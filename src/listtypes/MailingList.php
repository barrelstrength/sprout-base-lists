<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber as SubscriberRecord;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\Template;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;
use yii\base\Exception;
use yii\web\NotFoundHttpException;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 * @property string $handle
 */
class MailingList extends BaseListType
{
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Mailing List');
    }

    /**
     * @param Subscription $subscription
     *
     * @return bool|mixed
     * @throws \Throwable
     */
    public function add(Subscription $subscription): bool
    {
        $subscriber = $this->getSubscriber($subscription);

        try {
            // If our Subscriber doesn't exist, create a Subscriber Element
            if ($subscriber === null) {
                $subscriber = $this->createSubscriber($subscription, $this->settings->enableUserSync);
            }

            $list = $this->getList($subscription);

            // If our List doesn't exist, create a List Element
            if ($list === null && $this->settings->enableAutoList) {
                $list = new ListElement();
                $list->type = __CLASS__;
                $list->elementId = 1;
                $list->name = $subscription->listHandle ?? 'list:'.$subscription->listId;
                $list->handle = $subscription->listHandle ?? 'list:'.$subscription->listId;

                $this->saveList($list);
            }

            $subscription->listId = $list->id;
            $subscription->listType = $list->type;

            if (!$list) {
                throw new NotFoundHttpException(Craft::t('sprout-base-lists', 'Unable to find or create List'));
            }

            $subscriptionRecord = new SubscriptionRecord();
            $subscriptionRecord->listId = $list->id;
            $subscriptionRecord->itemId = $subscriber->id;

            if ($subscriptionRecord->save(false)) {
                $this->updateCount($subscriptionRecord->listId);
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param Subscription $subscription
     *
     * @return bool
     */
    public function remove(Subscription $subscription): bool
    {
        $list = $this->getList($subscription);

        if (!$list) {
            return false;
        }

        $subscriber = $this->getSubscriber($subscription);

        // Delete the subscription that matches the List and Subscriber IDs
        $subscriptions = SubscriptionRecord::deleteAll([
            'listId' => $list->id,
            'itemId' => $subscriber->id
        ]);

        if ($subscriptions != null) {
            $this->updateCount();

            return true;
        }

        return false;
    }

    /**=
     * @param Subscription $subscription
     *
     * @return bool
     */
    public function hasItem(Subscription $subscription): bool
    {
        if ($subscription->listId === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `listId` is required to check if a User is subscribed to a List.'));
        }

        // We need a user ID or an email
        if ($subscription->itemId === null && $subscription->email === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `itemId` or `email` are required to check if a User is subscribed to a List.'));
        }

        $list = $this->getList($subscription);

        // If we don't find a matching list, no subscription exists
        if ($list === null) {
            return false;
        }

        // Make sure we set all the values we can
        $subscription->listId = $list->id;
        $subscription->listHandle = $list->handle;

        $subscriber = $this->getSubscriber($subscription);

        if ($subscriber === null) {
            return false;
        }

        return SubscriptionRecord::find()->where([
            'listId' => $list->id,
            'itemId' => $subscriber->id
        ])->exists();
    }

    /**
     * Gets a subscriber
     *
     * @param Subscription $subscription
     *
     * @param bool         $sync
     *
     * @return Subscriber|\craft\base\ElementInterface|null|boolean
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     */
    public function createSubscriber(Subscription $subscription, $sync = false)
    {
        $user = null;

        // If subscriber not found prepare subscriber instance with email to create new subscriber
        $subscriber = new Subscriber();
        $subscriber->email = $subscription->email;
        $subscriber->firstName = $subscription->firstName ?? null;
        $subscriber->lastName = $subscription->lastName ?? null;

        // If enable user sync is on look for user element and assign it to userId column
        if ($sync) {
            // Try to find a matching User Element
            if (is_numeric($subscriber->userId)) {
                /** @var Element $element */
                $user = Craft::$app->elements->getElementById($subscriber->userId, User::class);
            } elseif ($subscriber->email !== null) {
                $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($subscriber->email);
            }

            // Use values from user profile as fallbacks
            $subscriber->firstName = $subscriber->firstName ?? $user->firstName;
            $subscriber->lastName = $subscriber->lastName ?? $user->lastName;

            if ($user && $subscriber->email !== null) {
                $subscriber->userId = $user->id;
            }
        }

        try {
            $this->saveSubscriber($subscriber);
        } catch (\Exception $exception) {
            throw new NotFoundHttpException(Craft::t('sprout-base-lists', 'Unable to create Subscriber'));
        }

        return $subscriber;
    }

    /**
     * Gets a subscriber with a given id.
     *
     * @param $id
     *
     * @return Subscriber|null
     */
    public function getSubscriberById($id)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getElements()->getElementById($id, Subscriber::class);
    }

    /**
     * Get a Subscriber Element based on a subscription
     *
     * @param Subscription $subscription
     *
     * @return Subscriber|null
     */
    public function getSubscriber(Subscription $subscription)
    {
        /** @var Subscriber $subscriber */
        $subscriber = null;

        /**
         * See if we find:
         * 1. Subscriber Element with matching ID
         * 2. A User Element with matching ID
         * 3. A Subscriber Element with a matching Email
         */
        if (is_numeric($subscription->itemId)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscription->itemId);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find a Subscriber with Element ID: {id}', [
                    'id' => $subscription->itemId
                ]), 'sprout-base-lists');

                return null;
            }

            // If we found a Subscriber Element grab that
            if (get_class($element) === Subscriber::class) {
                $subscriber = $element;
            }

            if ($subscriber === null) {
                if (get_class($element) !== User::class) {
                    return null;
                }

                // If it's a User ID, search our Subscriber table for an entry with a matching user.
                $subscriber = Subscriber::find()->where([
                    'sproutlists_subscribers.userId' => $element->id
                ])->one();
            }
        } elseif (is_string($subscription->itemId)) {
            $subscriber = Subscriber::find()->where([
                'sproutlists_subscribers.email' => $subscription->itemId
            ])->one();
        }

        // Only assign profile values if we have values
        // Don't overwrite any profile attributes with empty values
        // If itemId and email are both emails, we assume the email field is the priority
        if (!empty($subscription->firstName)) {
            $subscriber->firstName = $subscription->firstName;
        }

        if (!empty($subscription->lastName)) {
            $subscriber->lastName = $subscription->lastName;
        }

        if ($subscriber === null) {
            return null;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $subscriber;
    }

    /**
     * Deletes a subscriber.
     *
     * @param $id
     *
     * @return bool
     * @throws ElementNotFoundException
     * @throws \Throwable
     */
    public function deleteSubscriberById($id): bool
    {
        try {
            Craft::$app->getElements()->deleteElementById($id);
            SubscriberRecord::deleteAll('id = :subscriberId', [':subscriberId' => $id]);
            SubscriptionRecord::deleteAll('listId = :listId', [':listId' => $id]);

            $this->updateCount();

            return true;
        } catch (\Exception $e) {
            throw new ElementNotFoundException(Craft::t('sprout-base-lists', 'Unable to delete Subscriber.'));
        }
    }

    /**
     * @param ListElement $list
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getItems(ListElement $list)
    {
        if (empty($list->type)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: "type" is required by the getSubscribers variable.'));
        }

        if (empty($list->handle)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: "listHandle" is required by the getSubscribers variable.'));
        }

        $subscribers = [];

        if ($list === null) {
            return $subscribers;
        }

        $listRecord = ListElementRecord::find()->where([
            'type' => $list->type,
            'handle' => $list->handle
        ])->one();

        /**
         * @var $listRecord ListElementRecord
         */
        if ($listRecord != null) {
            $subscribers = $listRecord->getListsWithSubscribers()->all();

            return $subscribers;
        }

        return $subscribers;
    }

    /**
     * Returns an array of all lists that have subscribers.
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getListsWithSubscribers(): array
    {
        $listElementRecords = ListElementRecord::find()->all();

        if (!$listElementRecords) {
            return [];
        }

        $lists = [];

        /** @var $listElementRecord ListElementRecord */
        foreach ($listElementRecords as $listElementRecord) {

            $subscribers = $listElementRecord->getListsWithSubscribers()->all();

            if (empty($subscribers)) {
                continue;
            }

            $lists[] = $listElementRecord;
        }

        return $lists;
    }

    /**
     * Gets the HTML output for the lists sidebar on the Subscriber edit page.
     *
     * @param $subscriberId
     *
     * @return string|\Twig_Markup
     * @throws \Exception
     * @throws \Twig_Error_Loader
     */
    public function getListElementsHtml($subscriberId)
    {
        $subscriber = null;
        $listIds = [];

        if ($subscriberId !== null) {
            /**
             * @var $subscriber Subscriber
             */
            $subscriber = $this->getSubscriberById($subscriberId);

            if ($subscriber) {
                $listIds = $subscriber->getListIds();
            }
        }

        /** @var ListElement[] $lists */
        $lists = ListElement::find()->where([
            'sproutlists_lists.type' => __CLASS__
        ])->all();

        $options = [];

        if (count($lists)) {
            foreach ($lists as $list) {
                $options[] = [
                    'label' => sprintf('%s', $list->name),
                    'value' => $list->id
                ];
            }
        }

        // Return a blank template if we have no lists
        if (empty($options)) {
            return '';
        }

        $html = Craft::$app->getView()->renderTemplate('sprout-base-lists/subscribers/_mailinglists', [
            'options' => $options,
            'values' => $listIds
        ]);

        return Template::raw($html);
    }

    // Subscriptions
    // =========================================================================

    /**
     * Saves a subscription
     *
     * @param Subscriber $subscriber
     *
     * @return bool
     * @throws \Exception
     */
    public function saveSubscriptions(Subscriber $subscriber)
    {
        try {
            if (!empty($subscriber->listElements)) {
                foreach ($subscriber->listElements as $listId) {
                    $list = $this->getListById($listId);

                    if ($list) {
                        $subscriptionRecord = new SubscriptionRecord();
                        $subscriptionRecord->listId = $list->id;
                        $subscriptionRecord->itemId = $subscriber->id;

                        if (!$subscriptionRecord->save(false)) {

                            SproutBaseLists::error($subscriptionRecord->getErrors());

                            throw new Exception(Craft::t('sprout-lists', 'Unable to save subscription.'));
                        }
                    } else {
                        throw new Exception(Craft::t('sprout-lists', 'The Subscriber List with id {listId} does not exists.', [
                            'listId' => $listId
                        ]));
                    }
                }
            }

            $this->updateCount();

            return true;
        } catch (\Exception $e) {
            Craft::error($e->getMessage());
            throw $e;
        }
    }

    // Subscriber
    // =========================================================================

    /**
     * Saves a subscriber
     *
     * @param Subscriber $subscriber
     *
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveSubscriber(Subscriber $subscriber): bool
    {
        if (!$subscriber->validate(null, false)) {
            return false;
        }

        if (Craft::$app->getElements()->saveElement($subscriber)) {
            $this->saveSubscriptions($subscriber);

            return true;
        }

        return false;
    }


    public function cpBeforeSaveSubscriber($subscriber)
    {
        SubscriptionRecord::deleteAll('itemId = :itemId', [
            ':itemId' => $subscriber->id
        ]);

        return null;
    }
}

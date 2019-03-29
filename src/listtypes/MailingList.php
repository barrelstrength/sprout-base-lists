<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Template;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;
use yii\base\Exception;

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
        // If our Subscriber doesn't exist, create a Subscriber Element
        $subscriber = $this->getOrCreateSubscriber($subscription, $this->settings->enableUserSync);

        if (!$subscriber) {
            $subscription->addError('itemId', Craft::t('sprout-base-lists', 'No user found with id: {id}', [
                'id' => $subscription->itemId
            ]));
        }

        if ($subscription->getErrors()) {
            return false;
        }

        // Only assign profile values if we have values
        // Don't overwrite any profile attributes with empty values
        if (!empty($subscription->email)) {
            $subscriber->email = $subscription->email;
        }

        if (!empty($subscription->firstName)) {
            $subscriber->firstName = $subscription->firstName;
        }

        if (!empty($subscription->lastName)) {
            $subscriber->lastName = $subscription->lastName;
        }

        if ($this->settings->enableUserSync) {
            $subscriber->itemId = $subscription->itemId;
        }

        try {
            // If our List doesn't exist, create a List Element
            $list = $this->getOrCreateList($subscription, $this->settings->enableAutoList);
            $subscription->listId = $list->id;
            $subscription->listType = $list->type;

            if ($subscription->getErrors()) {
                return false;
            }

            $subscriptionRecord = new SubscriptionRecord();

            if ($list) {
                $subscriptionRecord->listId = $list->id;
                $subscriptionRecord->itemId = $subscriber->id;

                // Create a criteria between our List Element and Subscriber Element
                if ($subscriptionRecord->save(false)) {
                    $this->updateCount($subscriptionRecord->listId);
                }
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
        $listElement = new ListElement();

        if (is_numeric($subscription->listId)) {
            $listElement->id = $subscription->listId;
        } elseif (is_string($subscription->listId)) {
            $listElement->handle = $subscription->listId;
        }

        $list = $this->getList($listElement);

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

        $listElement = new ListElement();

        // Assign id property if it is listId and handle property if string
        if (is_numeric($subscription->listId)) {
            $listElement->id = $subscription->listId;
        } elseif (is_string($subscription->listId)) {
            $listElement->handle = $subscription->listId;
        }

        $list = $this->getList($listElement);

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
     * @param bool         $sync
     * @param Subscription $subscription
     *
     * @return Subscriber|\craft\base\ElementInterface|null|boolean
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getOrCreateSubscriber(Subscription $subscription, $sync = false)
    {
        $user = null;
        $email = trim($subscription->email);

        if (is_string($subscription->itemId)) {
            $email = $subscription->itemId;
        }

        // Make sure an email is an email
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $subscription->addError('email', Craft::t('sprout-base-lists', 'Email is invalid.'));
        }

        $subscriber = $this->getSubscriber($subscription);

        if ($subscriber == null) {
            // If subscriber not found prepare subscriber instance with email to create new subscriber
            $subscriber = new Subscriber();
            $subscriber->email = $email;
        }

        // Support adding of firstName and lastName
        $subscriber->firstName = $subscription->firstName ?? null;
        $subscriber->lastName = $subscription->lastName ?? null;

        // If enable user sync is on look for user element and assign it to userId column
        if ($sync) {
            // Try to find a matching User Element
            if (is_numeric($subscription->itemId)) {
                /** @var Element $element */
                $user = Craft::$app->elements->getElementById($subscription->itemId, User::class);
            } elseif (is_string($subscription->itemId)) {
                $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($subscription->itemId);
            } elseif ($email !== null) {
                $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
            }

            // Use values from user profile as fallbacks
            $subscriber->firstName = $subscription->firstName ?? $user->firstName;
            $subscriber->lastName = $subscription->lastName ?? $user->lastName;

            if ($user && $subscriber->email !== null) {
                $subscriber->userId = $user->id;
            }
        }

        $this->saveSubscriber($subscriber);

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
     * @param Subscriber $subscriber
     *
     * @return Subscriber|null
     */
    public function getSubscriber(Subscription $subscription)
    {
        $subscriber = new Subscriber();

        if (is_numeric($subscription->itemId)) {
            $subscriber->id = $subscription->itemId;
        }

        if (is_string($subscription->itemId)) {
            $subscriber->email = $subscription->itemId;
        }

        if ($subscription->email !== null) {
            $subscriber->email = $subscription->email;
        }

        /**
         * See if we find:
         * 1. Subscriber Element with matching ID
         * 2. A User Element with matching ID
         * 3. Any Element with a matching ID
         * 4.
         */
        if ($subscriber->id !== null) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscriber->itemId);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find a Subscriber with Element ID: {id}', [
                    'id' => $subscriber->itemId
                ]), 'sprout-base-lists');

                return null;
            }

            // If we found a Subscriber Element grab that
            if (get_class($element) === Subscriber::class) {
                /** @noinspection PhpIncompatibleReturnTypeInspection */
                return $element;
            }

            if (get_class($element) !== User::class) {
                return null;
            }

            /** @var User $element */
            $subscriber->email = $subscriber->email ?? $element->email;

            // Search for matching Subscribers mapped to the provided User ID and/or Email
            // @todo this needs refactoring. If a Subscriber doesn't have a userId, they could still have a matching email
            // so some scenarios, like having a user in the db and then enabling user sync, may not work right.
            $attributes = array_filter([
                'sproutlists_subscribers.email' => $subscriber->email
            ]);

            $subscriberQuery = Subscriber::find()->where($attributes);

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $subscriberQuery->one();
        }

        if ($subscriber->email !== null) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return Subscriber::find()->where([
                'sproutlists_subscribers.email' => $subscriber->email
            ])->one();
        }

        return null;
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
            SubscribersRecord::deleteAll('id = :subscriberId', [':subscriberId' => $id]);
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

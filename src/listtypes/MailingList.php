<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\elements\SubscriberList;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Template;
use barrelstrength\sproutbaselists\records\Subscriber as SubscribersRecord;
use barrelstrength\sproutbaselists\records\SubscriberList as SubscriberListRecord;
use yii\base\Exception;
use yii\web\NotFoundHttpException;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 * @property string $handle
 */
class MailingList extends BaseSproutListType
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
    public function subscribe(Subscription $subscription): bool
    {
        // If our Subscriber doesn't exist, create a Subscriber Element
        $subscriber = $this->getOrCreateSubscriber($subscription, $this->settings->enableUserSync);

        if (!$subscriber) {
            $subscription->addError('elementId', Craft::t('sprout-base-lists', 'No user found with id: {id}', [
                'id' => $subscription->elementId
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
            $subscriber->elementId = $subscription->elementId;
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
                $subscriptionRecord->subscriberId = $subscriber->id;

                // Create a criteria between our List Element and Subscriber Element
                if ($subscriptionRecord->save(false)) {
                    $this->updateTotalSubscribersCount($subscriptionRecord->listId);
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
     * @throws NotFoundHttpException
     */
    public function unsubscribe(Subscription $subscription): bool
    {
        $subscriberList = new SubscriberList();
        $subscriberList->id = $subscription->listId;
        $subscriberList->handle = $subscription->listHandle;

        $list = $this->getList($subscriberList);

        if (!$list) {
            return false;
        }

//        if ($subscription->elementId) {
//            $list->elementId = $list->id;
//            $list->save();
//        }

        $subscriber = new Subscriber();
        $subscriber->elementId = $subscription->elementId;
        $subscriber->email = $subscription->email;

        $subscriber = $this->getSubscriber($subscriber);

        // Delete the subscription that matches the List and Subscriber IDs
        $subscriptions = SubscriptionRecord::deleteAll([
            'listId' => $list->id,
            'subscriberId' => $subscriber->id
        ]);

        if ($subscriptions != null) {
            $this->updateTotalSubscribersCount();

            return true;
        }

        return false;
    }

    /**=
     * @param Subscription $subscription
     *
     * @return bool
     * @throws NotFoundHttpException
     */
    public function isSubscribed(Subscription $subscription): bool
    {
        if ($subscription->listId === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `listId` is required to check if a User is subscribed to a List.'));
        }

        // We need a user ID or an email
        if ($subscription->elementId === null && $subscription->email === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `elementId` or `email` are required to check if a User is subscribed to a List.'));
        }

        $subscriberList = new SubscriberList();
        $subscriberList->id = $subscription->listId;
        $subscriberList->handle = $subscription->listHandle;

        $list = $this->getList($subscriberList);

        // If we don't find a matching list, no subscription exists
        if ($list === null) {
            return false;
        }

        // Make sure we set all the values we can
        $subscription->listId = $list->id;
        $subscription->listHandle = $list->handle;

        $subscriber = new Subscriber();
        $subscriber->elementId = $subscription->elementId;
        $subscriber->email = $subscription->email;

        $subscriber = $this->getSubscriber($subscriber);

        if ($subscriber === null) {
            return false;
        }

        return SubscriptionRecord::find()->where([
            'listId' => $list->id,
            'subscriberId' => $subscriber->id
        ])->exists();
    }

    /**
     * Gets a subscriber
     *
     * @param bool         $sync
     * @param Subscription $subscription
     *
     * @return Subscriber|\craft\base\ElementInterface|null
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getOrCreateSubscriber(Subscription $subscription, $sync = false)
    {
        $user = null;
        $email = trim($subscription->email);

        // Try to find a matching User Element
        if (is_numeric($subscription->elementId)) {
            /** @var Element $element */
            $user = Craft::$app->elements->getElementById($subscription->elementId, User::class);
        } elseif (is_string($subscription->elementId)) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($subscription->elementId);
        } elseif ($email !== null) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        }

        // Make sure an email is an email
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $subscription->addError('email', Craft::t('sprout-base-lists', 'Email is invalid.'));
        }

        if (!$user) {
            $subscription->addError('elementId', Craft::t('sprout-base-lists', 'No user found with id: {id}', [
                'id' => $subscription->elementId
            ]));
        } elseif ($this->settings->enableUserSync) {
            // Use values from user profile as fallbacks
            $subscription->elementId = $user->id;
            $subscription->firstName = $subscription->firstName ?? $user->firstName;
            $subscription->lastName = $subscription->lastName ?? $user->lastName;
        }

        // Make sure we have the email (if only the elementId was provided)
        $subscription->email = $subscription->email ?? $user->email;

        // Now, check for a matching Subscriber Element
        $subscriber = new Subscriber();
        // The elementId represents the Subscriber Element or the User Element for the Mailing List
        $subscriber->elementId = $subscription->elementId;
        $subscriber->email = $subscription->email;

        if ($subscriberModel = $this->getSubscriber($subscriber)) {
            return $subscriberModel;
        }

        if ($sync === true && $user && $subscriber->email !== null) {
            $subscriber->elementId = $user->id;
        }

        $this->saveSubscriber($subscriber);

        return $subscriber;
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return Subscriber|null
     */
    public function getSubscriber(Subscriber $subscriber)
    {
        /**
         * See if we find:
         * 1. Subscriber Element with matching ID
         * 2. A User Element with matching ID
         * 3. Any Element with a matching ID
         * 4.
         */
        if (is_numeric($subscriber->elementId)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscriber->elementId);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find a Subscriber with Element ID: {id}', [
                    'id' => $subscriber->elementId
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
//                'sproutlists_subscribers.userId' => $subscriber->elementId,
                'sproutlists_subscribers.email' => $subscriber->email
            ]);

            $subscriberQuery = Subscriber::find()->where($attributes);

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $subscriberQuery->one();
        }

        if (is_string($subscriber->elementId)) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return SubscriberList::find()->where([
                'sproutlists_subscribers.email' => $subscriber->elementId
            ])->one();
        }

        return null;
    }

    /**
     * @param SubscriberList $list
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getSubscribers(SubscriberList $list)
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

        $listRecord = SubscriberListRecord::find()->where([
            'type' => $list->type,
            'handle' => $list->handle
        ])->one();

        /**
         * @var $listRecord SubscriberListRecord
         */
        if ($listRecord != null) {
            $subscribers = $listRecord->getSubscribers()->all();

            return $subscribers;
        }

        return $subscribers;
    }
}

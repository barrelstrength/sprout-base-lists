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
class WishList extends BaseSproutListType
{
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Wish List');
    }

    public function subscribe(Subscription $subscription): bool {

        return false;
    }

    public function unsubscribe(Subscription $subscription): bool {
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
        if (empty($subscription->listId)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `listId` is required to check if an item is already on a List.'));
        }

        // We need a user ID or an email
        if ($subscription->itemId === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `itemId` is required to check if an item is already on a List.'));
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
        $subscriber->elementId = $subscription->itemId;

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
     * @param Subscriber $subscriber
     *
     * @return Element|null
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
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find an Element with ID: {id}', [
                    'id' => $subscriber->elementId
                ]), 'sprout-base-lists');

                return null;
            }

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $element;
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

        if (empty($list->id)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: "id" is required by the getSubscribers variable.'));
        }

        $subscribers = [];

        if ($list === null) {
            return $subscribers;
        }

        $listRecord = SubscriberListRecord::find()->where([
            'id' => $list->id,
            'type' => $list->type
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

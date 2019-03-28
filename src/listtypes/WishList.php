<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use Craft;
use craft\base\Element;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 * @property string $handle
 */
class WishList extends BaseListType
{
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Wish List');
    }

    public function add(Subscription $subscription): bool {

        return false;
    }

    public function remove(Subscription $subscription): bool {
        return false;
    }

    /**=
     * @param Subscription $subscription
     *
     * @return bool
     */
    public function hasItem(Subscription $subscription): bool
    {
        if (empty($subscription->listId)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `listId` is required to check if an item is already on a List.'));
        }

        // We need a user ID or an email
        if ($subscription->itemId === null) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: `itemId` is required to check if an item is already on a List.'));
        }

        $listElement = new ListElement();
        $listElement->id = $subscription->listId;
        $listElement->handle = $subscription->listHandle;

        $list = $this->getList($listElement);

        // If we don't find a matching list, no subscription exists
        if ($list === null) {
            return false;
        }

        // Make sure we set all the values we can
        $subscription->listId = $list->id;
        $subscription->listHandle = $list->handle;

        $subscriber = new Subscriber();
        $subscriber->userId = $subscription->itemId;

        $subscriber = $this->getSubscriber($subscriber);

        if ($subscriber === null) {
            return false;
        }

        return SubscriptionRecord::find()->where([
            'listId' => $list->id,
            'itemId' => $subscriber->id
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
        if (is_numeric($subscriber->userId)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscriber->userId);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find an Element with ID: {id}', [
                    'id' => $subscriber->userId
                ]), 'sprout-base-lists');

                return null;
            }

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $element;
        }

        return null;
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

        if (empty($list->id)) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'Missing argument: "id" is required by the getSubscribers variable.'));
        }

        $subscribers = [];

        if ($list === null) {
            return $subscribers;
        }

        $listRecord = ListElementRecord::find()->where([
            'id' => $list->id,
            'type' => $list->type
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
}

<?php

namespace barrelstrength\sproutbaselists\base;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\listtypes\MailingList;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;
use craft\base\Element;
use yii\web\NotFoundHttpException;

/**
 * Trait ListTrait
 *
 * @package barrelstrength\sproutbaselists\base
 */
trait ListTrait
{
    /**
     * Prepare the Subscription model for the `add` and `remove` methods
     *
     * @return Subscription
     */
    public function populateSubscriptionFromPost(): Subscription
    {
        $subscription = new Subscription();
        $subscription->listType = get_class($this);
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId');
        $subscription->elementId = Craft::$app->getRequest()->getBodyParam('elementId');
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('listHandle');
        $subscription->itemId = Craft::$app->getRequest()->getBodyParam('itemId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscription->firstName = Craft::$app->getRequest()->getBodyParam('firstName');
        $subscription->lastName = Craft::$app->getRequest()->getBodyParam('lastName');

        return $subscription;
    }

    /**
     * @param Subscription $subscription
     *
     * @return bool
     * @throws \Throwable
     */
    public function add(Subscription $subscription): bool
    {
        if ($this->requireEmailForSubscription = true) {
            $subscription->setScenario(Subscription::SCENARIO_SUBSCRIBER);
        }

        if (!$subscription->validate()) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            /** @var Element $item */
            $item = $this->getSubscriberOrItem($subscription);

            // If our Subscriber doesn't exist, create a Subscriber Element
            if ($item === null) {
                $item = new Subscriber();
                $item->userId = $subscription->itemId;
                $item->email = $subscription->email;
                $item->firstName = $subscription->firstName ?? null;
                $item->lastName = $subscription->lastName ?? null;

                $this->saveSubscriber($item);

                $subscription->itemId = $item->id;
            }

            $list = $this->getList($subscription);

            // If our List doesn't exist, create a List Element
            if ($list === null && $this->settings->enableAutoList) {
                $list = new ListElement();
                $list->type = __CLASS__;
                $list->elementId = $subscription->elementId;
                $list->name = $subscription->listHandle ?? 'list:'.$subscription->listId;
                $list->handle = $subscription->listHandle ?? 'list:'.$subscription->listId;

                $this->saveList($list);

                $subscription->listId = $list->id;
            }

            if (!$list) {
                throw new NotFoundHttpException(Craft::t('sprout-base-lists', 'Unable to find or create List'));
            }

            if (!$item->validate() || !$list->validate()) {
                $subscription->addErrors($item->getErrors());
                $subscription->addErrors($list->getErrors());
                return false;
            }

            $subscriptionRecord = new SubscriptionRecord();
            $subscriptionRecord->listId = $list->id;
            $subscriptionRecord->itemId = $item->id;

            if ($subscriptionRecord->save()) {
                $this->updateCount($subscriptionRecord->listId);
            } else {
                Craft::warning(Craft::t('sprout-base-lists', 'List Item {itemId} already exists on List ID {listId}.', [
                    'listId' => $list->id,
                    'itemId' => $item->id
                ]));
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
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

        $item = $this->getSubscriberOrItem($subscription);

        if (!$item) {
            return false;
        }

        // Delete the subscription that matches the List and Subscriber IDs
        $subscriptions = SubscriptionRecord::deleteAll([
            'listId' => $list->id,
            'itemId' => $item->id
        ]);

        if ($subscriptions !== null) {
            $this->updateCount();

            return true;
        }

        return false;
    }

    // ListElement
    // =========================================================================

    /**
     * @param Subscription $subscription
     *
     * @return ListElement|null
     */
    public function getList(Subscription $subscription)
    {
        $query = ListElement::find()
            ->where([
                'sproutlists_lists.type' => $subscription->listType
            ]);

        if ($subscription->listId && $subscription->listHandle) {
            $query->andWhere([
                'and',
                ['sproutlists_lists.id' => $subscription->listId],
                ['sproutlists_lists.handle' => $subscription->listHandle]
            ]);
        } else {
            $query->andWhere([
                'or',
                ['sproutlists_lists.id' => $subscription->listId],
                ['sproutlists_lists.handle' => $subscription->listHandle]
            ]);
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $query->one();
    }

    /**
     * Get all Lists for a given List Type
     *
     * @return \craft\base\ElementInterface[]
     */
    public function getLists(): array
    {
        return ListElement::find()
            ->where([
                'sproutlists_lists.type' => get_class($this)
            ])->all();
    }

    /**
     * Saves a list.
     *
     * @param ListElement $list
     *
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveList(ListElement $list): bool
    {
        return Craft::$app->elements->saveElement($list);
    }

    /**
     * Deletes a list.
     *
     * @param ListElement $list
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteList(ListElement $list): bool
    {
        $listRecord = ListElementRecord::findOne($list->id);

        if ($listRecord === null) {
            return false;
        }

        if ($listRecord AND $listRecord->delete()) {
            $subscriptions = SubscriptionRecord::find()->where([
                'listId' => $list->id
            ]);

            if ($subscriptions != null) {
                SubscriptionRecord::deleteAll('listId = :listId', [
                    ':listId' => $list->id
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * @param ListElement $list
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getSubscriptions(ListElement $list)
    {
        return SubscriptionRecord::find()
            ->where(['listId' => $list->id])
            ->all();
    }

    /**
     * @param array $criteria
     *
     * @return Subscription
     */
    public function populateSubscriptionFromIsSubscribedCriteria(array $criteria = []): Subscription
    {
        $subscription = new Subscription();
        $subscription->listType = get_class($this);
        $subscription->listId = $criteria['listId'] ?? null;
        $subscription->listHandle = $criteria['listHandle'] ?? null;
        $subscription->itemId = $criteria['itemId'] ?? null;
        $subscription->email = $criteria['email'] ?? null;

        return $subscription;
    }

    /**=
     * @param Subscription $subscription
     *
     * @return bool
     */
    public function isSubscribed(Subscription $subscription): bool
    {
        $list = $this->getList($subscription);

        // If we don't find a matching list, no subscription exists
        if ($list === null) {
            return false;
        }

        // Make sure we set all the values we can
        if (!empty($subscription->listId)) {
            $subscription->listId = $list->id;
        }

        if (!empty($subscription->listHandle)) {
            $subscription->listHandle = $list->handle;
        }

        $item = $this->getSubscriberOrItem($subscription);

        if ($item === null) {
            return false;
        }

        return SubscriptionRecord::find()->where([
            'listId' => $list->id,
            'itemId' => $item->id
        ])->exists();
    }

    /**
     * @param ListElement $list
     *
     * @return int
     * @throws \Exception
     */
    public function getCount(ListElement $list): int
    {
        $items = $this->getSubscriptions($list);

        return count($items);
    }

    /**
     * Updates the count column in the db
     *
     * @todo - delegate this to the queue
     *
     * @param null $listId
     *
     * @return bool
     */
    public function updateCount($listId = null): bool
    {
        if ($listId === null) {
            $lists = ListElement::find()->all();
        } else {
            $list = ListElement::findOne($listId);

            $lists = [$list];
        }

        if (!count($lists)) {
            return false;
        }

        /** @var ListElement[] $lists */
        foreach ($lists as $list) {

            if (!$list) {
                continue;
            }

            $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);

            $count = $listType->getCount($list);
            $list->count = $count;

            $listType->saveList($list);
        }

        return true;
    }
}

<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\SubscriberList;
use barrelstrength\sproutbaselists\elements\Subscriber;
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
 */
abstract class BaseSproutListType extends ListType
{
    // SubscriberList
    // =========================================================================

    /**
     * Saves a list.
     *
     * @param SubscriberList $list
     *
     * @return bool|mixed
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveList(SubscriberList $list)
    {
        $list->totalSubscribers = 0;

        return Craft::$app->elements->saveElement($list);
    }

    /**
     * Gets lists.
     *
     * @param Subscriber $subscriber
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getLists(Subscriber $subscriber = null): array
    {
        /** @var SubscribersRecord $subscriberRecord */
        $subscriberRecord = null;
        $lists = [];

        /** @var $subscriber Subscriber */
        if ($subscriber !== null && (!empty($subscriber->email) || !empty($subscriber->userId))) {
            $subscriberAttributes = array_filter([
                'email' => $subscriber->email,
                'userId' => $subscriber->userId
            ]);

            $subscriberRecord = SubscribersRecord::find()->where($subscriberAttributes)->one();
        }

        $listRecords = [];

        if ($subscriberRecord == null) {
            // Only findAll if we are not looking for a specific Subscriber, otherwise we want to return null
            if (empty($subscriber->email)) {
                $listRecords = SubscriberListRecord::find()->all();
            }
        } else {
            $listRecords = $subscriberRecord->getLists()->all();
        }

        if (!empty($listRecords)) {

            foreach ($listRecords as $listRecord) {
                $list = new SubscriberList();
                $list->setAttributes($listRecord->getAttributes(), false);
                $lists[] = $list;
            }
        }

        return $lists;
    }

    /**
     * Get the total number of lists for a given subscriber
     *
     * @param Subscriber|null $subscriber
     *
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public function getListCount(Subscriber $subscriber = null): int
    {
        $lists = $this->getLists($subscriber);

        return count($lists);
    }

    /**
     * Gets list with a given id.
     *
     * @param int $listId
     *
     * @return \craft\base\ElementInterface|mixed|null
     */
    public function getListById(int $listId)
    {
        return Craft::$app->getElements()->getElementById($listId, SubscriberList::class);
    }

    /**
     * Returns an array of all lists that have subscribers.
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getListsWithSubscribers(): array
    {
        $subscriberListRecords = SubscriberListRecord::find()->all();

        if (!$subscriberListRecords) {
            return [];
        }

        $lists = [];

        /** @var $subscriberList SubscriberListRecord  */
        foreach ($subscriberListRecords as $subscriberList) {

            $subscribers = $subscriberList->getSubscribers()->all();

            if (empty($subscribers)) {
                continue;
            }

            $lists[] = $subscriberList;
        }

        return $lists;
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
            if (!empty($subscriber->subscriberLists)) {
                foreach ($subscriber->subscriberLists as $listId) {
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

            $this->updateTotalSubscribersCount();

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

    /**
     * Gets a subscriber with a given id.
     *
     * @param $id
     *
     * @return \craft\base\ElementInterface|null
     */
    public function getSubscriberById($id)
    {
        return Craft::$app->getElements()->getElementById($id, Subscriber::class);
    }

    /**
     * Deletes a subscriber.
     *
     * @param $id
     *
     * @return Subscriber
     * @throws \Throwable
     */
    public function deleteSubscriberById($id): Subscriber
    {
        /**
         * @var $subscriber Subscriber
         */
        $subscriber = $this->getSubscriberById($id);

        if ($subscriber AND ($subscriber AND $subscriber != null)) {
            SproutBaseLists::$app->subscribers->deleteSubscriberById($id);
        }

        $this->updateTotalSubscribersCount();

        return $subscriber;
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
    public function getSubscriberListsHtml($subscriberId)
    {
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

        $lists = $this->getLists();

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

        $html = Craft::$app->getView()->renderTemplate('sprout-base-lists/subscribers/_subscriberlists', [
            'options' => $options,
            'values' => $listIds
        ]);

        return Template::raw($html);
    }

    /**
     * Updates the totalSubscribers column in the db
     *
     * @param null $listId
     *
     * @return bool
     */
    public function updateTotalSubscribersCount($listId = null): bool
    {
        if ($listId == null) {
            $lists = SubscriberListRecord::find()->all();
        } else {
            $list = SubscriberListRecord::findOne($listId);

            $lists = [$list];
        }

        if (count($lists)) {
            foreach ($lists as $list) {

                if (!$list) {
                    continue;
                }

                $count = count($list->getSubscribers()->all());

                $list->totalSubscribers = $count;

                $list->save();
            }

            return true;
        }

        return false;
    }

    /**
     * @param SubscriberList $list
     *
     * @return int|mixed
     * @throws \Exception
     */
    public function getSubscriberCount(SubscriberList $list)
    {
        $subscribers = $this->getSubscribers($list);

        return count($subscribers);
    }

    /**
     * Gets or creates list
     *
     * @param Subscription $subscription
     * @param bool         $enableAutoList
     *
     * @return SubscriberList
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getOrCreateList(Subscription $subscription, $enableAutoList = false): SubscriberList
    {
        $list = new SubscriberList();
        $list->id = $subscription->listId;
        $list->handle = $subscription->listHandle;

        /** @var SubscriberList|null $list */
        if ($list = $this->getList($list)) {
            return $list;
        }

        // Dynamically create a list
        if ($enableAutoList) {
            $list = new SubscriberList();
            $list->type = __CLASS__;
            $list->elementId = 1;
            $list->name = $subscription->listHandle ?? 'list:'.$subscription->listId;
            $list->handle = $subscription->listHandle ?? 'list:'.$subscription->listId;

            $this->saveList($list);

            return $list;
        }

        throw new NotFoundHttpException(Craft::t('sprout-base-lists', 'Unable to find a List with Element ID: {id}', [
            'id' => $subscription->listId
        ]));
    }

    /**
     * @param SubscriberList $subscriberList
     *
     * @return SubscriberList|null
     */
    public function getList(SubscriberList $subscriberList)
    {
        /**
         * See if we find:
         * 1. List Element with matching ID
         * 2. ANY Element with matching ID
         * 3. List Element with matching handle
         */
        if (is_numeric($subscriberList->id)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscriberList->id);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find a List with Element ID: {id}', [
                    'id' => $subscriberList->id
                ]), 'sprout-base-lists');

                return null;
            }

            if (get_class($element) === SubscriberList::class) {
                /** @noinspection PhpIncompatibleReturnTypeInspection */
                return $element;
            }

            // If we found an Element that is not a Subscriber List, it should be mapped to a Subscriber List
            // Check both the Element ID and the Handle as the Handle lets an Element be mapped to more than one list
            $attributes = array_filter([
                'sproutlists_lists.elementId' => $element->id,
                'sproutlists_lists.handle' => $subscriberList->handle
            ]);

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return SubscriberList::find()->where($attributes)->one();
        }

        if (is_string($subscriberList->id)) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return SubscriberList::find()->where([
                'sproutlists_lists.handle' => $subscriberList->id
            ])->one();
        }

        return null;
    }

    public function cpBeforeSaveSubscriber($subscriber)
    {
        SubscriptionRecord::deleteAll('itemId = :itemId', [
            ':itemId' => $subscriber->id
        ]);

        return null;
    }
}

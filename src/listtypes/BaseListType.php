<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use craft\base\Element;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;
use yii\base\Exception;
use yii\web\NotFoundHttpException;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 */
abstract class BaseListType extends ListType
{
    // ListElement
    // =========================================================================

    /**
     * Saves a list.
     *
     * @param ListElement $list
     *
     * @return bool|mixed
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveList(ListElement $list)
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

        if ($listRecord == null) {
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
     * Gets list with a given id.
     *
     * @param int $listId
     *
     * @return \craft\base\ElementInterface|mixed|null
     */
    public function getListById(int $listId)
    {
        return Craft::$app->getElements()->getElementById($listId, ListElement::class);
    }

    /**
     * Updates the count column in the db
     *
     * @param null $listId
     *
     * @return bool
     */
    public function updateCount($listId = null): bool
    {
        if ($listId == null) {
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

    /**
     * @param ListElement $list
     *
     * @return int|mixed
     * @throws \Exception
     */
    public function getCount(ListElement $list)
    {
        $subscribers = $this->getItems($list);

        return count($subscribers);
    }

    /**
     * Gets or creates list
     *
     * @param Subscription $subscription
     * @param bool         $enableAutoList
     *
     * @return ListElement
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getOrCreateList(Subscription $subscription, $enableAutoList = false): ListElement
    {
        $listElement = new ListElement();

        // Assign id property if it is listId and handle property if string
        if (is_numeric($subscription->listId)) {
            $listElement->id = $subscription->listId;
        } elseif (is_string($subscription->listId)) {
            $listElement->handle = $subscription->listId;
        }

//        $list = new ListElement();
//        $list->id = $subscription->listId;
//        $list->handle = $subscription->listHandle;

        /** @var ListElement|null $list */
        if ($list = $this->getList($listElement)) {
            return $list;
        }

        // Dynamically create a list
        if ($enableAutoList) {
            $list = new ListElement();
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
     * @param ListElement $listElement
     *
     * @return ListElement|null
     */
    public function getList(ListElement $listElement)
    {
        /**
         * See if we find:
         * 1. List Element with matching ID
         * 2. ANY Element with matching ID
         * 3. List Element with matching handle
         */
        if ($listElement->id !== null) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($listElement->id);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find a List with Element ID: {id}', [
                    'id' => $listElement->id
                ]), 'sprout-base-lists');

                return null;
            }

            if (get_class($element) === ListElement::class) {
                /** @noinspection PhpIncompatibleReturnTypeInspection */
                return $element;
            }

            // If we found an Element that is not a Subscriber List, it should be mapped to a Subscriber List
            // Check both the Element ID and the Handle as the Handle lets an Element be mapped to more than one list
            $attributes = array_filter([
                'sproutlists_lists.elementId' => $element->id,
                'sproutlists_lists.handle' => $listElement->handle
            ]);

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return ListElement::find()->where($attributes)->one();
        }

        if ($listElement->handle !== null) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return ListElement::find()->where([
                'sproutlists_lists.handle' => $listElement->handle
            ])->one();
        }

        return null;
    }
}

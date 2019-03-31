<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;

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
            $query->andWhere(['and',
                ['sproutlists_lists.id' => $subscription->listId],
                ['sproutlists_lists.handle' => $subscription->listHandle]
            ]);
        } else {
            $query->andWhere(['or',
                ['sproutlists_lists.id' => $subscription->listId],
                ['sproutlists_lists.handle' => $subscription->listHandle]
            ]);
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $query->one();
    }

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
}

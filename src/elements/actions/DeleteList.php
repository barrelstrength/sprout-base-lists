<?php

namespace barrelstrength\sproutbaselists\elements\actions;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;

/**
 * Class DeleteList
 *
 * @package barrelstrength\sproutbaselists\elements\actions
 */
class DeleteList extends Delete
{
    /**
     * @var string|null The confirmation message that should be shown before the elements get deleted
     */
    public $confirmationMessage = 'Are you sure you want to delete this list(s)?';

    /**
     * @var string|null The message that should be shown after the elements get deleted
     */
    public $successMessage = 'List(s) deleted.';

    /**
     * @param ElementQueryInterface $query
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        /**
         * @var ListElement[] $lists
         */
        $lists = $query->all();

        // Delete the users
        foreach ($lists as $list) {
            $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);
            $list = $listType->getListById($list->id);

            $listType->deleteList($list);
        }

        $this->setMessage(Craft::t('sprout-lists', 'List(s) deleted.'));

        return true;
    }
}

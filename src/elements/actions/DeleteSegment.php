<?php

namespace barrelstrength\sproutbasereports\elements\actions;

use Craft;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;

/**
 * DeleteReport represents a Delete reports element action.
 *
 * @package barrelstrength\sproutbaseemail\elements\actions
 */
class DeleteSegment extends Delete
{
    /**
     * @var string|null The confirmation message that should be shown before the elements get deleted
     */
    public $confirmationMessage = 'Are you sure you want to delete the selected segments?';

    /**
     * @var string|null The message that should be shown after the elements get deleted
     */
    public $successMessage = 'Segments deleted.';

    /**
     *  Performs the action on any elements that match the given criteria.
     *  return Whether the action was performed successfully.
     *
     * @param ElementQueryInterface $query
     *
     * @return bool
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        parent::performAction($query);

        $this->setMessage(Craft::t('sprout-base-lists', 'Segments deleted.'));

        return true;
    }
}

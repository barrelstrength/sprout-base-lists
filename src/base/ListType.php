<?php

namespace barrelstrength\sproutbaselists\base;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\models\Subscription;
use craft\base\Component;

/**
 *
 * @property mixed $className
 */
abstract class ListType extends Component
{
    /**
     * @var Settings $settings
     */
    public $settings;

    public function init()
    {
        $this->settings = SproutBase::$app->settings->getPluginSettings('sprout-lists');

        parent::init();
    }

    /**
     * Returns the class name of this List Type
     *
     * @return mixed
     */
    final public function getClassName()
    {
        return str_replace('Craft\\', '', get_class($this));
    }

    /**
     * Subscribe a user to a list for this List Type
     *
     * @param Subscription $subscription
     *
     * @return bool
     */
    abstract public function add(Subscription $subscription): bool;

    /**
     * Unsubscribe a user from a list for this List Type
     *
     * @param Subscription $subscription
     *
     * @return bool
     */
    abstract public function remove(Subscription $subscription): bool;

    /**
     * @param ListElement $list
     *
     * @return mixed
     */
    abstract public function saveList(ListElement $list);

    /**
     * @param ListElement $list
     *
     * @return mixed
     */
    abstract public function deleteList(ListElement $list);

    /**
     * @param int $listId
     *
     * @return mixed
     */
    abstract public function getListById(int $listId);

    /**
     * Get subscribers on a given list.
     *
     * @param ListElement $list
     *
     * @return mixed
     * @internal param $criteria
     */
    abstract public function getItems(ListElement $list);

    /**
     * Check if a user is subscribed to a list
     *
     * @param Subscription $subscription
     *
     * @return bool
     */
    abstract public function hasItem(Subscription $subscription): bool;

    /**
     * @param ListElement $list
     *
     * @return mixed
     */
    abstract public function getCount(ListElement $list);

    /**
     * Runs on CP Panel controller to avoid incorrect values on checkbox values
     *
     * @param $subscriber
     *
     * @return null
     */
    public function cpBeforeSaveSubscriber($subscriber)
    {
        return null;
    }
}
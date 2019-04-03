<?php

namespace barrelstrength\sproutbaselists\base;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\models\Subscription;
use craft\base\Component;
use yii\base\Model;

/**
 *
 * @property mixed $className
 */
abstract class ListType extends Component
{
    /**
     * Set this value to true if a List Type should require an email address when processing a subscription.
     *
     * @var bool
     */
    public $requireEmailForSubscription = false;

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
     * Prepare the Subscription model for the `add` and `remove` methods
     *
     * @return Subscription
     */
    abstract public function populateSubscriptionFromPost(): Subscription;

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
     * @param Subscription $subscription
     */
    abstract public function getList(Subscription $subscription);

    /**
     * Prepare the ListElement for the `saveList` method
     *
     * @return ListElement
     */
    abstract public function populateListFromPost(): ListElement;

    /**
     * @param ListElement $list
     *
     * @return bool
     */
    abstract public function saveList(ListElement $list): bool;

    /**
     * @param ListElement $list
     *
     * @return bool
     */
    abstract public function deleteList(ListElement $list): bool;

    /**
     * @param Model $subscription
     *
     * @return Model|null
     */
    abstract public function getSubscriberOrItem($subscription);

    /**
     * Get all subscriptions for a given list.
     *
     * @param ListElement $list
     *
     * @return mixed
     * @internal param $criteria
     */
    abstract public function getSubscriptions(ListElement $list);

    /**
     * Prepare the Subscription model for the `isSubscribed` method.
     * The Subscription info is passed as `params` to the isSubscribed method.
     *
     * @example
     * {% if craft.sproutLists.isSubscribed(params) %} ... {% endif %}
     *
     * @param array $criteria
     *
     * @return Subscription
     */
    abstract public function populateSubscriptionFromIsSubscribedCriteria(array $criteria = []): Subscription;

    /**
     * Check if a user is subscribed to a list
     *
     * @param Subscription $subscription
     *
     * @return bool
     */
    abstract public function isSubscribed(Subscription $subscription): bool;

    /**
     * @param ListElement $list
     *
     * @return int
     */
    abstract public function getCount(ListElement $list): int;
}
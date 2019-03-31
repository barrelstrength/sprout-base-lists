<?php

namespace barrelstrength\sproutbaselists\base;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber;
use craft\base\Component;
use Craft;
use craft\base\Element;
use craft\helpers\StringHelper;

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
     * Prepare the ListElement for the `saveList` method
     *
     * @return ListElement
     * @throws \yii\web\BadRequestHttpException
     */
    public function populateListFromPost(): ListElement
    {
        $list = new ListElement();
        $list->type = get_class($this);
        $list->id = Craft::$app->getRequest()->getBodyParam('listId');
        $list->name = Craft::$app->request->getRequiredBodyParam('name');
        $list->handle = Craft::$app->request->getBodyParam('handle');

        if ($list->id) {
            /** @var Element $element */
            $element = Craft::$app->getElements()->getElementById($list->id);

            // Update where we store the Element ID if we don't have a Subscriber Element
            if (get_class($element) !== Subscriber::class) {
                $list->elementId = $element->id;
                $list->id = null;
            }
        }

        if ($list->handle === null) {
            $list->handle = StringHelper::toCamelCase($list->name);
        }

        return $list;
    }

    /**
     * Prepare the Subscription model for the `add` and `remove` methods.
     *
     * @return Subscription
     */
    public function populateSubscriptionFromPost(): Subscription
    {
        $subscription = new Subscription();
        $subscription->listType = get_class($this);
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId');
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('listHandle');
        $subscription->itemId = Craft::$app->getRequest()->getBodyParam('itemId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscription->firstName = Craft::$app->getRequest()->getBodyParam('firstName');
        $subscription->lastName = Craft::$app->getRequest()->getBodyParam('lastName');

        return $subscription;
    }

    /**
     * Prepare the Subscription model for the `isSubscribed` method
     *
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
    abstract public function isSubscribed(Subscription $subscription): bool;

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
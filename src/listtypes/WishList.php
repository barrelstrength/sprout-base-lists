<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListInterface;
use barrelstrength\sproutbaselists\base\ListTrait;
use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\base\SubscriptionInterface;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Subscription;
use Craft;
use craft\base\Element;
use craft\helpers\StringHelper;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 * @property string $handle
 */
class WishList extends ListType
{
    use ListTrait;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Wish List');
    }

    /**
     * Prepare the Subscription model for the `add` and `remove` methods
     *
     * @return SubscriptionInterface
     */
    public function populateSubscriptionFromPost(): SubscriptionInterface
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $currentUserId = $currentUser->id ?? null;

        $subscription = new Subscription();
        $subscription->listType = get_class($this);
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('list.id');
        $subscription->elementId = Craft::$app->getRequest()->getBodyParam('list.elementId', $currentUserId);
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('list.handle');
        $subscription->itemId = Craft::$app->getRequest()->getBodyParam('subscription.itemId');

        return $subscription;
    }

    /**
     * Prepare the ListElement for the `saveList` method
     *
     * @return ListElement
     * @throws \yii\web\BadRequestHttpException
     */
    public function populateListFromPost(): ListInterface
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $currentUserId = $currentUser->id ?? null;

        $list = new ListElement();
        $list->type = get_class($this);
        $list->id = Craft::$app->getRequest()->getBodyParam('listId');
        $list->elementId = Craft::$app->getRequest()->getBodyParam('elementId', $currentUserId);
        $list->name = Craft::$app->request->getRequiredBodyParam('name');
        $list->handle = Craft::$app->request->getBodyParam('handle');

        if ($list->handle === null) {
            $list->handle = StringHelper::toCamelCase($list->name);
        }

        return $list;
    }

    /**
     * @param SubscriptionInterface|Subscription $subscription
     *
     * @return Element|null
     */
    public function getSubscriberOrItem(SubscriptionInterface $subscription)
    {
        if (is_numeric($subscription->itemId)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscription->itemId);

            if ($element === null) {
                Craft::warning(Craft::t('sprout-base-lists', 'Unable to find an Element with ID: {id}', [
                    'id' => $subscription->listId
                ]), 'sprout-base-lists');

                return null;
            }

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $element;
        }

        return null;
    }
}

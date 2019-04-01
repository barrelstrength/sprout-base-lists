<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListTrait;
use barrelstrength\sproutbaselists\base\ListType;
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

        // @todo - does this work properly for new and edit scenarios?
        // @todo - Dynamically set USER? Craft::$app->getUser()->getIdentity()->id ?? null
//        if ($list->id) {
//            /** @var Element $element */
//            $element = Craft::$app->getElements()->getElementById($list->id);
//
//            // Update where we store the Element ID if we don't have a Subscriber Element
//            if (get_class($element) !== User::class || get_class($element) !== Subscriber::class) {
//                $list->elementId = $element->id;
//                $list->id = null;
//            }
//        }

        if ($list->handle === null) {
            $list->handle = StringHelper::toCamelCase($list->name);
        }

        return $list;
    }

    /**
     * @param Subscription $subscription
     *
     * @return Element|null
     */
    public function getSubscriberOrItem($subscription)
    {
        /**
         * See if we find:
         * 1. Subscriber Element with matching ID
         * 2. A User Element with matching ID
         * 3. Any Element with a matching ID
         * 4.
         */
        if (is_numeric($subscription->listId)) {
            /** @var Element $element */
            $element = Craft::$app->elements->getElementById($subscription->listId);

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

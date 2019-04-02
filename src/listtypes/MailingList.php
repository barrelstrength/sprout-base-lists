<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\ListTrait;
use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\base\SubscriberInterface;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber as SubscriberRecord;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use yii\base\Exception;

/**
 *
 * @property string $name
 * @property array  $listsWithSubscribers
 * @property string $handle
 */
class MailingList extends ListType implements SubscriberInterface
{
    use ListTrait;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Mailing List');
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
        if ($list->id) {
            /** @var Element $element */
            $element = Craft::$app->getElements()->getElementById($list->id);

            // Update where we store the Element ID if we don't have a Subscriber Element
            if (get_class($element) === ListElement::class) {
                $list->elementId = $element->id;
                $list->id = null;
            } else {
                $list->elementId = $element->id;
            }
        }

        if ($list->handle === null) {
            $list->handle = StringHelper::toCamelCase($list->name);
        }

        return $list;
    }

    /**
     * Get a Subscriber Element based on a subscription
     *
     * @param \yii\base\Model $subscription
     *
     * @return Subscriber|\yii\base\Model|null
     */
    public function getSubscriberOrItem($subscription)
    {
        /** @var Subscription $subscription */
        $subscriberId = $subscription->itemId;

        $query = Subscriber::find();

        if ($subscription->email) {
            $query->where([
                'sproutlists_subscribers.email' => $subscription->email
            ]);
        } else {
            $query->where([
                'sproutlists_subscribers.id' => $subscriberId
            ])
                ->orWhere([
                    'sproutlists_subscribers.userId' => $subscriberId
                ]);
        }

        /** @var Subscriber $subscriber */
        $subscriber = $query->one();

        // Only assign profile values when we add a Subscriber if we have values
        // Don't overwrite any profile attributes with empty values
        if (!empty($subscription->firstName)) {
            $subscriber->firstName = $subscription->firstName;
        }

        if (!empty($subscription->lastName)) {
            $subscriber->lastName = $subscription->lastName;
        }

        return $subscriber;
    }

    /**
     * @return Subscriber
     */
    public function populateSubscriberFromPost(): Subscriber
    {
        $subscriber = new Subscriber();

        $subscriberId = Craft::$app->getRequest()->getBodyParam('subscriberId');

        if ($subscriberId !== null) {
            $element = Craft::$app->getElements()->getElementById($subscriberId);

            if ($element) {
                $subscriber = $element;
            }
        }

        $subscriber->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscriber->firstName = Craft::$app->getRequest()->getBodyParam('firstName');
        $subscriber->lastName = Craft::$app->getRequest()->getBodyParam('lastName');
        $subscriber->listElements = Craft::$app->getRequest()->getBodyParam('mailingList.listElements');

        return $subscriber;
    }

    /**
     * Gets the HTML output for the lists sidebar on the Subscriber edit page.
     *
     * @param $subscriberId
     *
     * @return string|\Twig_Markup
     * @throws Exception
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     */
    public function getSubscriberSettingsHtml($subscriberId)
    {
        $subscriber = null;
        $listIds = [];

        if ($subscriberId !== null) {
            $subscription = new Subscription();
            $subscription->itemId = $subscriberId;

            $subscriber = $this->getSubscriberOrItem($subscription);

            if ($subscriber) {
                $listIds = $subscriber->getLists(true);
            }
        }

        /** @var ListElement[] $lists */
        $lists = ListElement::find()->where([
            'sproutlists_lists.type' => __CLASS__
        ])->all();

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

        $html = Craft::$app->getView()->renderTemplate('sprout-base-lists/subscribers/_mailinglists', [
            'options' => $options,
            'values' => $listIds
        ]);

        return Template::raw($html);
    }

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
        if (!$subscriber->validate()) {
            return false;
        }

        $subscriber = $this->updateSubscriberForUserSync($subscriber);

        if (Craft::$app->getElements()->saveElement($subscriber)) {
            $this->updateCount();
            return true;
        }

        return false;
    }

    /**
     * Deletes a subscriber.
     *
     * @param Subscriber $subscriber
     *
     * @return bool
     * @throws ElementNotFoundException
     * @throws \Throwable
     */
    public function deleteSubscriber(Subscriber $subscriber): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            Craft::$app->getElements()->deleteElementById($subscriber->id);

            // Clean up everything else that relates to this subscriber
            SubscriberRecord::deleteAll('id = :subscriberId', [
                ':subscriberId' => $subscriber->id
            ]);
            SubscriptionRecord::deleteAll('listId = :listId', [
                ':listId' => $subscriber->id
            ]);

            $this->updateCount();

            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new ElementNotFoundException(Craft::t('sprout-base-lists', 'Unable to delete Subscriber.'));
        }
    }

    /**
     * If enable user sync is on look for user element and assign it to userId column
     *
     * @param Subscriber $subscriber
     *
     * @return Subscriber $subscriber
     */
    public function updateSubscriberForUserSync(Subscriber $subscriber): Subscriber
    {
        if (!$this->settings->enableUserSync) {
            $subscriber->userId = null;
            return $subscriber;
        }

        $user = null;

        if ($subscriber->userId) {
            $user = Craft::$app->elements->getElementById($subscriber->userId, User::class);
        } elseif ($subscriber->email) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($subscriber->email);
        }

        $subscriber->userId = $user->id ?? null;

        // Assign First and Last name again and values from user profile as fallbacks
        $subscriber->firstName = $subscriber->firstName ?? $user->firstName ?? null;
        $subscriber->lastName = $subscriber->lastName ?? $user->lastName ?? null;

        return $subscriber;
    }
}

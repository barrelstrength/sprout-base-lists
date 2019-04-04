<?php

namespace barrelstrength\sproutbaselists\listtypes;

use barrelstrength\sproutbaselists\base\BaseSubscriberList;
use barrelstrength\sproutbaselists\base\ListInterface;
use barrelstrength\sproutbaselists\base\ListTrait;
use barrelstrength\sproutbaselists\base\SubscriberInterface;
use barrelstrength\sproutbaselists\base\SubscriptionInterface;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber as SubscriberRecord;
use barrelstrength\sproutbaselists\records\Subscription as SubscriptionRecord;
use Craft;
use craft\base\Element;
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
class MailingList extends BaseSubscriberList
{
    use ListTrait;

    /**
     * @var bool
     */
    public $requireEmailForSubscription = true;

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
     * @return ListInterface
     * @throws \yii\web\BadRequestHttpException
     */
    public function populateListFromPost(): ListInterface
    {
        $list = new ListElement();
        $list->type = get_class($this);
        $list->id = Craft::$app->getRequest()->getBodyParam('listId');
        $list->name = Craft::$app->request->getRequiredBodyParam('name');
        $list->handle = Craft::$app->request->getBodyParam('handle');

        if ($list->id) {
            /** @var Element $element */
            $element = Craft::$app->getElements()->getElementById($list->id);
            $list->elementId = $element->id;

            // If the listId is not a ListElement, set listId to null to create a new ListElement
            if (get_class($element) !== ListElement::class) {
                $list->id = null;
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
     * @param SubscriptionInterface $subscription
     *
     * @return SubscriptionInterface|Subscriber|null
     */
    public function getSubscriberOrItem(SubscriptionInterface $subscription)
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
     * @return SubscriberInterface
     */
    public function populateSubscriberFromPost(): SubscriberInterface
    {
        $subscriber = new Subscriber();
        $subscriber->id = Craft::$app->getRequest()->getBodyParam('subscriberId');
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
                $listIds = $subscriber->getLists()->column();
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
     * @param SubscriberInterface $subscriber
     *
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function saveSubscriber(SubscriberInterface $subscriber): bool
    {
        /** @var Subscriber $subscriber */
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
     * @param SubscriberInterface|Subscriber $subscriber
     *
     * @return bool
     * @throws ElementNotFoundException
     * @throws \Throwable
     */
    public function deleteSubscriber(SubscriberInterface $subscriber): bool
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
     * @param SubscriberInterface|Subscriber $subscriber
     *
     * @return SubscriberInterface $subscriber
     */
    public function updateSubscriberForUserSync(SubscriberInterface $subscriber): SubscriberInterface
    {
        if (!$this->settings->enableUserSync) {
            $subscriber->userId = null;
            return $subscriber;
        }

        $user = null;

        if ($subscriber->email) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($subscriber->email);
        }

        $subscriber->userId = $user->id ?? null;

        // Assign First and Last name again and values from user profile as fallbacks
        $subscriber->firstName = $subscriber->firstName ?? $user->firstName ?? null;
        $subscriber->lastName = $subscriber->lastName ?? $user->lastName ?? null;

        return $subscriber;
    }
}

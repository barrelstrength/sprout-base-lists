<?php

namespace barrelstrength\sproutbaselists\elements;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\actions\DeleteSubscriber;
use barrelstrength\sproutbaselists\elements\db\SubscriberQuery;
use barrelstrength\sproutbaselists\listtypes\MailingList;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\records\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber as SubscribersRecord;
use barrelstrength\sproutbaselists\SproutBaseLists;
use craft\base\Element;
use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use yii\db\Exception;

/**
 *
 * @property array $listIds
 * @property array $lists
 */
class Subscriber extends Element
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $userId;

    /**
     * @var int
     */
    public $itemId;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;

    /**
     * @var ListType
     */
    public $listType;

    /**
     * @var array
     */
    public $listElements;

    /**
     * @var array
     */
    private $listElementIds;

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->email;
    }

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'Sprout Subscriber');
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'sprout-lists/subscribers/edit/'.$this->id
        );
    }

    /**
     * @param string|null $context
     *
     * @return array
     * @throws \Exception
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('sprout-lists', 'All Subscribers')
            ]
        ];

        $listType = SproutBaseLists::$app->lists->getListType(MailingList::class);

        /**
         * @var ListType|MailingList $listType
         */
        $lists = $listType->getListsWithSubscribers();

        if (!empty($lists)) {
            $sources[] = [
                'heading' => $listType::displayName()
            ];

            foreach ($lists as $list) {
                $source = [
                    'key' => 'lists:'.$list->id,
                    'label' => $list->name,
                    'data' => ['handle' => $list->handle],
                    'criteria' => ['listId' => $list->id]
                ];

                $sources[] = $source;
            }
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'email' => ['label' => Craft::t('sprout-lists', 'Email')],
            'firstName' => ['label' => Craft::t('sprout-lists', 'First Name')],
            'lastName' => ['label' => Craft::t('sprout-lists', 'Last Name')],
            'dateCreated' => ['label' => Craft::t('sprout-lists', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('sprout-lists', 'Date Updated')]
        ];

        return $attributes;
    }

    /**
     * @return ElementQueryInterface
     */
    public static function find(): ElementQueryInterface
    {
        return new SubscriberQuery(static::class);
    }

    /**
     * @return \craft\models\FieldLayout|null
     */
    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(static::class);
    }

    /**
     * @return null|string
     */
    public function getUriFormat()
    {
        return 'sprout-lists/{slug}';
    }

    /**
     * @return null|string
     * @throws \yii\base\Exception
     */
    public function getUrl()
    {
        if ($this->uri !== null) {
            return UrlHelper::siteUrl($this->uri);
        }

        return null;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getListIds(): array
    {
        if ($this->listElementIds) {
            return $this->listElementIds;
        }

        $listElements = $this->getLists();

        if (!count($listElements)) {
            return [];
        }

        foreach ($listElements as $list) {
            $this->listElementIds[] = $list->id;
        }

        return $this->listElementIds;
    }

    /**
     * Gets an array of SproutLists_ListModels to which this subscriber is subscribed.
     *
     * @return array
     * @throws \Exception
     */
    public function getLists(): array
    {
        $lists = [];

        $subscriptions = Subscription::find()->where([
            'itemId' => $this->id
        ])->all();

        $listType = SproutBaseLists::$app->lists->getListType(MailingList::class);

        if (count($subscriptions)) {
            /** @var Subscription $subscription */
            foreach ($subscriptions as $subscription) {
                /**
                 * @todo - move query out of loop.
                 * @var $listType ListType
                 */
                $lists[] = $listType->getListById($subscription->listId);
            }
        }

        return $lists;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['email'], 'required'],
            [
                ['email'], UniqueValidator::class,
                'targetClass' => SubscribersRecord::class
            ],
            [['email'], 'email']
        ];
    }

    /**
     * @param bool $isNew
     *
     * @throws Exception
     */
    public function afterSave(bool $isNew)
    {
        /** @var Settings $settings */
        $settings = SproutBase::$app->settings->getPluginSettings('sprout-lists');

        if (!$settings) {
            parent::afterSave($isNew);
            return;
        }

        // Get the list record
        if (!$isNew) {
            $subscriberRecord = SubscribersRecord::findOne($this->id);

            if (!$subscriberRecord) {
                throw new Exception('Invalid Subscriber ID: '.$this->id);
            }
        } else {
            $subscriberRecord = new SubscribersRecord();
            $subscriberRecord->id = $this->id;
        }

        $user = null;

        // Sync updates with Craft User if User Sync enabled
        if ($this->email && $settings->enableUserSync) {

            // Get an existing matched User by Element ID if we have them
            if ($this->userId) {
                $user = Craft::$app->users->getUserById($this->userId);
            }

            if ($user === null) {
                // If we don't have a match, see if we can find a matching user by email
                $user = Craft::$app->users->getUserByUsernameOrEmail($this->email);

                // Set to null when updating a matched email
                $this->userId = null;
            }

            if ($user !== null) {
                $this->userId = $user->id;
            }
        }

        // Prepare our Subscriber Record
        $subscriberRecord->userId = $this->userId;
        $subscriberRecord->email = $this->email;
        $subscriberRecord->firstName = $this->firstName;
        $subscriberRecord->lastName = $this->lastName;

        $result = $subscriberRecord->save(false);

        if ($result &&
            $isNew === false &&
            $user !== null &&
            $settings->enableUserSync) {
            // Sync updates with existing Craft User if User Sync enabled
            Craft::$app->getDb()->createCommand()->update(
                '{{%users}}',
                [
                    'email' => $subscriberRecord->email ?? $user->email,
                    'firstName' => $subscriberRecord->firstName ?? $user->firstName,
                    'lastName' => $subscriberRecord->lastName ?? $user->lastName
                ],
                ['id' => $subscriberRecord->userId],
                [],
                false
            )
                ->execute();
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = DeleteSubscriber::class;

        return $actions;
    }
}
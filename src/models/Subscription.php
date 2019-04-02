<?php

namespace barrelstrength\sproutbaselists\models;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\records\Subscriber as SubscriberRecord;
use craft\base\Model;
use DateTime;
use Craft;
use craft\validators\UniqueValidator;

class Subscription extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var ListType
     */
    public $listType;

    /**
     * @var string
     */
    public $listHandle;

    /**
     * @var
     */
    public $listId;

    /**
     * @var
     */
    public $elementId;

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
     * @var DateTime|null
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     */
    public $dateUpdated;

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $listId     = trim($this->listId);
        $listHandle = trim($this->listHandle);

        if (empty($listId) && empty($listHandle)) {
            $this->addError('listHandle', Craft::t('sprout-base-lists',
                'List ID or List Handle is required.'));
        }

        $rules[] = [['email'], 'email'];
        $rules[] = [
            ['email'], UniqueValidator::class,
            'targetClass' => SubscriberRecord::class
        ];

        return $rules;
    }
}

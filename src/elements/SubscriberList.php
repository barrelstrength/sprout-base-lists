<?php

namespace barrelstrength\sproutbaselists\elements;

use barrelstrength\sproutbaselists\elements\actions\DeleteList;
use barrelstrength\sproutbaselists\elements\db\SubscriberListQuery;
use craft\base\Element;
use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\errors\ElementNotFoundException;
use craft\helpers\UrlHelper;
use barrelstrength\sproutbaselists\records\SubscriberList as ListsRecord;
use yii\web\ErrorHandler;

class SubscriberList extends Element
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $elementId;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $handle;

    /**
     * @var int
     */
    public $totalSubscribers;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-lists', 'List');
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
            'sprout-lists/lists/edit/'.$this->id
        );
    }

    /**
     * @return ElementQueryInterface
     */
    public static function find(): ElementQueryInterface
    {
        return new SubscriberListQuery(static::class);
    }

    /**
     * @param string|null $context
     *
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('sprout-lists', 'All lists')
            ]
        ];

        return $sources;
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return (string)$this->name;
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'name' => ['label' => Craft::t('sprout-lists', 'Name')],
            'handle' => ['label' => Craft::t('sprout-lists', 'List Handle')],
            'view' => ['label' => Craft::t('sprout-lists', 'View Subscriber')],
            'totalSubscribers' => ['label' => Craft::t('sprout-lists', 'Total Subscriber')],
            'dateCreated' => ['label' => Craft::t('sprout-lists', 'Date Created')]
        ];

        return $attributes;
    }

    /**
     * @param string $attribute
     *
     * @return string
     */
    public function getTableAttributeHtml(string $attribute): string
    {
        $totalSubscribers = $this->totalSubscribers;

        switch ($attribute) {
            case 'handle':

                return '<code>'.$this->handle.'</code>';

                break;

            case 'view':

                if ($this->id && $totalSubscribers > 0) {
                    return '<a href="'.UrlHelper::cpUrl('sprout-lists/subscribers/'.$this->handle).'" class="go">'.
                        Craft::t('sprout-lists', 'View Subscriber').'</a>';
                }
                return '';
                break;
        }

        return parent::getTableAttributeHtml($attribute);
    }

    /**
     * @return \craft\models\FieldLayout|null
     */
    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(static::class);
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['name', 'handle'], 'required'];

        return $rules;
    }

    /**
     * @param bool $isNew
     *
     * @throws \Exception
     */
    public function afterSave(bool $isNew)
    {
        // Get the list record
        if (!$isNew) {
            $record = ListsRecord::findOne($this->id);

            if (!$record) {
                throw new ElementNotFoundException('Invalid list ID: '.$this->id);
            }

            $record->elementId = $this->elementId;
        } else {
            $record = new ListsRecord();
            $record->id = $this->id;

            // Fallback and assign the current listId if no elementId is provided
            $record->elementId = $this->elementId ?? $this->id;
        }

        $record->type = $this->type;
        $record->name = $this->name;
        $record->handle = $this->handle;

        $record->save(false);

        // Update the entry's descendants, who may be using this entry's URI in their own URIs
        Craft::$app->getElements()->updateElementSlugAndUri($this);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = DeleteList::class;

        return $actions;
    }
}
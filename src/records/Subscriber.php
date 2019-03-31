<?php

namespace barrelstrength\sproutbaselists\records;

use barrelstrength\sproutbaselists\elements\ListElement;
use craft\base\Element;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Class Subscriber record.
 *
 * @property int                          $id
 * @property int                          $userId
 * @property string                       $email
 * @property string                       $firstName
 * @property string                       $lastName
 * @property \yii\db\ActiveQueryInterface $element
 * @property \yii\db\ActiveQueryInterface $lists
 */
class Subscriber extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%sproutlists_subscribers}}';
    }

    /**
     * Returns the entry’s element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function getLists(): ActiveQueryInterface
    {
        return $this->hasMany(ListElement::class, ['id' => 'listId'])
            ->viaTable('{{%sproutlists_subscriptions}}', ['listId' => 'id']);
    }

    public function rules(): array
    {
        return [
            [['email'], 'unique']
        ];
    }
}

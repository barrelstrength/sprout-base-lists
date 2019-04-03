<?php

namespace barrelstrength\sproutbaselists\records;

use craft\base\Element;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;
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
     * Gets an array of SproutLists_ListModels to which this subscriber is subscribed.
     *
     * @return ActiveQuery
     * @throws \yii\base\InvalidConfigException
     */
    public function getLists(): ActiveQuery
    {
        return $this->hasMany(ListElement::class, ['id' => 'listId'])
            ->viaTable('{{%sproutlists_subscriptions}}', ['itemId' => 'id']);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['email'], 'unique']
        ];
    }
}

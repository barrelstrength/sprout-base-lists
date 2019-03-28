<?php

namespace barrelstrength\sproutbaselists\records;

use craft\base\Element;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Class ListElement record.
 *
 * @property int                          $id
 * @property int                          $elementId
 * @property string                       $type
 * @property string                       $name
 * @property string                       $handle
 * @property \yii\db\ActiveQueryInterface $element
 * @property \yii\db\ActiveQueryInterface $subscribers
 * @property \yii\db\ActiveQueryInterface $listsWithSubscribers
 * @property int                          $count
 */
class ListElement extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%sproutlists_lists}}';
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
     * @todo - move this off the ListElement record. Subscribers are a separate concept.
     *
     * @return ActiveQueryInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function getListsWithSubscribers(): ActiveQueryInterface
    {
        return $this->hasMany(Subscriber::class, ['id' => 'itemId'])
            ->viaTable('{{%sproutlists_subscriptions}}', ['listId' => 'id']);
    }
}

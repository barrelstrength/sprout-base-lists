<?php

namespace barrelstrength\sproutbaselists\records;

use craft\db\ActiveRecord;

/**
 * Class Subscription record.
 *
 * @property int $id
 * @property int $listId
 * @property int $itemId
 */
class Subscription extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%sproutlists_subscriptions}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['listId', 'itemId'], 'unique', 'targetAttribute' => ['listId', 'itemId']]
        ];
    }
}

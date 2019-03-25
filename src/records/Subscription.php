<?php

namespace barrelstrength\sproutbaselists\records;

use craft\db\ActiveRecord;

/**
 * Class Subscription record.
 *
 * @property int $id
 * @property int $listId
 * @property int $subscriberId
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
}

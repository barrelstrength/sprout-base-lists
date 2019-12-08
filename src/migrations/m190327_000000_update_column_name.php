<?php

namespace barrelstrength\sproutbaselists\migrations;

use craft\db\Migration;
use yii\base\NotSupportedException;

/**
 * Class m190327_000000_update_subscription_column_name
 *
 * @package barrelstrength\sproutlists\migrations
 */
class m190327_000000_update_column_name extends Migration
{
    /**
     * @return bool
     * @throws NotSupportedException
     */
    public function safeUp(): bool
    {
        $table = '{{%sproutlists_subscriptions}}';

        if (!$this->db->columnExists($table, 'itemId')) {
            $this->renameColumn($table, 'subscriberId', 'itemId');
        }

        $listTable = '{{%sproutlists_lists}}';

        if ($this->db->columnExists($listTable, 'totalSubscribers')) {
            $this->renameColumn($listTable, 'totalSubscribers', 'count');
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190327_000000_update_subscription_column_name cannot be reverted.\n";
        return false;
    }
}

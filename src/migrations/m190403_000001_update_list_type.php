<?php /** @noinspection ClassConstantCanBeUsedInspection */

namespace barrelstrength\sproutbaselists\migrations;

use craft\db\Migration;

/**
 * Class m190327_000001_update_list_type
 *
 * @package barrelstrength\sproutbaselists\migrations
 */
class m190403_000001_update_list_type extends Migration
{
    /**
     * @return bool
     */
    public function safeUp(): bool
    {
        $listClasses = [
            0 => [
                'oldType' => 'barrelstrength\sproutlists\listtypes\SubscriberListType',
                'newType' => 'barrelstrength\sproutbaselists\listtypes\MailingList'
            ]
        ];

        foreach ($listClasses as $class) {
            $this->update('{{%sproutlists_lists}}', [
                'type' => $class['newType']
            ], ['type' => $class['oldType']], [], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190327_000001_update_list_type cannot be reverted.\n";
        return false;
    }
}

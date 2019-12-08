<?php /** @noinspection ClassConstantCanBeUsedInspection */

namespace barrelstrength\sproutbaselists\migrations;

use craft\db\Migration;

/**
 * Class m190327_000001_update_element_type
 *
 * @package barrelstrength\sproutbaselists\migrations
 */
class m190327_000001_update_element_type extends Migration
{
    /**
     * @return bool
     */
    public function safeUp(): bool
    {
        $subscriberClasses = [
            0 => [
                'oldType' => 'barrelstrength\sproutlists\elements\Subscriber',
                'newType' => 'barrelstrength\sproutbaselists\elements\Subscriber'
            ]
        ];

        foreach ($subscriberClasses as $subscriberClass) {
            $this->update('{{%elements}}', [
                'type' => $subscriberClass['newType']
            ], ['type' => $subscriberClass['oldType']], [], false);
        }

        $listClasses = [
            0 => [
                'oldType' => 'barrelstrength\sproutlists\elements\SubscriberList',
                'newType' => 'barrelstrength\sproutbaselists\elements\ListElement'
            ]
        ];

        foreach ($listClasses as $listClass) {
            $this->update('{{%elements}}', [
                'type' => $listClass['newType']
            ], ['type' => $listClass['oldType']], [], false);
        }

        $mailingClasses = [
            0 => [
                'oldType' => 'barrelstrength\sproutlists\listtypes\SubscriberListType',
                'newType' => 'barrelstrength\sproutbaselists\listtypes\SubscriberList'
            ]
        ];

        foreach ($mailingClasses as $mailingClass) {
            $this->update('{{%sproutlists_lists}}', [
                'type' => $mailingClass['newType']
            ], ['type' => $mailingClass['oldType']], [], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190327_000001_update_element_type cannot be reverted.\n";
        return false;
    }
}

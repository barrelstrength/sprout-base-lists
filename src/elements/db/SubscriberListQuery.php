<?php

namespace barrelstrength\sproutbaselists\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class SubscriberListQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('sproutlists_lists');
        $this->query->select([
            'sproutlists_lists.elementId',
            'sproutlists_lists.type',
            'sproutlists_lists.name',
            'sproutlists_lists.handle',
            'sproutlists_lists.totalSubscribers'
        ]);

        return parent::beforePrepare();
    }
}
<?php

namespace barrelstrength\sproutbaselists\elements\db;

use craft\elements\db\ElementQuery;

class ListElementQuery extends ElementQuery
{
    public $type;

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
            'sproutlists_lists.count'
        ]);

        if ($this->type) {
            $listClass = new $this->type();
            $this->subQuery->andWhere(['sproutlists_lists.type' => get_class($listClass)]);
        }

        return parent::beforePrepare();
    }
}
<?php

namespace barrelstrength\sproutbaselists\web\twig\variables;

use barrelstrength\sproutbaselists\elements\db\ListElementQuery;
use barrelstrength\sproutbaselists\elements\db\SubscriberQuery;
use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\elements\Subscriber;
use Craft;

class SproutListsVariable
{
    /**
     * @param array $criteria
     *
     * @return ListElementQuery
     */
    public function lists(array $criteria = []): ListElementQuery
    {
        $query = ListElement::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    /**
     * @param array $criteria
     *
     * @return SubscriberQuery
     */
    public function subscribers(array $criteria = []): SubscriberQuery
    {
        $query = Subscriber::find();
        Craft::configure($query, $criteria);

        return $query;
    }
}

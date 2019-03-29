<?php

namespace barrelstrength\sproutbaselists\models;

use barrelstrength\sproutbaselists\base\ListType;
use craft\base\Model;
use DateTime;

class Subscription extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var ListType
     */
    public $listType;

    /**
     * @var string
     */
    public $listHandle;

    public $listId;

    /**
     * @var int
     */
    public $itemId;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;

    /**
     * @var DateTime|null
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     */
    public $dateUpdated;
}

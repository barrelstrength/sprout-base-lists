<?php

namespace barrelstrength\sproutbaselists\events;

use yii\base\Event;

class RegisterListTypesEvent extends Event
{
    /**
     * @var array
     */
    public $listTypes;
}
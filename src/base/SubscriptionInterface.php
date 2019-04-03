<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaselists\base;

interface SubscriptionInterface
{
    const SCENARIO_SUBSCRIBER = 'subscriber';

    /**
     * @return ListType
     */
    public function getListType(): ListType;
}
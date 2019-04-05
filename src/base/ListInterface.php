<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaselists\base;

interface ListInterface
{
    const SCENARIO_LIST = 'list';
    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return ListType
     */
    public function getType(): ListType;
}
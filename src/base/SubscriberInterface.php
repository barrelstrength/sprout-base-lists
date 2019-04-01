<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaselists\base;

use barrelstrength\sproutbaselists\elements\Subscriber;

interface SubscriberInterface
{
    /**
     * Prepare Subscriber for the `saveSubscriber` method
     *
     * @return Subscriber
     */
    public function populateSubscriberFromPost(): Subscriber;

    /**
     * @param $subscriberId
     *
     * @return mixed
     */
    public function getSubscriberSettingsHtml($subscriberId);

    /**
     * @param Subscriber $subscriber
     *
     * @return bool
     */
    public function saveSubscriber(Subscriber $subscriber): bool;

    /**
     * @param Subscriber $subscriber
     *
     * @return bool
     */
    public function deleteSubscriber(Subscriber $subscriber): bool;
}
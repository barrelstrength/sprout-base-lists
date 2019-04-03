<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaselists\base;

abstract class BaseSubscriberList extends ListType
{
    /**
     * Prepare Subscriber for the `saveSubscriber` method
     *
     * @return SubscriberInterface
     */
    abstract public function populateSubscriberFromPost(): SubscriberInterface;

    /**
     * @todo - review if this works in the abstract sense
     *
     * @param $subscriberId
     *
     * @return mixed
     */
    abstract public function getSubscriberSettingsHtml($subscriberId);

    /**
     * @param SubscriberInterface $subscriber
     *
     * @return bool
     */
    abstract public function saveSubscriber(SubscriberInterface $subscriber): bool;

    /**
     * @param SubscriberInterface $subscriber
     *
     * @return bool
     */
    abstract public function deleteSubscriber(SubscriberInterface $subscriber): bool;
}
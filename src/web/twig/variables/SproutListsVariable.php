<?php

namespace barrelstrength\sproutbaselists\web\twig\variables;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\SubscriberList;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\listtypes\MailingList;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\SproutBaseLists;
use Craft;

class SproutListsVariable
{
    /**
     * Checks if a user is subscribed to a given list.
     *
     * @param $criteria
     *
     * @return mixed
     * @throws \Exception
     */
    public function getIsSubscribed(array $criteria = [])
    {
        $subscription = new Subscription();
        $subscription->listType = $criteria['listType'] ?? MailingList::class;
//        $subscription->listHandle = $criteria['listHandle'] ?? null;
        $subscription->listId = $criteria['listId'] ?? null;
        $subscription->elementId = $criteria['elementId'] ?? null;
//        $subscription->userId = $criteria['userId'] ?? null;
        $subscription->email = $criteria['email'] ?? null;
//        $subscription->subscriberId = $criteria['subscriberId'] ?? null;

        $listType = SproutBaseLists::$app->lists->getListType($subscription->listType);

        return $listType->isSubscribed($subscription);
    }

    /**
     * Returns all lists for a given subscriber.
     *
     * @param array $criteria
     *
     * @return mixed
     */
    /**
     * @param array $criteria
     *
     * @return mixed
     * @throws \Exception
     */
    public function getLists(array $criteria = [])
    {
        $subscriber = new Subscriber();
        $listType = $criteria['listType'] ?? MailingList::class;
        $subscriber->listType = $listType;
        $subscriber->email = $criteria['email'] ?? null;
        $subscriber->userId = $criteria['userId'] ?? null;
        $subscriber->firstName = $criteria['firstName'] ?? null;
        $subscriber->lastName = $criteria['lastName'] ?? null;

        /**
         * @var $listTypeObject ListType
         */
        $listTypeObject = new $listType;

        return $listTypeObject->getLists($subscriber);
    }

    /**
     * Return all subscribers on a given list.
     *
     * @param array $criteria
     *
     * @return mixed
     * @throws \Exception
     */
    public function getSubscribers(array $criteria = [])
    {
        if (!isset($criteria['listId'])) {
            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'The `listId` parameter is required.'));
        }

        $list = new SubscriberList();
        $list->id = $criteria['listId'] ?? null;

        $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);
        $list->type = get_class($listType);

        return $listType->getSubscribers($list);
    }

    // Counts
    // =========================================================================

    /**
     * Return total subscriptions for a given subscriber.
     *
     * @param array $criteria
     *
     * @return mixed
     * @throws \Exception
     */
    public function getListCount(array $criteria = [])
    {
        $subscriber = new Subscriber();
        $subscriber->listType = $criteria['listType'] ?? MailingList::class;
        $subscriber->email = $criteria['email'] ?? null;
        $subscriber->userId = $criteria['userId'] ?? null;
        $subscriber->firstName = $criteria['firstName'] ?? null;
        $subscriber->lastName = $criteria['lastName'] ?? null;

        $listType = SproutBaseLists::$app->lists->getListType($subscriber->listType);

        return $listType->getListCount($subscriber);
    }

    /**
     * Return total subscriber count on a given list.
     *
     * @param $criteria
     *
     * @return mixed
     * @throws \Exception
     */
    public function getSubscriberCount(array $criteria = [])
    {
        $list = new SubscriberList();
        $list->id = $criteria['listId'] ?? null;

        $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);
        $list->type = get_class($listType);

        return $listType->getSubscriberCount($list);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        $routeParams = Craft::$app->getUrlManager()->getRouteParams();

        $errors = [];

        if (isset($routeParams['subscription'])) {
            /**
             * @var $subscription Subscription
             */
            $subscription = $routeParams['subscription'];
            $subscriptionErrors = $subscription->getErrors();
            $errors = $this->flattenArray($subscriptionErrors);
        }

        return $errors;
    }

    /**
     * Convert multidimensional array to single array
     *
     * @param array $array
     *
     * @return array
     */
    private function flattenArray(array $array): array
    {
        $return = [];
        array_walk_recursive($array, function($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }
}

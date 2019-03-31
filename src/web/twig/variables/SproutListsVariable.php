<?php

namespace barrelstrength\sproutbaselists\web\twig\variables;

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
    public function isSubscribed(array $criteria = [])
    {
        $listType = $criteria['listType'] ?? null;
        $listType = SproutBaseLists::$app->lists->getListType($listType);

        $subscription = $listType->populateSubscriptionFromIsSubscribedCriteria($criteria);

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
//    public function getLists(array $criteria = [])
//    {
//        $subscriber = new Subscriber();
//        $subscriber->id = $criteria['subscriberId'] ?? null;
//        $subscriber->email = $criteria['email'] ?? null;
//        $subscriber->firstName = $criteria['firstName'] ?? null;
//        $subscriber->lastName = $criteria['lastName'] ?? null;
//
//        $listType = $criteria['listType'] ?? MailingList::class;
//        $subscriber->listType = $listType;
//
//        /**
//         * @var $listTypeObject ListType
//         */
//        $listTypeObject = new $listType;
//
//        return $listTypeObject->getLists($subscriber);
//    }

    /**
     * Return all subscribers on a given list.
     *
     * @param array $criteria
     *
     * @return mixed
     * @throws \Exception
     */
//    public function getSubscribers(array $criteria = [])
//    {
//        if (!isset($criteria['listId'])) {
//            throw new \InvalidArgumentException(Craft::t('sprout-lists', 'The `listId` parameter is required.'));
//        }
//
//        $list = new ListElement();
//        $list->id = $criteria['listId'] ?? null;
//
//        $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);
//        $list->type = get_class($listType);
//
//        return $listType->getItems($list);
//    }

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
//    public function getListCount(array $criteria = [])
//    {
//        $subscriber = new Subscriber();
//        $subscriber->listType = $criteria['listType'] ?? MailingList::class;
//        $subscriber->email = $criteria['email'] ?? null;
//        $subscriber->userId = $criteria['userId'] ?? null;
//        $subscriber->firstName = $criteria['firstName'] ?? null;
//        $subscriber->lastName = $criteria['lastName'] ?? null;
//
//        $listType = SproutBaseLists::$app->lists->getListType($subscriber->listType);
//
//        return $listType->getListCount($subscriber);
//    }

    /**
     * Return total subscriber count on a given list.
     *
     * @param $criteria
     *
     * @return mixed
     * @throws \Exception
     */
//    public function getSubscriberCount(array $criteria = [])
//    {
//        $list = new ListElement();
//        $list->id = $criteria['listId'] ?? null;
//
//        $listType = SproutBaseLists::$app->lists->getListTypeById($list->id);
//        $list->type = get_class($listType);
//
//        return $listType->getItemCount($list);
//    }

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

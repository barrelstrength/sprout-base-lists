<?php

namespace barrelstrength\sproutbaselists\controllers;

use barrelstrength\sproutbaselists\base\BaseSubscriberList;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\listtypes\MailingList;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\SproutBaseLists;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use Craft;
use Exception;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SubscribersController extends Controller
{
    public $subscriberBaseUrl;

    public function init()
    {
        $segmentOne = Craft::$app->getRequest()->getSegment(1);
        $segmentTwo = Craft::$app->getRequest()->getSegment(2);

        $this->subscriberBaseUrl = $segmentOne.'/'.$segmentTwo.'/';

        parent::init();
    }

    /**
     * @return Response
     */
    public function actionSubscribersIndexTemplate(): Response
    {
        return $this->renderTemplate('sprout-base-lists/subscribers/index', [
            'subscriberBaseUrl' => $this->subscriberBaseUrl
        ]);
    }

    /**
     * Prepare variables for Subscriber Edit Template
     *
     * @param null $id
     * @param null $subscriber
     *
     * @return Response
     * @throws Exception
     * @throws Throwable
     */
    public function actionEditSubscriberTemplate($id = null, $subscriber = null): Response
    {
        $this->requirePermission('sproutLists-editSubscribers');

        /**  @var MailingList $listType */
        $listType = SproutBaseLists::$app->lists->getListType(MailingList::class);
        $listTypes[] = $listType;

        if ($id !== null && $subscriber === null) {
            $subscription = new Subscription();
            $subscription->itemId = $id;

            $subscriber = $listType->getSubscriberOrItem($subscription);
        }

        return $this->renderTemplate('sprout-base-lists/subscribers/_edit', [
            'subscriber' => $subscriber,
            'listTypes' => $listTypes
        ]);
    }

    /**
     * Saves a subscriber
     *
     * @return Response|null
     * @throws MissingComponentException
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function actionSaveSubscriber()
    {
        $this->requirePostRequest();
        $this->requirePermission('sproutLists-editSubscribers');

        /** @var BaseSubscriberList $listType */
        $listType = Craft::$app->getRequest()->getBodyParam('listType');
        $listType = SproutBaseLists::$app->lists->getListType($listType);

        $subscriber = $listType->populateSubscriberFromPost();

        if (!$listType->saveSubscriber($subscriber)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to save subscriber.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'subscriber' => $subscriber
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'Subscriber saved.'));

        return $this->redirectToPostedUrl($subscriber);
    }

    /**
     * Deletes a subscriber
     *
     * @return Response
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionDeleteSubscriber(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('sproutLists-editSubscribers');

        $subscriber = new Subscriber();
        $subscriber->listType = Craft::$app->getRequest()->getRequiredBodyParam('listType');
        $subscriber->id = Craft::$app->getRequest()->getBodyParam('subscriberId');

        /** @var BaseSubscriberList $listType */
        $listType = SproutBaseLists::$app->lists->getListType($subscriber->listType);

        if (!$listType->deleteSubscriber($subscriber)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to delete subscriber.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'subscriber' => $subscriber
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'Subscriber deleted.'));

        return $this->redirectToPostedUrl();
    }
}
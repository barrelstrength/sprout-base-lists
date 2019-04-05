<?php

namespace barrelstrength\sproutbaselists\controllers;

use barrelstrength\sproutbaselists\elements\ListElement;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\SproutBaseLists;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Craft;
use yii\web\Response;

class ListsController extends Controller
{
    /**
     * Allow users who are not logged in to subscribe and unsubscribe from lists
     *
     * @var array
     */
    protected $allowAnonymous = [
        'actionAdd',
        'actionRemove'
    ];

    /**
     * @param string $pluginHandle
     *
     * @return Response
     */
    public function actionListsIndexTemplate(string $pluginHandle): Response
    {
        return $this->renderTemplate('sprout-base-lists/lists/index', [
            'pluginHandle' => $pluginHandle
        ]);
    }

    /**
     * Prepare variables for the List Edit Template
     *
     * @param string $pluginHandle
     * @param null   $listId
     * @param null   $list
     *
     * @return Response
     */
    public function actionListEditTemplate(string $pluginHandle, $listId = null, $list = null): Response
    {
        $continueEditingUrl = null;

        if (!$list) {
            if ($listId !== null) {
                /** @var ListElement $list */
                $list = Craft::$app->elements->getElementById($listId, ListElement::class);
                $continueEditingUrl = 'sprout-lists/lists/edit/'.$list->id;
            } else {
                $list = new ListElement();
            }
        }

        $redirectUrl = UrlHelper::cpUrl($pluginHandle.'/lists');

        return $this->renderTemplate('sprout-base-lists/lists/_edit', [
            'list' => $list,
            'redirectUrl' => $redirectUrl,
            'continueEditingUrl' => $continueEditingUrl
        ]);
    }

    /**
     * Saves a list
     *
     * @return null
     * @throws \Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveList()
    {
        $this->requirePostRequest();

        $listType = Craft::$app->getRequest()->getBodyParam('listType');
        $listType = SproutBaseLists::$app->lists->getListType($listType);

        $list = $listType->populateListFromPost();

        if (!$listType->saveList($list)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to save list.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'list' => $list
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'List saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a list
     *
     * @return Response
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteList(): Response
    {
        $this->requirePostRequest();

        $list = new ListElement();
        $list->type = Craft::$app->getRequest()->getRequiredBodyParam('listType');
        $list->id = Craft::$app->getRequest()->getRequiredBodyParam('listId');

        $listType = SproutBaseLists::$app->lists->getListType($list->type);

        if (!$listType->deleteList($list)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => false
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to delete list.'));

            return $this->redirectToPostedUrl();
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'success' => true
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'List deleted.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Adds a subscriber to a list
     *
     * @return Response | null
     * @throws \Throwable
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionAdd()
    {
        $this->requirePostRequest();

        $listType = Craft::$app->getRequest()->getBodyParam('listType');
        $listType = SproutBaseLists::$app->lists->getListType($listType);

        /** @var Subscription $subscription */
        $subscription = $listType->populateSubscriptionFromPost();

        if (!$listType->add($subscription)) {

            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $subscription->getErrors()
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'subscriptions' => $subscription
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'success' => true
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Removes a subscriber from a list
     *
     * @return Response|null
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRemove()
    {
        $this->requirePostRequest();

        $listType = Craft::$app->getRequest()->getBodyParam('listType');
        $listType = SproutBaseLists::$app->lists->getListType($listType);

        /** @var Subscription $subscription */
        $subscription = $listType->populateSubscriptionFromPost();

        if (!$listType->remove($subscription)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $subscription->getErrors()
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'subscription' => $subscription
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'success' => true
            ]);
        }

        return $this->redirectToPostedUrl();
    }
}
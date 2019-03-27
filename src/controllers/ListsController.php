<?php

namespace barrelstrength\sproutbaselists\controllers;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\SubscriberList;
use barrelstrength\sproutbaselists\listtypes\MailingList;
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
     * @param null   $type
     * @param null   $listId
     * @param null   $list
     *
     * @return Response
     * @throws \yii\base\Exception
     */
    public function actionListEditTemplate(string $pluginHandle, $type = null, $listId = null, $list = null): Response
    {
        $type = $type ?? MailingList::class;

        $listType = SproutBaseLists::$app->lists->getListType($type);

        $continueEditingUrl = null;

        if (!$list) {
            if ($listId == null) {
                $list = new SubscriberList();
            } else {
                /**
                 * @var $listType ListType
                 */
                $list = $listType->getListById($listId);

                $continueEditingUrl = 'sprout-lists/lists/edit/'.$listId;
            }
        }

        $redirectUrl = UrlHelper::cpUrl($pluginHandle.'/lists');

        return $this->renderTemplate('sprout-base-lists/lists/_edit', [
            'listId' => $listId,
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

        $listId = Craft::$app->request->getBodyParam('listId');

        $list = new SubscriberList();

        if ($listId !== null && $listId !== '') {
            $list = Craft::$app->getElements()->getElementById($listId);
        }

        $list->name = Craft::$app->request->getBodyParam('name');
        $list->handle = Craft::$app->request->getBodyParam('handle');
        $list->type = Craft::$app->request->getBodyParam('type');

        /**
         * @var $listType ListType
         */
        $listType = SproutBaseLists::$app->lists->getListType($list->type);

        if ($listType->saveList($list)) {
            Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'List saved.'));

            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to save list.'));

        Craft::$app->getUrlManager()->setRouteParams([
            'list' => $list
        ]);

        return null;
    }

    /**
     * Deletes a list
     *
     * @return Response
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteList(): Response
    {
        $this->requirePostRequest();

        $listId = Craft::$app->getRequest()->getBodyParam('listId');

        if (SproutBaseLists::$app->lists->deleteList($listId)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => true
                ]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('sprout-lists', 'List deleted.'));

            return $this->redirectToPostedUrl();
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'success' => false
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('sprout-lists', 'Unable to delete list.'));

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

        $subscription = new Subscription();
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId', Craft::$app->getUser()->getIdentity()->id);
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('listHandle');
        $subscription->itemId = Craft::$app->getRequest()->getBodyParam('itemId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscription->firstName = Craft::$app->getRequest()->getBodyParam('firstName');
        $subscription->lastName = Craft::$app->getRequest()->getBodyParam('lastName');
        $listTypeClass = Craft::$app->getRequest()->getBodyParam('listType', MailingList::class);

        /** @var ListType $listType */
        $listType = new $listTypeClass();

        if (!$listType->add($subscription)) {
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

    /**
     * Removes a subscriber from a list
     *
     * @return Response|null
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRemove()
    {
        $this->requirePostRequest();

        $subscription = new Subscription();
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId');
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('listHandle');
        $subscription->itemId = Craft::$app->getRequest()->getBodyParam('itemId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $listTypeClass = Craft::$app->getRequest()->getBodyParam('listType', MailingList::class);

        /** @var ListType $listType */
        $listType = new $listTypeClass();

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
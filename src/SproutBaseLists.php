<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaselists;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbaselists\controllers\ListsController;
use barrelstrength\sproutbaselists\controllers\SubscribersController;
use barrelstrength\sproutbaselists\events\RegisterListTypesEvent;
use barrelstrength\sproutbaselists\listtypes\SubscriberList;
use barrelstrength\sproutbaselists\services\App;
use barrelstrength\sproutbaselists\services\Lists;
use barrelstrength\sproutbaselists\web\twig\extensions\TwigExtensions;
use barrelstrength\sproutbaselists\web\twig\variables\SproutListsVariable;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use \yii\base\Module;
use craft\web\View;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\ArrayHelper;
use craft\i18n\PhpMessageSource;
use Craft;
use craft\elements\User;

class SproutBaseLists extends Module
{
    use BaseSproutTrait;

    /**
     * Enable use of SproutBaseLists::$app-> in place of Craft::$app->
     *
     * @var App
     */
    public static $app;

    /**
     * @var string
     */
    public $handle;

    /**
     * Identify our plugin for BaseSproutTrait
     *
     * @var string
     */
    public static $pluginHandle = 'sprout-base-lists';

    /**
     * @var string|null The translation category that this module translation messages should use. Defaults to the lowercase plugin handle.
     */
    public $t9nCategory;

    /**
     * @var string The language that the module messages were written in
     */
    public $sourceLanguage = 'en-US';

    /**
     * @inheritdoc
     */
    public function __construct($id, $parent = null, array $config = [])
    {
        // Set some things early in case there are any settings, and the settings model's
        // init() method needs to call Craft::t() or Plugin::getInstance().

        $this->handle = 'sprout-base-lists';
        $this->t9nCategory = ArrayHelper::remove($config, 't9nCategory', $this->t9nCategory ?? strtolower($this->handle));
        $this->sourceLanguage = ArrayHelper::remove($config, 'sourceLanguage', $this->sourceLanguage);

        if (($basePath = ArrayHelper::remove($config, 'basePath')) !== null) {
            $this->setBasePath($basePath);
        }

        // Translation category
        $i18n = Craft::$app->getI18n();
        /** @noinspection UnSafeIsSetOverArrayInspection */
        if (!isset($i18n->translations[$this->t9nCategory]) && !isset($i18n->translations[$this->t9nCategory.'*'])) {
            $i18n->translations[$this->t9nCategory] = [
                'class' => PhpMessageSource::class,
                'sourceLanguage' => $this->sourceLanguage,
                'basePath' => $this->getBasePath().DIRECTORY_SEPARATOR.'translations',
                'allowOverrides' => true,
            ];
        }

        // Set this as the global instance of this plugin class
        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    public function init()
    {
        self::$app = new App();

        Craft::setAlias('@sproutbaselists', $this->getBasePath());

        // Setup Controllers
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'sproutbaselists\\console\\controllers';
        } else {
            $this->controllerNamespace = 'sproutbaselists\\controllers';

            $this->controllerMap = [
                'lists' => ListsController::class,
                'subscribers' => SubscribersController::class
            ];
        }

        Craft::$app->view->registerTwigExtension(new TwigExtensions());

        Event::on(Lists::class, Lists::EVENT_REGISTER_LIST_TYPES, static function(RegisterListTypesEvent $event) {
            $event->listTypes[] = SubscriberList::class;
//            $event->listTypes[] = WishList::class;
        });

        // Setup Template Roots
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            $e->roots['sprout-base-lists'] = $this->getBasePath().DIRECTORY_SEPARATOR.'templates';
        });

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
            $event->sender->set('sproutLists', SproutListsVariable::class);
        });

        Event::on(User::class, User::EVENT_AFTER_SAVE, static function(Event $event) {
            if (Craft::$app->getPlugins()->isPluginEnabled('sprout-lists')) {
                SproutBaseLists::$app->subscribers->handleUpdateUserIdOnSaveEvent($event);
            }
        });

        Event::on(User::class, User::EVENT_AFTER_DELETE, static function(Event $event) {
            if (Craft::$app->getPlugins()->isPluginEnabled('sprout-lists')) {
                SproutBaseLists::$app->subscribers->handleUpdateUserIdOnDeleteEvent($event);
            }
        });

        parent::init();
    }
}

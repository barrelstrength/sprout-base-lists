<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbasereports\elements;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbasereports\elements\actions\DeleteReport;
use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbasereports\base\DataSource;
use barrelstrength\sproutbasereports\elements\db\ReportQuery;
use barrelstrength\sproutbasereports\models\Settings;
use barrelstrength\sproutbasereports\records\Report as ReportRecord;
use barrelstrength\sproutbasereports\services\DataSources;
use barrelstrength\sproutbasereports\SproutBaseReports;
use Craft;
use craft\base\Plugin;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use Exception;
use InvalidArgumentException;
use Throwable;
use yii\web\NotFoundHttpException;

/**
 * Class Segment
 *
 * @package barrelstrength\sproutbasereports\elements
 */
class Segment extends Report
{
    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-base-lists', 'Segments (Sprout)');
    }
}
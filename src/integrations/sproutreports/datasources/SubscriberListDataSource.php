<?php

namespace barrelstrength\sproutforms\integrations\sproutreports\datasources;

use barrelstrength\sproutbasereports\SproutBaseReports;
use barrelstrength\sproutforms\elements\Form;
use barrelstrength\sproutforms\SproutForms;
use barrelstrength\sproutbasereports\elements\Report;
use Craft;
use barrelstrength\sproutbasereports\base\DataSource;
use craft\db\Query;
use craft\fields\data\MultiOptionsFieldData;
use craft\helpers\DateTimeHelper;
use barrelstrength\sproutforms\elements\Entry;
use craft\elements\db\ElementQueryInterface;
use DateTime;
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class SubscriberListDataSource
 *
 * @package barrelstrength\sproutforms\integrations\sproutreports\datasources
 */
class SubscriberListDataSource extends DataSource
{
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-base-lists', 'Subscriber List (Sprout)');
    }

    /**
     * @return null|string
     */
    public function getDescription(): string
    {
        return Craft::t('sprout-base-lists', 'Create a Mailing List with your Subscribers');
    }

    /**
     * @inheritDoc
     */
    public function getViewContext(): string
    {
        return 'reports';
    }

    /**
     * @inheritDoc
     */
    public function getViewContextLabel(): string
    {
        return 'Mailing List';
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getResults(Report $report, array $settings = []): array
    {

        return [];
    }

    /**
     * @inheritDoc
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getSettingsHtml(array $settings = [])
    {

        return 'Select which Subscriber List you want to create a mailing list from';
//        /** @var Form[] $forms */
//        $forms = Form::find()->limit(null)->orderBy('name')->all();
//
//        if (empty($settings)) {
//            $settings = (array)$this->report->getSettings();
//        }
//
//        $formOptions = [];
//
//        foreach ($forms as $form) {
//            $formOptions[] = [
//                'label' => $form->name,
//                'value' => $form->id
//            ];
//        }
//
//        // @todo Determine sensible default start and end date based on Order data
//        $defaultStartDate = null;
//        $defaultEndDate = null;
//
//        if (count($settings)) {
//            if (isset($settings['startDate'])) {
//                $startDateValue = (array)$settings['startDate'];
//
//                $settings['startDate'] = DateTimeHelper::toDateTime($startDateValue);
//            }
//
//            if (isset($settings['endDate'])) {
//                $endDateValue = (array)$settings['endDate'];
//
//                $settings['endDate'] = DateTimeHelper::toDateTime($endDateValue);
//            }
//        }
//
//        $dateRanges = SproutBaseReports::$app->reports->getDateRanges(false);
//
//        return Craft::$app->getView()->renderTemplate('sprout-forms/_integrations/sproutreports/datasources/EntriesDataSource/settings', [
//            'formOptions' => $formOptions,
//            'defaultStartDate' => new DateTime($defaultStartDate),
//            'defaultEndDate' => new DateTime($defaultEndDate),
//            'dateRanges' => $dateRanges,
//            'options' => $settings
//        ]);
    }

//    /**
//     * @inheritdoc
//     *
//     * @throws Exception
//     */
//    public function prepSettings(array $settings)
//    {
//        // Convert date strings to DateTime
//        $settings['startDate'] = DateTimeHelper::toDateTime($settings['startDate']) ?: null;
//        $settings['endDate'] = DateTimeHelper::toDateTime($settings['endDate']) ?: null;
//
//        return $settings;
//    }
}

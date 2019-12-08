<?php

namespace barrelstrength\sproutbaselists\integrations\sproutreports\datasources;

use barrelstrength\sproutbaselists\listtypes\MailingList;
use barrelstrength\sproutbaselists\records\ListElement as ListElementRecord;
use barrelstrength\sproutbaselists\records\Subscriber as SubscriberRecord;
use barrelstrength\sproutbasereports\elements\Report;
use Craft;
use barrelstrength\sproutbasereports\base\DataSource;
use craft\db\Query;
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class SubscriberListDataSource
 *
 * @package barrelstrength\sproutforms\integrations\sproutreports\datasources
 *
 * @property string $viewContextLabel
 */
class SubscriberListDataSource extends DataSource
{
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-base-lists', 'Subscriber List (Sprout Lists)');
    }

    /**
     * @return null|string
     */
    public function getDescription(): string
    {
        return Craft::t('sprout-base-lists', 'Create a Subscriber List with your Subscribers');
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
        return 'Subscriber List';
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getResults(Report $report, array $settings = []): array
    {
        $reportSettings = $report->getSettings();

        /** @var ListElementRecord $listRecord */
        $listRecord = ListElementRecord::find()
            ->where([
                'id' => $reportSettings['subscriberListId']
            ])
            ->one();

        /** @var SubscriberRecord $subscriberRecords */
        $subscriberRecords = $listRecord->getSubscribers()->all();

        $subscribers = [];
        foreach ($subscriberRecords as $subscriberRecord) {
            $subscribers[] = $subscriberRecord->getAttributes();
        }

        return $subscribers;
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
        $subscriberListOptions = (new Query())
            ->select([
                'lists.name AS label',
                'lists.id AS value'
            ])
            ->from('{{%sproutlists_lists}} lists')
            ->leftJoin('{{%elements}} elements', '[[elements.id]] = [[lists.id]]')
            ->where([
                'lists.type' => MailingList::class,
                'elements.dateDeleted' => null
            ])
            ->all();

        return Craft::$app->getView()->renderTemplate('sprout-base-lists/_integrations/sproutreports/datasources/SubscriberList/settings', [
            'subscriberListOptions' => $subscriberListOptions
        ]);
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

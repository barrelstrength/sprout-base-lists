<?php

namespace barrelstrength\sproutbaselists\integrations\sproutreports\datasources;

use barrelstrength\sproutbasereports\base\SegmentDataSource;
use barrelstrength\sproutbasereports\elements\Report;
use Craft;
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class CustomMailingListQuery
 *
 * @package Craft
 *
 * @property string $name
 */
class CustomMailingListQuery extends SegmentDataSource
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-base-lists', 'Custom Mailing List Query');
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Craft::t('sprout-base-lists', 'Create a list using a custom database query');
    }

    /**
     * @inheritdoc
     */
    public function getResults(Report $report, array $settings = []): array
    {
        $query = $report->getSetting('query');

        $result = [];

        try {
            $result = Craft::$app->getDb()->createCommand($query)->queryAll();
        } catch (Exception $e) {
            $report->setResultsError($e->getMessage());
        }

        return $result;
    }

    /**
     * @param array $settings
     *
     * @return string|null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSettingsHtml(array $settings = [])
    {
        $settingsErrors = $this->report->getErrors('settings');
        $settingsErrors = array_shift($settingsErrors);

        return Craft::$app->getView()->renderTemplate('sprout-base-lists/_integrations/sproutreports/datasources/CustomMailingListQuery/settings', [
            'settings' => count($settings) ? $settings : $this->report->getSettings(),
            'errors' => $settingsErrors
        ]);
    }

    /**
     * @inheritdoc
     */
    public function validateSettings(array $settings = [], array &$errors = []): bool
    {
        if (empty($settings['query'])) {
            $errors['query'][] = Craft::t('sprout-base-lists', 'Query cannot be blank.');

            return false;
        }

        return true;
    }
}

<?php

namespace barrelstrength\sproutbaselists\integrations\sproutreports\datasources;

use barrelstrength\sproutbasereports\base\DataSource;
use barrelstrength\sproutbasereports\base\SegmentDataSource;
use barrelstrength\sproutbasereports\elements\Report;
use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use Twig_Error_Loader;
use yii\base\Exception;

/**
 *
 * @property string $name
 */
class Users extends SegmentDataSource
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return Craft::t('sprout-base-lists', 'Users');
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return Craft::t('sprout-base-lists', 'Create reports about your users and user groups.');
    }

    /**
     * @param Report $report
     * @param array  $settings
     *
     * @return array
     */
    public function getResults(Report $report, array $settings = []): array
    {
        // First, use dynamic options, fallback to report options
        if (!count($settings)) {
            $settings = $report->getSettings();
        }

        $userGroupIds = $settings['userGroups'] ?? false;
        $displayUserGroupColumns = $settings['displayUserGroupColumns'] ?: false;

        $includeAdmins = false;

        if (is_array($userGroupIds) && in_array('admin', $userGroupIds, false)) {
            $includeAdmins = true;

            // Admin is always the first in our array if it exists
            unset($userGroupIds[0]);
        }

        $userGroups = Craft::$app->getUserGroups()->getAllGroups();

        $userGroupsByName = [];
        foreach ($userGroups as $userGroup) {
            $userGroupsByName[$userGroup->name] = 0;
        }

        $query = (new Query())
            ->select([
                '[[users.id]]',
                '[[users.username]] AS Username',
                '[[users.email]] AS Email',
                '[[users.firstName]] AS "First Name"',
                '[[users.lastName]] AS "Last Name"'
            ]);

        if ($displayUserGroupColumns) {
            $query->addSelect([
                '[[users.admin]] AS Admin'
            ]);
        }

        $query
            ->from([Table::USERS . ' users'])
            ->leftJoin(
                Table::USERGROUPS_USERS . ' usergroups_users',
                '[[users.id]] = [[usergroups_users.userId]]'
            );

        if (is_array($userGroupIds)) {
            $query->where([
                'in', '[[usergroups_users.groupId]]', $userGroupIds
            ]);
        }

        if ($includeAdmins) {
            $query->orWhere([
                '[[users.admin]]' => true
            ]);
        }

        $query->groupBy('[[users.id]]');
        $query->indexBy('id');

        $users = $query->all();

//        $query = new Query();
//        $userGroupsMapQuery = $query
//            ->select('*')
//            ->from('{{%usergroups_users}} usergroups_users')
//            ->join('LEFT JOIN', '{{%usergroups}} usergroups', 'usergroups.id = usergroups_users.groupId')
//            ->all();
//
//        // Create a map of all users and which user groups they are in
//        $userGroupsMap = [];
//        foreach ($userGroupsMapQuery as $userGroupsUser) {
//            $userGroupsMap[$userGroupsUser['userId']][$userGroupsUser['name']] = true;
//        }
//
//        // Add and identify User Groups as columns
//        foreach ($usersById as $key => $user) {
//            if ($displayUserGroupColumns) {
//                // Add User Groups as columns to user array
//                $user = array_merge($user, $userGroupsByName);
//
//                if (isset($userGroupsMap[$key])) {
//                    // Match users to the user groups they are in
//                    $user = array_merge($user, $userGroupsMap[$key]);
//                }
//            }
//
//            $usersById[$key] = $user;
//        }

        return $users;
    }

    /**
     * @param array $settings
     *
     * @return null|string
     * @throws Twig_Error_Loader
     * @throws Exception
     */
    public function getSettingsHtml(array $settings = [])
    {
        $userGroups = Craft::$app->getUserGroups()->getAllGroups();

        $userGroupSettings[] = [
            'label' => 'Admin',
            'value' => 'admin'
        ];

        foreach ($userGroups as $userGroup) {
            $userGroupSettings[] = [
                'label' => $userGroup->name,
                'value' => $userGroup->id
            ];
        }

        $settingsErrors = $this->report->getErrors('settings');
        $settingsErrors = array_shift($settingsErrors);

        return Craft::$app->getView()->renderTemplate('sprout-base-lists/_integrations/sproutreports/datasources/Users/settings', [
            'userGroupSettings' => $userGroupSettings,
            'settings' => count($settings) ? $settings : $this->report->getSettings(),
            'errors' => $settingsErrors
        ]);
    }

    /**
     * Validate our data source settings
     *
     * @param array $settings
     * @param array $errors
     *
     * @return bool
     */
    public function validateSettings(array $settings = [], array &$errors = []): bool
    {
        if (empty($settings['userGroups'])) {
            $errors['userGroups'][] = Craft::t('sprout-base-lists', 'Select at least one User Group.');

            return false;
        }

        return true;
    }
}

<?php

namespace barrelstrength\sproutbaselists\services;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\records\Subscriber as SubscribersRecord;
use barrelstrength\sproutbaselists\elements\Subscriber;
use craft\base\Component;
use craft\elements\User;
use yii\base\Event;

class Subscribers extends Component
{
    /**
     * @param ElementEvent $event
     *
     * @throws \Throwable
     */
    public function handleUpdateUserIdOnSaveEvent(Event $event)
    {
        /** @var Settings $settings */
        $settings = SproutBase::$app->settings->getPluginSettings('sprout-lists');

        if ($settings->enableUserSync && $event->sender instanceof User) {
            $this->updateUserIdOnSave($event);
        }
    }

    /**
     * @param Event $event
     *
     * @throws \Throwable
     */
    public function handleUpdateUserIdOnDeleteEvent(Event $event)
    {
        /** @var Settings $settings */
        $settings = SproutBase::$app->settings->getPluginSettings('sprout-lists');

        if ($settings->enableUserSync && $event->sender instanceof User) {
            $this->updateUserIdOnDelete($event);
        }
    }

    /**
     * Sync SproutLists subscriber to craft_users if same email is found on save.
     *
     * @param ElementEvent $event
     *
     * @return Subscriber|bool
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateUserIdOnSave(Event $event)
    {
        /**
         * @var User $user
         */
        $user = $event->sender;

        /**
         * @var SubscribersRecord $subscriberRecord
         */
        $subscriberRecord = SubscribersRecord::find()->where([
            'userId' => $user->id
        ])->one();

        // If that doesn't work, try to find a user with a matching email address
        if ($subscriberRecord === null) {

            $subscriberRecord = SubscribersRecord::find()
                ->where([
                    'userId' => null,
                    'email' => $user->email
                ])
                ->one();

            if ($subscriberRecord) {
                // Assign the user ID to the subscriber with the matching email address
                $subscriberRecord->userId = $user->id;
            }
        }

        if ($subscriberRecord !== null) {

            // Sync updates with existing Craft User if User Sync enabled
            $subscriberRecord->email = $user->email;
            $subscriberRecord->firstName = $user->firstName;
            $subscriberRecord->lastName = $user->lastName;

            try {

                $subscriberRecord->update(false);

                return true;
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * Remove any relationships between Sprout ListElement Subscriber and Users who are deleted.
     * Deleting a Craft User does not delete the matching Subscriber. It simply removes
     * the relationship to any Craft User ID from the Subscriber table.
     *
     * @param ElementEvent $event
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateUserIdOnDelete(Event $event): bool
    {
        /**
         * @var $user User
         */
        $user = $event->sender;

        /**
         * @var SubscribersRecord $subscriberRecord
         */
        $subscriberRecord = SubscribersRecord::find()->where([
            'userId' => $user->id,
        ])->one();

        if ($subscriberRecord !== null) {

            $subscriberRecord->userId = null;

            try {
                $subscriberRecord->save();

                return true;
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return false;
    }
}

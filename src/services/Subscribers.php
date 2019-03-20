<?php

namespace barrelstrength\sproutbaselists\services;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaselists\models\Settings;
use barrelstrength\sproutbaselists\records\Subscription;
use barrelstrength\sproutbaselists\records\Subscriber as SubscribersRecord;
use barrelstrength\sproutbaselists\elements\Subscriber;
use barrelstrength\sproutbaselists\SproutBaseLists;
use craft\base\Component;
use craft\elements\User;
use craft\events\ElementEvent;
use Craft;

class Subscribers extends Component
{
    /**
     * @param ElementEvent $event
     *
     * @throws \Throwable
     */
    public function handleUpdateUserIdOnSaveEvent(ElementEvent $event)
    {
        /** @var Settings $settings */
        $settings = SproutBase::$app->settings->getSettingsByPriority('sprout-lists');

        if ($settings->enableUserSync && $event->element instanceof User) {
            $this->updateUserIdOnSave($event);
        }
    }

    /**
     * @param ElementEvent $event
     *
     * @throws \Throwable
     */
    public function handleUpdateUserIdOnDeleteEvent(ElementEvent $event)
    {
        /** @var Settings $settings */
        $settings = SproutBase::$app->settings->getSettingsByPriority('sprout-lists');

        if ($settings->enableUserSync && $event->element instanceof User) {
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
    public function updateUserIdOnSave(ElementEvent $event)
    {
        /**
         * @var User $user
         */
        $user = $event->element;

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
     * Remove any relationships between Sprout SubscriberList Subscriber and Users who are deleted.
     * Deleting a Craft User does not delete the matching Subscriber. It simply removes
     * the relationship to any Craft User ID from the Subscriber table.
     *
     * @param ElementEvent $event
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     */
    public function updateUserIdOnDelete(ElementEvent $event): bool
    {
        /**
         * @var $user User
         */
        $user = $event->element;

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

    /**
     * Delete a subscriber and all related subscriptions
     *
     * @param $id
     *
     * @throws \Throwable
     */
    public function deleteSubscriberById($id)
    {
        if (Craft::$app->getElements()->deleteElementById($id)) {
            SubscribersRecord::deleteAll('id = :subscriberId', [':subscriberId' => $id]);
            Subscription::deleteAll('subscriberId = :subscriberId', [':subscriberId' => $id]);
        }
    }
}

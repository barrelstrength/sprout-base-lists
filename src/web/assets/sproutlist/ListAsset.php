<?php

namespace barrelstrength\sproutbaselists\web\assets\sproutlist;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Class EmailAsset
 *
 * @package barrelstrength\sproutemail\web\assets\email
 */
class ListAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@sproutbaselists/web/assets/sproutlist/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/SproutListsSubscriberIndex.js'
        ];

        parent::init();
    }
}
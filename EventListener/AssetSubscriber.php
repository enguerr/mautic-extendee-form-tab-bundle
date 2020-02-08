<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use MauticPlugin\MauticLimiterBundle\Service\Limiter;
use MauticPlugin\MauticLimiterBundle\Service\LimiterJs;
use MauticPlugin\MauticLimiterBundle\Integration\LimiterSettings;

class AssetSubscriber extends CommonSubscriber
{

    /**
     * AssetSubscriber constructor.
     *
     * @param Limiter $limiter
     */
    public function __construct()
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    /**
     * @param CustomAssetsEvent $assetsEvent
     */
    public function injectAssets(CustomAssetsEvent $assetsEvent)
    {
        $assetsEvent->addScript('plugins/MauticExtendeeFormTabBundle/Assets/js/extendee.js');
    }
}

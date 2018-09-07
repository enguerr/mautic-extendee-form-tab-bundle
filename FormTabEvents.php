<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle;

/**
 * Class MauticExtendeeFormTabBundle
 */
final class FormTabEvents
{
    /**
     * The mautic.extendee_form_tab.on_campaign_batch_action event is dispatched when the campaign action triggers.
     *
     * The event listener receives a Mautic\CampaignBundle\Event\PendingEvent
     *
     * @var string
     */
    const ON_CAMPAIGN_BATCH_ACTION = 'mautic.extendee_form_tab.on_campaign_batch_action';

    /**
     * The mautic.extendee_form_tab.on_campaign_trigger_condition event is dispatched when the campaign condition triggers.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\CampaignExecutionEvent
     *
     * @var string
     */
    const ON_CAMPAIGN_TRIGGER_CONDITION = 'mautic.extendee_form_tab.on_campaign_trigger_condition';

}

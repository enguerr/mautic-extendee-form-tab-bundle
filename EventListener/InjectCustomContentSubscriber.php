<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Integration\FormTabIntegration;

class InjectCustomContentSubscriber extends CommonSubscriber
{
    /**
     * @var IntegrationHelper
     */
    protected $integrationHelper;

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * ButtonSubscriber constructor.
     *
     * @param IntegrationHelper              $integrationHelper
     * @param FormTabHelper                  $formTabHelper
     */
    public function __construct(
        IntegrationHelper $integrationHelper,
        FormTabHelper $formTabHelper
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->formTabHelper     = $formTabHelper;
    }

    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectViewCustomContent', 0],
        ];
    }

    /**
     * @param CustomContentEvent $customContentEvent
     */
    public function injectViewCustomContent(CustomContentEvent $customContentEvent)
    {
        /** @var FormTabIntegration $formTab */
        $formTab = $this->integrationHelper->getIntegrationObject('FormTab');
        if ((false === $formTab || !$formTab->getIntegrationSettings()->getIsPublished(
                )) || ($customContentEvent->getContext() != 'tabs' && $customContentEvent->getContext() != 'tabs.content')
        ) {
            return;
        }
        if (empty($customContentEvent->getVars()['lead']) || !$customContentEvent->getVars()['lead'] instanceof Lead) {
            return;
        }

        $leadForms = $this->formTabHelper->getFormsWithResults($customContentEvent->getVars()['lead']->getId());
        if (empty($leadForms)) {
            return;
        }

        if ($customContentEvent->getContext() == 'tabs') {
            $customContentEvent->addContent($this->formTabHelper->getTabHeaderWithResult($leadForms));
        }

        if ($customContentEvent->getContext() == 'tabs.content') {
            $customContentEvent->addContent($this->formTabHelper->getTabContentWithResult($leadForms));
        }
    }

}

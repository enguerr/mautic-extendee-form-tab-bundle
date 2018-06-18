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
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Templating\Helper\ButtonHelper;
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
     * @param IntegrationHelper $integrationHelper
     * @param FormTabHelper     $formTabHelper
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
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectViewCustomContent', 0],
        ];
    }

    /**
     * @param CustomButtonEvent $event
     */
    public function injectViewButtons(CustomButtonEvent $event)
    {
        $this->addButtonGenerator(
            $event,
            ButtonHelper::LOCATION_PAGE_ACTIONS,
            'new',
            'mautic.core.form.new',
            'fa fa-plus',
            'mautic_form_results',
            1000,
            true
        );

        $this->addButtonGenerator(
            $event,
            ButtonHelper::LOCATION_LIST_ACTIONS,
            'edit',
            'mautic.core.form.edit',
            'fa fa-pencil-square-o',
            'mautic_form_results',
            1000,
            true,
            '#MauticSharedModal'
        );
    }

    /**
     * @param CustomContentEvent $customContentEvent
     */
    public function injectViewCustomContent(CustomContentEvent $customContentEvent)
    {
        /** @var FormTabIntegration $formTab */
        $formTab = $this->integrationHelper->getIntegrationObject('FormTab');

        if ((false === $formTab || !$formTab->getIntegrationSettings()->getIsPublished(
                )) || ($customContentEvent->getContext() != 'tabs' && $customContentEvent->getContext(
                ) != 'tabs.content')
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

    /**
     * @param CustomButtonEvent $event
     * @param string            $location
     * @param                   $objectAction
     * @param                   $btnText
     * @param                   $icon
     * @param                   $context
     * @param int               $priority
     * @param bool              $primary
     * @param null              $target
     * @param string            $header
     */
    private function addButtonGenerator(
        CustomButtonEvent $event,
        $location,
        $objectAction,
        $btnText,
        $icon,
        $context,
        $priority = 1,
        $primary = false,
        $target = null,
        $header = ''
    ) {
        $formId = $event->getRequest()->get('objectId', '');

        if (!$formId) {
            return;
        }
        $parameters =          [
            'formId'     => $formId,
        ];

        if (method_exists($event, 'getItem') && is_array($event->getItem())) {
            $parameters['objectId'] =  $event->getItem()['id'];
        }
        $parameters['objectId'] = 1;
        $route    = $this->router->generate(
            'mautic_formtabsubmission_edit',
            $parameters
        );
        $attr = [
            'href'        => $route,
            'data-toggle' => 'ajax',
            'data-method' => 'POST',
        ];
        switch ($target) {
            case '_blank':
                $attr['data-toggle'] = '';
                $attr['data-method'] = '';
                $attr['target']      = $target;
                break;
            case '#MauticSharedModal':
                $attr['data-toggle'] = 'ajaxmodal';
                $attr['data-method'] = '';
                $attr['data-target'] = $target;
                $attr['data-header'] = $header;
                break;
        }

        $button =
            [
                'attr'      => $attr,
                'btnText'   => $this->translator->trans($btnText),
                'iconClass' => $icon,
                'priority'  => $priority,
                'primary'   => $primary,
            ];

        $event
            ->addButton(
                $button,
                $location,
                [$context, ['objectId' => $formId]]
            );

    }

}

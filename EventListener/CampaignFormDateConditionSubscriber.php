<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\ConditionEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\CampaignFormDateConditionType;
use MauticPlugin\MauticExtendeeFormTabBundle\FormTabEvents;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Class CampaignFormDateConditionSubscriber
 */
class CampaignFormDateConditionSubscriber implements EventSubscriberInterface
{

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * CampaignFormDateConditionSubscriber constructor.
     *
     * @param FormTabHelper        $formTabHelper
     * @param CoreParametersHelper $coreParametersHelper
     */
    public function __construct(FormTabHelper $formTabHelper, CoreParametersHelper $coreParametersHelper)
    {

        $this->formTabHelper        = $formTabHelper;
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD            => ['onCampaignBuild', 0],
            FormTabEvents::ON_CAMPAIGN_TRIGGER_CONDITION => ['onCampaignTriggerFormDateCondition', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        $event->addCondition(
            'form.tab.date.condition',
            [
                'label'       => 'mautic.extendee.form.tab.campaign.date.condition',
                'description' => 'mautic.extendee.form.tab.campaign.date.condition.desc',
                'eventName'   => FormTabEvents::ON_CAMPAIGN_TRIGGER_CONDITION,
                'formType'    => CampaignFormDateConditionType::class,
                'formTheme'   => 'MauticExtendeeFormTabBundle:FormTheme\DateCondition',

            ]
        );
    }

    /**
     * Triggers the condition if form date value condition is fired
     *
     * @param ConditionEvent $event
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function onCampaignTriggerFormDateCondition(ConditionEvent $event)
    {
        if (!$event->checkContext('form.tab.date.condition')) {
            return;
        }

        $config = $event->getConfig();
        $lead   = $event->getLead();
        list($formId, $fieldAlias) = explode('|', $config['field']);
        $form = $this->formTabHelper->getFormModel()->getEntity($formId);

        if (!$form || !$form->getId()) {
            $event->fail('Form not found.');

            return;
        }

        $expr = '';
        if ($config['unit'] == 'anniversary') {
            $expr = 'anniversary';
        }else{
            $expr = 'date';
        }

        $results = $this->formTabHelper->compareValue($form, $lead, $fieldAlias, $this->formTabHelper->getDate($config), $expr);
        if (!empty($results)) {
            $event->pass();
        } else {
            $event->fail();
        }
    }
}

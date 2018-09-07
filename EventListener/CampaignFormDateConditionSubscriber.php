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

        // Set the date in system timezone since this is triggered by cron
        $triggerDate = new \DateTime(
            'now',
            new \DateTimeZone($this->coreParametersHelper->getParameter('default_timezone'))
        );
        $interval = substr($config['interval'], 1); // remove 1st character + or -
        $unit = strtoupper($config['unit']);
        if (strpos($config['interval'], '+') !== false) { //add date
            $triggerDate->add(new \DateInterval('P'.$interval.$unit)); //add the today date with interval
        } elseif (strpos($config['interval'], '-') !== false) {
            $triggerDate->sub(new \DateInterval('P'.$interval.$unit)); //subtract the today date with interval
        }
        if ($this->formTabHelper->compareDateValue($form, $lead, $fieldAlias, $triggerDate->format('Y-m-d'))) {
            $event->pass();
        } else {
            $event->fail();
        }
    }
}

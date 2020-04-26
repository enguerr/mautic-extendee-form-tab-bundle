<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Helper;

use Doctrine\ORM\EntityManager;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEventDTO;
use MauticPlugin\MauticExtendeeFormTabBundle\EventListener\CampaignComplexFormConditionSubscriber;
use MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\CampaignComplexConditionType;
use MauticPlugin\MauticExtendeeFormTabBundle\Integration\FormTabIntegration;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FormTabHelper.
 */
class ComplexConditionHelper
{
    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * ComplexConditionHelper constructor.
     *
     * @param EventRepository $eventRepository
     */
    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param Event $event
     * @param string         $column
     *
     * @return EventRepository[]|null
     */
    public function getComplexConditionsForEvent(Event $event, $column = 'e.tempId', $conditionsType = null)
    {
        $properties        = $event->getProperties();
        $selectedIds = ArrayHelper::getValue(
            $conditionsType,
            $properties
        );

        if (empty($selectedIds)) {
            return null;
        }

        $complexConditions = $this->eventRepository->getEntities(
            [
                'ignore_paginator' => true,
                'filter'           => [
                    'force' => [
                        [
                            'column' => $column,
                            'value'  => $selectedIds,
                            'expr'   => 'in',
                        ],
                        [
                            'column' => 'e.campaign',
                            'value'  => $event->getCampaign(),
                            'expr'   => 'eq',
                        ],
                    ],
                ],
            ]
        );

        if (count($complexConditions)) {
            return $complexConditions;
        }

        return null;
    }

    /**
     * @param Event $event
     * @param Lead  $lead
     *
     * @return \MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEvents
     */
    public function getCampaignEvents(Event $event, Lead $lead)
    {
        $campaignEvents = new \MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEvents();
        foreach (CampaignComplexConditionType::getConditionsTypes() as $conditionsType) {
            $complexConditionsEvents = $this    ->getComplexConditionsForEvent($event, 'e.id', $conditionsType);
            if ($complexConditionsEvents) {
                $campaignEvents->addCampaignEvent(new CampaignEventDTO(
                    $event,
                    $complexConditionsEvents,
                    $conditionsType,
                    $lead
                ));
            }
        }

        return $campaignEvents;
    }


}

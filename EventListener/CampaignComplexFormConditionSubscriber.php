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
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignEvent;
use Mautic\CampaignBundle\Event\ConditionEvent;
use Mautic\CoreBundle\Helper\ArrayHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\CompareQueryBuilder;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEventDTO;
use MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\CampaignComplexConditionType;
use MauticPlugin\MauticExtendeeFormTabBundle\FormTabEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class CampaignComplexFormConditionSubscriber implements EventSubscriberInterface
{

    CONST EVENT_NAME = 'form.complex.condition';


    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var CompareQueryBuilder
     */
    private $compareQueryBuilder;

    /**
     * CampaignComplexFormConditionSubscriber constructor.
     *
     * @param EventRepository     $eventRepository
     * @param CompareQueryBuilder $compareQueryBuilder
     */
    public function __construct(EventRepository $eventRepository, CompareQueryBuilder $compareQueryBuilder)
    {
        $this->eventRepository     = $eventRepository;
        $this->compareQueryBuilder = $compareQueryBuilder;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_POST_SAVE           => ['processCampaignEventsAfterSave', 1],
            CampaignEvents::CAMPAIGN_ON_BUILD            => ['onCampaignBuild', 0],
            FormTabEvents::ON_CAMPAIGN_TRIGGER_CONDITION => ['onCampaignTriggerFormComplexCondition', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        $event->addCondition(
            self::EVENT_NAME,
            [
                'label'       => 'mautic.form.tab.campaign.complex.condition',
                'description' => 'mautic.form.tab.campaign.complex.condition.desc',
                'eventName'   => FormTabEvents::ON_CAMPAIGN_TRIGGER_CONDITION,
                'formType'    => CampaignComplexConditionType::class,
                'formTheme'   => 'MauticExtendeeFormTabBundle:FormTheme\ComplexCondition',

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
    public function onCampaignTriggerFormComplexCondition(ConditionEvent $event)
    {
        if (!$event->checkContext(self::EVENT_NAME)) {
            return;
        }

        $campaignEvents = new \MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEvents();
        foreach (CampaignComplexConditionType::getConditionsTypes() as $conditionsType) {
            $complexConditionsEvents = $this->getComplexConditionsForEvent($event->getLog()->getEvent(), 'e.id', $conditionsType);
            if ($complexConditionsEvents) {
                $campaignEvents->addCampaignEvent(new CampaignEventDTO(
                    $event,
                    $complexConditionsEvents,
                    $conditionsType
                ));
            }

        }
        // Pass with an error for the UI.
        if (empty($campaignEvents->getCampaignEvents())) {
            $event->setFailed('Any complex condition');
        } else {
            $results          = $this->compareQueryBuilder->compareValue($campaignEvents);
            if (!empty($results)) {
                $event->pass();
            } else {
                $event->fail();
            }
        }
    }

    /**
     * @param Event $event
     * @param string         $column
     *
     * @return EventRepository[]|null
     */
    private function getComplexConditionsForEvent(Event $event, $column = 'e.tempId', $conditionsType = null)
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
     * Update campaign events.
     *
     * This block specifically handles the campaign.jump_to_event properties
     * to ensure that it has the actual ID and not the temp_id as the
     * target for the jump.
     *
     * @param CampaignEvent $campaignEvent
     */
    public function processCampaignEventsAfterSave(CampaignEvent $campaignEvent)
    {
        $campaign = $campaignEvent->getCampaign();
        $events   = $campaign->getEvents();
        $toSave   = [];

        foreach ($events as $event) {
            if ($event->getType() !== self::EVENT_NAME) {
                continue;
            }

            foreach (CampaignComplexConditionType::getConditionsTypes() as $conditionsType) {
                $complexConditions = $this->getComplexConditionsForEvent($event, 'e.tempId', $conditionsType);
                if ($complexConditions !== null) {

                    $conditions = ArrayHelper::getValue(
                        $conditionsType,
                        $event->getProperties()
                    );

                    $conditions = array_flip($conditions);

                    foreach ($complexConditions as $complexCondition) {
                        if (isset($conditions[$complexCondition->getTempId()])) {
                            unset($conditions[$complexCondition->getTempId()]);
                            $conditions[$complexCondition->getId()] = $complexCondition->getId();
                        }
                    }

                    $conditions = array_keys($conditions);

                    $event->setProperties(
                        array_merge(
                            $event->getProperties(),
                            [
                                $conditionsType => $conditions,
                            ]
                        )
                    );

                    $toSave[] = $event;
                }
            }


        }

        if (count($toSave)) {
            $this->eventRepository->saveEntities($toSave);
        }
    }
}

<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO;


use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\ConditionEvent;

class CampaignEventDTO
{
    private $campaignEvent;

    /**
     * @var Event[]
     */
    private $complexConditionsEvents;

    /**
     * @var Event[]
     */
    private $compareEvents;

    /**
     * @var string
     */
    private $conditionsType;

    /**
     * CampaignEventDTO constructor.
     *
     * @param ConditionEvent $campaignEvent
     * @param array          $complexConditionsEvents
     * @param string $conditionsType
     */
    public function __construct(ConditionEvent $campaignEvent, array $complexConditionsEvents, $conditionsType)
    {
        $this->campaignEvent = $campaignEvent;
        $this->complexConditionsEvents = $complexConditionsEvents;
        $this->conditionsType = $conditionsType;
    }

    /**
     * @return Event[]
     */
    public function getCompareEvents()
    {
        foreach ($this->complexConditionsEvents as $complexConditionsEvent) {
            $this->compareEvents[] = new CompareEvent($complexConditionsEvent);
        }
        return $this->compareEvents;
    }

    /**
     * @return \Mautic\LeadBundle\Entity\Lead
     */
    public function getContact()
    {
        return $this->campaignEvent->getLead();
    }

    /**
     * @return string
     */
    public function getConditionsType()
    {
        return $this->conditionsType;
    }
}

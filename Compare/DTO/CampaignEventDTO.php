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

    public function __construct(ConditionEvent $campaignEvent, array $complexConditionsEvents)
    {
        $this->campaignEvent = $campaignEvent;
        $this->complexConditionsEvents = $complexConditionsEvents;
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
}

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

class CampaignEvents
{
    /**
     * @var array
     */
    private $campaignEvents;

    /**
     * @param CampaignEventDTO $campaignEventDTO
     * @param string $conditionsType
     */
    public function addCampaignEvent(CampaignEventDTO $campaignEventDTO)
    {
        $this->campaignEvents[] = $campaignEventDTO;
    }

    /**
     * @return CampaignEventDTO[]
     */
    public function getCampaignEvents()
    {
        return $this->campaignEvents;
    }

    /**
     * @return CampaignEventDTO|mixed
     */
    public function first()
    {
        return reset($this->campaignEvents);
    }
}

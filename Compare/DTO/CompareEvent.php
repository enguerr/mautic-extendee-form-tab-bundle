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

class CompareEvent
{
    /**
     * @var Event
     */
    private $compareEvent;

    /**
     * CompareEvent constructor.
     *
     * @param Event $compareEvent
     */
    public function __construct(Event $compareEvent)
    {
        $this->compareEvent = $compareEvent;
    }

    /**
     * @return CompareEventProperties
     */
    public function getProperties()
    {
        return new CompareEventProperties($this->compareEvent->getProperties());
    }

}

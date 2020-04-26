<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

class FormTabDateHelper
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * FormTabDateHelper constructor.
     *
     * @param CoreParametersHelper $coreParametersHelper
     */
    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public function getDateFromConfig($config)
    {
        $triggerDate = new \DateTime(
            'now',
            new \DateTimeZone($this->coreParametersHelper->getParameter('default_timezone'))
        );

        if ($config['unit'] === 'anniversary') {
            return $triggerDate;
        }elseif(strpos($config['interval'], '-') === false && strpos($config['interval'], '+') === false && strpos($config['unit'], '-') === false && strpos($config['unit'], '+') === false)
        {
            $config['interval'] = '+'.$config['interval'];
        }
        $interval    = substr($config['interval'], 1); // remove 1st character + or -
        $unit        = strtoupper($config['unit']);


        switch ($unit) {
            case 'H':
            case 'I':
            case 'S':
                $interval = 'T'.$interval;
        }
        $trigger = '';
        $type = '';

        if (strpos($config['unit'], '+') !== false) {
            $trigger = substr($config['unit'], 1);
            $type = 'add';
        }elseif (strpos($config['unit'], '-') !== false) {
            $trigger = substr($config['unit'], 1);
            $type = 'sub';
        }elseif (strpos($config['interval'], '+') !== false) {
            $trigger = 'P'.$interval.$unit;
            $type = 'add';
        } elseif (strpos($config['interval'], '-') !== false) {
            $trigger = 'P'.$interval.$unit;
            $type = 'sub';
        }
        if ($trigger) {
            $triggerDate->$type(new \DateInterval($trigger)); //subtract the today date with interval
        }

        return $triggerDate;
    }
}

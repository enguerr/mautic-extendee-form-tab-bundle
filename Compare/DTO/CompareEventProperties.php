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


use Mautic\CoreBundle\Helper\ArrayHelper;

class CompareEventProperties
{
    /**
     * @var array
     */
    private $properties;

    /**
     * EventPropertiesDTO constructor.
     *
     * @param array $properties
     */
    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    /**
     * @return int|null
     */
    public function getFormId()
    {
        $formId = ArrayHelper::getValue('form', $this->properties);
        if ($formId) {
            return $formId;
        }
        $formId = explode('|', ArrayHelper::getValue('field', $this->properties));
        return reset($formId);

    }

    /**
     * @return string|null
     */
    public function getFieldAlias()
    {
        $fieldAlias = explode('|', ArrayHelper::getValue('field', $this->properties));

        return end($fieldAlias);
    }

    public function getValue()
    {
        return ArrayHelper::getValue('value', $this->properties);
    }

    /**
     * @return bool
     */
    public function isCustomDateCondition()
    {
        return (count(explode('|', ArrayHelper::getValue('field', $this->properties))) === 2);
    }

    /**
     * @param array $operators
     *
     * @return mixed|string
     */
    public function getExpr(array $operators)
    {
        if ($this->isCustomDateCondition()) {
            if ($this->getProperties()['unit'] == 'anniversary') {
                $expr = 'anniversary';
            } else {
                $expr = 'date';
            }
        } else {
            $expr = $operators[$this->getProperties()['operator']]['expr'];
        }

        return $expr;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}

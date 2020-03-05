<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class CampaignComplexConditionType extends AbstractType
{
    CONST COMPLEX_CONDITIONS  = 'conditions';

    CONST COMPLEX_CONDITIONS2 = 'conditions2';

    CONST COMPLEX_CONDITIONS3 = 'conditions3';

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $properties = $builder->getData();

        foreach (self::getConditionsTypes() as $conditionsType) {
            $selected = isset($properties[$conditionsType]) ? $properties[$conditionsType] : null;
            $builder->add(
                $conditionsType,
                ChoiceType::class,
                [
                    'choices'    => [],
                    'multiple'   => false,
                    'label'      => 'mautic.form.tab.campaign.complex_form_condition',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'                => 'form-control campaignevent_properties_conditions',
                        'data-onload-callback' => 'updateComplexConditionEventOptions',
                        'data-selected'        => json_encode($selected),
                    ],
                    'multiple'   => true,
                    'required'   => false,
                ]
            );

            // Allows additional values (new events) to be selected before persisting
            $builder->get($conditionsType)->resetViewTransformers();
        }

    }

    /**
     * @return array
     */
    public static function getConditionsTypes()
    {
        return [self::COMPLEX_CONDITIONS, self::COMPLEX_CONDITIONS2, self::COMPLEX_CONDITIONS3];
    }
}

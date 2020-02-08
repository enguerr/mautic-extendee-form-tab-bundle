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
    CONST COMPLEX_CONDITIONS = 'conditions';
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $properties = $builder->getData();
        $selected  = isset($properties[self::COMPLEX_CONDITIONS]) ? $properties[self::COMPLEX_CONDITIONS] : null;

        $builder->add(
            self::COMPLEX_CONDITIONS,
            ChoiceType::class,
            [
                'choices'    => [],
                'multiple'   => false,
                'label'      => 'mautic.form.tab.campaign.complex_form_condition',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'                => 'form-control',
                    'data-onload-callback' => 'updateComplexConditionEventOptions',
                    'data-selected'        => json_encode($selected),
                ],
                'multiple'=> true,
                'constraints' => [
                    new NotBlank(
                        [
                            'message' => 'mautic.core.value.required',
                        ]
                    ),
                ],
            ]
        );

        // Allows additional values (new events) to be selected before persisting
        $builder->get(self::COMPLEX_CONDITIONS)->resetViewTransformers();
    }
}

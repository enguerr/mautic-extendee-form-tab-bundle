<?php

/*
 * @copyright   2015 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Form\Type;

use Mautic\FormBundle\Entity\Field;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class CampaignFormDateConditionType
 */
class CampaignFormDateConditionType extends AbstractType
{

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * CampaignFormDateConditionType constructor.
     *
     * @param FormTabHelper $formTabHelper
     */
    public function __construct(FormTabHelper $formTabHelper)
    {

        $this->formTabHelper = $formTabHelper;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dateFields = $this->formTabHelper->getDateFields();
        $choices    = [];
        foreach ($dateFields as $formId => $dateField) {
            /**
             * @var  Field $field
             */
            foreach ($dateField as $fieldAlias => $field) {
                $choices[$formId.'|'.$fieldAlias] = $field->getForm()->getName().': '.$field->getLabel();
            }
        }


        $builder->add(
            'field',
            ChoiceType::class,
            [
                'choices'     => $choices,
                'empty_value' => '',
                'attr'        => [
                    'class' => 'form-control',
                ],
                'label'       => 'mautic.form.field',
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.core.value.required']
                    ),
                ],
            ]
        );
        $builder->add(
            'interval',
            NumberType::class,
            [
                'label'       => '',
                'attr'        => [
                    'class' => 'form-control',
                    'preaddon'=>'symbol-hashtag'
                ],
                'data'=> empty($options['data']['interval']) ? 0 : $options['data']['interval'],
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.core.value.required']
                    ),
                ],
            ]
        );

        $choices = [];
        foreach (['i', 'h', 'd', 'm', 'y'] as $interval) {
            $choices[$interval] = $interval;
        }

        $builder->add(
            'unit',
            ChoiceType::class,
            [
                'choices'     => $choices,
                'empty_value' => '',
                'attr'        => [
                    'class' => 'form-control',
                ],
                'label'       => '',
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.core.value.required']
                    ),
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form_tab_date_condition';
    }
}

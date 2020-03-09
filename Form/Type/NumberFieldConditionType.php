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

use Mautic\FormBundle\Entity\Field;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class NumberFieldConditionType extends AbstractType
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
        $dateFields = $this->formTabHelper->getNumberFields();
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
                'label'       => 'mautic.form.tab.form.sum_number_form_field',
                'required'    => false,
            ]
        );

        $choices       = [];
        $choices['gt'] = 'mautic.lead.list.form.operator.greaterthan';
        $choices['lt'] = 'mautic.lead.list.form.operator.lessthan';


        $builder->add(
            'expr',
            'choice',
            [
                'label'       => 'mautic.lead.lead.events.campaigns.expression',
                'multiple'    => false,
                'choices'     => $choices,
                'empty_value' => false,
                'required'    => false,
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class' => 'form-control',
                ],
            ]
        );

        $builder->add(
            'value',
            NumberType::class,
            [
                'label'      => 'mautic.core.value',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                ],
                'required'   => true,
            ]
        );

    }
}

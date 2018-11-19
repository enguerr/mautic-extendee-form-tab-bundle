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

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;

class EmailSendResultType extends AbstractType
{

   /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'form',
            'form_list',
            [
                'label'       => 'mautic.form.campaign.event.forms',
                'label_attr'  => ['class' => 'control-label'],
                'multiple'    => false,
                'empty_value' => 'mautic.core.select',
                'attr'        => [
                    'class'    => 'form-control  formtab-campaign-form',
                    'tooltip'  => 'mautic.form.campaign.event.forms_descr',
                    'onchange' => 'Mautic.generateFieldsFormTab(this)',
                ],
                'required'    => true,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.core.value.required']
                    ),
                ],
            ]
        );

        $builder->add(
            'email',
            'email_list',
            [
                'label'      => 'mautic.email.send.selectemails',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'tooltip'  => 'mautic.email.choose.emails_descr',
                    'onchange' => 'Mautic.disabledEmailAction(window, this)',
                ],
                'multiple'    => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.email.chooseemail.notblank']
                    ),
                ],
            ]
        );
    }
}

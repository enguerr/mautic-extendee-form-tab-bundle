<?php

namespace MauticPlugin\MauticExtendeeFormTabBundle\Integration;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\FormBundle\Form\Type\FormListType;
use Mautic\PluginBundle\Integration\AbstractIntegration;

class FormTabIntegration extends AbstractIntegration
{
    public function getName()
    {
        // should be the name of the integration
        return 'FormTab';
    }

    public function getAuthenticationType()
    {
        /* @see \Mautic\PluginBundle\Integration\AbstractIntegration::getAuthenticationType */
        return 'none';
    }

    /**
     * Get icon for Integration.
     *
     * @return string
     */
    public function getIcon()
    {
        return 'plugins/MauticExtendeeFormTabBundle/Assets/img/icon.png';
    }

    /**
     * @param \Mautic\PluginBundle\Integration\Form|FormBuilder $builder
     * @param array                                             $data
     * @param string                                            $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ($formArea == 'keys') {
            $builder->add(
                'forms',
                FormListType::class,
                [
                    'label'      => 'mautic.extendee.form.tab.forms',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'forms_forced',
                FormListType::class,
                [
                    'label'      => 'mautic.extendee.form.tab.forms.forced',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class'   => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'debugMode',
                YesNoButtonGroupType::class,
                [
                    'label' => 'mautic.form.tab.form.debug_mode',
                    'data'  => isset($data['debugMode']) ? $data['debugMode'] : false,
                ]
            );
        }
    }
}

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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class SubmissionType
 */
class SubmissionType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $data = $options['data'];
        $builder->add(
            'execute',
            'yesno_button_group',
            [
                'label' => 'mautic.extendee.form.tab.execute',
                'data'  => (isset($data['execute'])) ? (bool) $data['execute'] : false,
            ]
        );

        $builder->add(
            'buttons',
            'form_buttons',
            [
                'apply_text' => false,
            ]
        );

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }

    }


    /**
     * @return string
     */
    public function getName()
    {
        return 'form_tab_submission';
    }
}

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

/**
 * Class ModifyFormResultType
 */
class ModifyFormResultType extends AbstractType
{

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * ModifyFormResultType constructor.
     *
     * @param FormTabHelper $formTabHelper
     * @param RequestStack  $requestStack
     */
    public function __construct(FormTabHelper $formTabHelper, RequestStack $requestStack)
    {

        $this->formTabHelper = $formTabHelper;
        $this->requestStack = $requestStack;
    }

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

        $ff = $builder->getFormFactory();
        // function to add 'template' choice field dynamically
        $func = function (FormEvent $e) use ($ff) {
            $data    = $e->getData();
            $form    = $e->getForm();

            if ($form->has('field')) {
                $form->remove('field');
            }

            if (empty($data['form'])) {
                $content = '';
            } else {
                if (!empty($data['content'])) {
                    $content = $data['content'];
                }elseif (!empty($data['properties']['content'])) {
                    $content = $data['properties']['content'];
                }
                if(!empty($content)){
                    foreach ($content as $key=>$value) {
                        $this->requestStack->getCurrentRequest()->query->set($key, urlencode($value));
                    }
                }
                $content = $this->formTabHelper->getFormContentFromId($data['form'], true, 'name="campaignevent[properties][content]');
            }

            $form->add(
                'content',
                HiddenType::class,
                [
                    'data' => $content
                ]
            );
        };
        // Register the function above as EventListener on PreSet and PreBind
        $builder->addEventListener(FormEvents::PRE_SET_DATA, $func);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, $func);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form_tab_modify_result';
    }

}

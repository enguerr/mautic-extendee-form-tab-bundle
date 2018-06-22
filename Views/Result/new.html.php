<p>
    <?php
    $customButtons = [];
    if ($canCreate) {
        $customButtons[] =
            [
                'attr'      => [
                    'data-toggle' => 'ajaxmodal',
                    'data-target' => '#MauticSharedModal',
                    'data-header' => $view['translator']->trans(
                        'mautic.extendee.form.tab.add',
                        ['%contactEmail%' => $lead['email']]
                    ),
                    'href'        => $view['router']->path(
                        'mautic_formtabsubmission_edit',
                        ['formId' => $form->getId(), 'contactId' => $lead['id'], 'email'=>$lead['email']]
                    ),
                ],
                'btnText'   => $view['translator']->trans('mautic.core.form.new'),
                'iconClass' => 'fa fa-plus',
            ];
    }
    echo $view->render('MauticCoreBundle:Helper:page_actions.html.php', ['customButtons' => $customButtons]);
    ?>
</p>
<?php
$item['name'] = $view['translator']->trans(
    'mautic.form.form.results.name',
    ['%id%' => $item['id']]
);
$formId       = $form->getId();
if (isset($new) && $new) {
    echo '</tr>';
}
if (!isset($skip)) {
    ?>
    <tr id="form-tab-submission-<?php echo $item['id']; ?>">
    <?php
}
if ($canDelete):

    $customButtons = [];
    $customButtons[] =
        [
            'attr'      => [
                'data-toggle' => 'ajaxmodal',
                'data-target' => '#MauticSharedModal',
                'data-header' => $view['translator']->trans(
                    'mautic.extendee.form.tab.edit',
                    ['%formName%' => $form->getName(), '%contactEmail%' => $lead['email']]
                ),
                'href'        => $view['router']->path(
                    'mautic_formtabsubmission_edit',
                    ['objectId' => $item['id'], 'formId' => $form->getId(), 'contactId' => $lead['id']]
                ),
            ],
            'btnText'   => $view['translator']->trans('mautic.core.form.edit'),
            'iconClass' => 'fa fa-pencil-square-o',
            'target'    => '#form-container',
            'priority'  => 1,
        ];

    $customButtons[] =
        [
            'confirm' => [
                'message'       => $view['translator']->trans(
                    'mautic.form.results.form.confirmdelete'
                ),
                'confirmAction' => $view['router']->path(
                    'mautic_formtabsubmission_delete',
                    ['objectId' => $item['id']]
                ),
                'template'      => 'delete',
                'btnClass'      => false,
            ],
        ];


    ?>
    <td>
        <?php
        echo $view->render(
            'MauticCoreBundle:Helper:list_actions.html.php',
            [
                'item'          => $item,
                'route'         => 'mautic_form_results_action',
                'langVar'       => 'form.results',
                'query'         => [
                    'formId'       => $formId,
                    'objectAction' => 'delete',
                ],
                'customButtons' => isset($customButtons) ? $customButtons : [],
            ]
        );
        ?>
    </td>
<?php endif; ?>
    <td>
        <?php echo $view['date']->toFull($item['dateSubmitted']); ?>
    </td>
<?php foreach ($item['results'] as $key => $r): ?>
    <?php $isTextarea = $r['type'] === 'textarea'; ?>
    <td <?php echo $isTextarea ? 'class="long-text"' : ''; ?>>
        <?php if ($isTextarea) : ?>
            <?php echo nl2br($r['value']); ?>
        <?php elseif ($r['type'] === 'file') : ?>
            <a href="<?php echo $view['router']->path(
                'mautic_form_file_download',
                ['submissionId' => $item['id'], 'field' => $key]
            ); ?>">
                <?php echo $r['value']; ?>
            </a>
        <?php else : ?>
            <?php echo $r['value']; ?>
        <?php endif; ?>
    </td>
<?php endforeach; ?>
<?php
if (!isset($skip)) {
    ?>
    </tr>
    <?php
}

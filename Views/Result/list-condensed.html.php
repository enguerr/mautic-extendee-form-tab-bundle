<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$formId = $form->getId();
?>
<p>
    <?php
    $customButtons = [
        [
            'attr' => [
                'data-toggle' => 'ajaxmodal',
                'data-target' => '#MauticSharedModal',
                'data-header' => $view['translator']->trans('mautic.extendee.form.tab.add',[ '%contactEmail%'=> $lead['email']]),
                'data-footer' => 'false',
                'href'        => $view['router']->path(
                    'mautic_formtabsubmission_edit',
                    ['formId' => $form->getId()]
                ),
            ],
            'btnText'   => $view['translator']->trans('mautic.core.form.new'),
            'iconClass' => 'fa fa-plus',
        ],
    ];
    echo $view->render('MauticCoreBundle:Helper:page_actions.html.php', ['customButtons' => $customButtons]);
    ?>
</p>

<div class="table-responsive table-responsive-force">
    <table class="table table-hover table-striped table-bordered formresult-list">
        <thead>
        <tr>
            <?php
            if ($canDelete):
                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'checkall'        => 'true',
                        'target'          => '#formResultTable',
                        'routeBase'       => 'form_results',
                        'query'           => ['formId' => $formId],
                        'templateButtons' => [
                            'delete' => $canDelete,
                        ],
                    ]
                );
            endif;
            echo $view->render(
                'MauticCoreBundle:Helper:tableheader.html.php',
                [
                    'sessionVar' => 'formresult.'.$formId,
                    'text'       => 'mautic.form.result.thead.date',
                    'class'      => 'col-formresult-date',
                    'dataToggle' => 'date',
                ]
            );

            $fields     = $form->getFields();
            $fieldCount = ($canDelete) ? 4 : 3;
            foreach ($fields as $f):
                if (in_array($f->getType(), $viewOnlyFields) || $f->getSaveResult() === false) {
                    continue;
                }
                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'sessionVar' => 'formresult.'.$formId,
                        'text'       => $f->getLabel(),
                        'class'      => 'col-formresult-field col-formresult-field'.$f->getId(),
                    ]
                );
                ++$fieldCount;
            endforeach;
            ?>
        </tr>
        </thead>
        <tbody>
        <?php if (count($items)): ?>
            <?php foreach ($items as $item): ?>
                <?php $item['name'] = $view['translator']->trans(
                    'mautic.form.form.results.name',
                    ['%id%' => $item['id']]
                ); ?>
                <tr>
                    <?php
                    if ($canDelete):

                        $customButtons = [
                            [
                                'attr'      => [
                                    'data-toggle' => 'ajaxmodal',
                                    'data-target' => '#MauticSharedModal',
                                    'data-header' => $view['translator']->trans(
                                        'mautic.extendee.form.tab.edit',
                                        ['%formName%' => $form->getName(), '%contactEmail%' => $lead['email']]
                                    ),
                                    'data-footer' => 'false',
                                    'href'        => $view['router']->path(
                                        'mautic_formtabsubmission_edit',
                                        ['objectId' => $item['id'], 'formId' => $form->getId()]
                                    ),
                                ],
                                'btnText'   => $view['translator']->trans('mautic.core.form.edit'),
                                'iconClass' => 'fa fa-pencil-square-o',
                            ],
                        ];
                        ?>
                        <td>
                            <?php
                            echo $view->render(
                                'MauticCoreBundle:Helper:list_actions.html.php',
                                [
                                    'item'            => $item,
                                    'templateButtons' => [
                                        'delete' => $canDelete,
                                    ],
                                    'route'           => 'mautic_form_results_action',
                                    'langVar'         => 'form.results',
                                    'query'           => [
                                        'formId'       => $formId,
                                        'objectAction' => 'delete',
                                    ],
                                    'customButtons'   => isset($customButtons) ? $customButtons : [],
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
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

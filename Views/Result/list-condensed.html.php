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
<div id="form-results-<?php echo $formId ?>">
    <p>
        <?php
        $customButtons = [
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
                        ['formId' => $form->getId(), 'contactId' => $lead['id']]
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
                    ?>
                    <th style="min-width:auto;"></th>
                    <?php
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
                <?php foreach ($items as $item):
                    echo $view->render(
                        'MauticExtendeeFormTabBundle:Result:item.html.php',
                        [
                            'item'      => $item,
                            'form'      => $form,
                            'canDelete' => $canDelete,
                            'lead'      => $lead,
                        ]
                    );
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
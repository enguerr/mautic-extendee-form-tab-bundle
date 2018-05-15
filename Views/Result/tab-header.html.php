<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

?>

<?php if (!empty($leadForms)): ?>
    <li class="">
        <a href="#form-container" role="tab" data-toggle="tab">
                        <span class="label label-primary mr-sm" id="FormCount">
                            <?php echo count($leadForms); ?>
                        </span>
            <?php echo $view['translator']->trans('mautic.extendee.form.tab.forms'); ?>
        </a>
    </li>
<?php endif; ?>

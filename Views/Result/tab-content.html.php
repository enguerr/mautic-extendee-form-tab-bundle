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
<!-- #form-container -->
<?php if (!empty($leadForms)): ?>
    <div class="tab-pane fade bdr-w-0 row" id="form-container">
        <?php
        echo $view->render('MauticExtendeeFormTabBundle:Result:list.html.php',
            [
                'leadForms' => $leadForms,
            ]); ?>
    </div>
<?php endif; ?>
<!--/ #form-container -->
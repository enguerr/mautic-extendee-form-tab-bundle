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

<?php
if (!empty($error)) {
    ?>
    <div class="text-danger"><p><strong>
    <?php
    if (is_array($error)) {
        echo implode('<br />', $error);
    } else {
        echo $error;
    }
    echo '</strong></p></div>';
}
?>


<?php echo $view['form']->start($form); ?>
<?php echo $content; ?>
<?php echo $view['form']->row($form['buttons']); ?>
<?php echo $view['form']->end($form); ?>
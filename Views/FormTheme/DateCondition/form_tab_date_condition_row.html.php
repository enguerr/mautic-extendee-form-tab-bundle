<div class="row">
    <div class="col-xs-5">
        <?php echo $view['form']->row($form['field']); ?>
    </div>
    <div class="col-xs-3">
        <?php echo str_replace('type="text"', 'type="number"', $view['form']->row($form['interval'])); ?>
    </div>
    <div class="col-xs-4">
        <?php echo $view['form']->row($form['unit']); ?>
    </div>
</div>
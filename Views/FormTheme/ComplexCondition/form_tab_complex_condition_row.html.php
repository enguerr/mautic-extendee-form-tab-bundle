<div class="row">
    <div class="col-xs-12">
        <?php echo $view['form']->row($form['conditions']); ?>
    </div>
    <div class="col-xs-12">
        <?php echo $view['form']->row($form['conditions2']); ?>
    </div>
    <div class="col-xs-12">
        <?php echo $view['form']->row($form['conditions3']); ?>
    </div>
    <div class="col-xs-4">
        <?php echo $view['form']->row($form['sum']['field']); ?>
    </div>
    <div class="col-xs-4">
        <?php echo $view['form']->row($form['sum']['expr']); ?>
    </div>
    <div class="col-xs-4">
        <?php echo $view['form']->row($form['sum']['value']); ?>
    </div>
</div>
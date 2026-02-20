<?php
/**
 * @var object{id: $creditNote int, hash:string}
 */
?>
<div class="btn-group">
    <button type="button" class="btn btn-default pull-left dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
        aria-expanded="false">
        <?= _l('einvoice') ?>
        <span class="caret"></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-right">
        <li class="hidden-xs"><a target="_blank"
                href="<?= admin_url('einvoice/output/credit_note/' . $creditNote->id . '?output_type=view'); ?>"><?= _l('view_einvoice') ?></a>
        </li>
        <li><a
                href="<?= admin_url('einvoice/output/credit_note/' . $creditNote->id) ?>"><?= _l('download') ?></a>
        </li>
    </ul>
</div>
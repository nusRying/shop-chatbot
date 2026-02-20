<div class="card mtop15 tw-mb-0">
    <div class="card-header tw-flex tw-gap-x-2 tw-items-center tw-mb-2">
        <h4 class="card-title tw-font-semibold tw-my-0">
            <?= _l('template_variables') ?>
        </h4>
        <a href="https://github.com/bobthecow/mustache.php/wiki/Mustache-tags" target="_blank" rel="noreferrer noopener"
            class="tw-my-0">
            <?= _l('read_more') ?>
        </a>
    </div>
    <div class="card-body tw-bg-neutral-100 tw-rounded-lg tw-p-3 tw-pb-0 tw-text-neutral-900">
        <ul class="list-group template-variables">
            <li>
                <h4 class="tw-font-medium">
                    <?= _l('invoice') ?>:
                </h4>
                <ul class="list-unstyled tw-pl-4">
                    <?php foreach (Perfexcrm\EInvoice\Data\Invoice::getPlaceholders() as $placeholder) { ?>
                    <li class="tw-leading-5"><?= $placeholder ?></li>
                    <?php } ?>
                </ul>
            </li>

            <li>
                <h4 class="tw-font-medium">
                    <?= _l('invoice_items') ?>:
                </h4>
                <p class="tw-pl-4 tw-mb-0">{{# LINE_ITEMS }}</p>
                <ul class="list-unstyled tw-pl-8 tw-mb-2">
                    <?php foreach (Perfexcrm\EInvoice\Data\Item::getPlaceholders() as $placeholder) { ?>
                    <li class="tw-leading-5"><?= $placeholder ?></li>
                    <?php } ?>
                </ul>
                <ul class="list-unstyled tw-pl-8">
                    <p class="tw-mb-0">{{# LINE_ITEM_TAXES }}</p>
                    <?php foreach (Perfexcrm\EInvoice\Data\Item::getTaxesPlaceholders() as $placeholder) { ?>
                    <li class="tw-pl-4 tw-leading-5">
                        <?= $placeholder ?>
                    </li>
                    <?php } ?>
                    <p>{{/ LINE_ITEM_TAXES }}</p>
                </ul>
                <p class="tw-pl-4 tw-mb-0">{{/ LINE_ITEMS }}</p>
            </li>
            <li>
                <h4 class="tw-font-medium">
                    <?= _l('custom_field_company') ?>:
                </h4>
                <ul class="list-unstyled tw-pl-4">
                    <?php foreach (Perfexcrm\EInvoice\Data\Company::getPlaceholders() as $placeholder) { ?>
                    <li class="tw-leading-5"><?= $placeholder ?></li>
                    <?php } ?>
                </ul>
            </li>
            <li>
                <h4 class="tw-font-medium">
                    <?= _l('client') ?>:
                </h4>
                <ul class="list-unstyled tw-pl-4">
                    <?php foreach (Perfexcrm\EInvoice\Data\Customer::getPlaceholders() as $placeholder) { ?>
                    <li class="tw-leading-5"><?= $placeholder ?></li>
                    <?php } ?>
                </ul>
            </li>
            <li>
                <h4 class="tw-font-medium">
                    <?= _l('credit_note') ?>:
                </h4>
                <ul class="list-unstyled tw-pl-4">
                    <?php foreach (Perfexcrm\EInvoice\Data\CreditNote::getPlaceholders() as $placeholder) { ?>
                    <li class="tw-leading-5"><?= $placeholder ?></li>
                    <?php } ?>
                </ul>
            </li>
        </ul>
    </div>
</div>
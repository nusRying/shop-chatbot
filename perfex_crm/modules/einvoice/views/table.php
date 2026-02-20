<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'name',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'templates';
$result       = data_tables_init(
    $aColumns,
    $sIndexColumn,
    $sTable,
    where: ['AND (type = "einvoice")'],
    additionalSelect: [
        'id',
    ]
);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0, $iMax = count($aColumns); $i < $iMax; $i++) {
        $_data = $aRow[$aColumns[$i]];

        if ($aColumns[$i] === 'name') {
            $_data = '<span class="name"><a href="' . admin_url("einvoice/template/{$aRow['id']}") . '" class="tw-font-medium">' . e($_data) . '</a></span>';
        }
        $row[] = $_data;
    }
    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
    $options .= '<a href="' . admin_url("einvoice/template/{$aRow['id']}") . '" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
        <i class="fa-regular fa-pen-to-square fa-lg"></i>
    </a>';

    $options .= '<a href="#"
    class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete"
    onclick="delete_template(this,\'einvoice_invoice\',' . e($aRow['id']) . '); return false;">
                    <i class="fa-regular fa-trash-can"></i>
    </a>';

    $options .= '</div>';

    $row[] = $options;

    $output['aaData'][] = $row;
}

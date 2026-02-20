<?php

namespace Perfexcrm\EInvoice;

class BulkExporterConfig
{
    public readonly string $directory;

    public function __construct(
        public readonly string $exportType,
        public readonly bool   $hasPermission,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly string $status,
        public readonly string $dateColumn = 'date'
    )
    {
        $this->directory = TEMP_FOLDER . $this->exportType;
    }

    public function getErrorRedirectURL(): string
    {
        return admin_url('einvoice/export');
    }
}

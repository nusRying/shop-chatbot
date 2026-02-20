<?php

namespace Perfexcrm\EInvoice;

use app\services\utilities\Str;
use CI_Controller;
use RuntimeException;

class BulkExporter
{
    private BulkExporterConfig $config;

    private CI_Controller $ci;
    private EinvoiceHandler $einvoiceHandler;

    public function __construct(BulkExporterConfig $config)
    {
        $this->config = $config;
        $this->ci = &get_instance();
        $this->einvoiceHandler = new EinvoiceHandler();
        
        $this->loadRequiredModels();
        $this->validateEnvironment();
        $this->setupWorkingDirectory();
    }

    /**
     * Load required CodeIgniter models based on export type
     */
    private function loadRequiredModels(): void
    {
        if ($this->config->exportType === 'credit_note') {
            $this->ci->load->model('credit_notes_model');
        } else {
            $this->ci->load->model('invoices_model');
        }
        $this->ci->load->model('templates_model');
        $this->ci->load->library('zip');
    }

    /**
     * Validate that the environment is properly configured
     * 
     * @throws RuntimeException if environment validation fails
     */
    private function validateEnvironment(): void
    {
        if (!is_really_writable(TEMP_FOLDER)) {
            throw new RuntimeException(
                TEMP_FOLDER . ' folder is not writable. You need to change the permissions to 0755'
            );
        }
    }

    /**
     * Setup the working directory for export operations
     * 
     * @throws RuntimeException if directory creation fails
     */
    private function setupWorkingDirectory(): void
    {
        if (is_dir($this->config->directory)) {
            $this->clearDirectory($this->config->directory);
        }

        if (!mkdir($this->config->directory, 0755, true) && !is_dir($this->config->directory)) {
            throw new RuntimeException(
                sprintf('Directory "%s" was not created', $this->config->directory)
            );
        }
        
        register_shutdown_function([$this, 'clearDirectory'], $this->config->directory);
    }

    /**
     * Clear directory contents and remove the directory itself
     * 
     * @param string $dir Directory path to clear and remove
     */
    public function clearDirectory(string $dir): void
    {
        // Prevent accidental deletion of system temp folder
        if ($dir === TEMP_FOLDER) {
            return;
        }

        if (is_dir($dir)) {
            delete_files($dir);
            delete_dir($dir);
        }
    }

    /**
     * Execute the bulk export process
     */
    public function export(): void
    {
        $this->buildExportQuery();
        $exportData = $this->executeQueryAndPrepareData();
        $template = $this->getExportTemplate();
        
        $this->processExportData($exportData, $template);
        $this->createZipArchive();
    }

    /**
     * Build the database query for export based on configuration
     */
    private function buildExportQuery(): void
    {
        $this->ci->db->select('id, date');
        $this->ci->db->from(db_prefix() . 'invoices');
        
        $this->applyStatusFilters();
        $this->applyPermissionFilters();
        
        $this->ci->db->order_by($this->config->dateColumn, 'desc');
    }

    /**
     * Apply status-based filters to the query
     */
    private function applyStatusFilters(): void
    {
        if ($this->config->status === 'all') {
            return;
        }

        if (is_numeric($this->config->status)) {
            $this->ci->db->where('status', $this->config->status);
        } else {
            $this->ci->db->where('sent=0 AND status NOT IN(2,5)');
        }
    }

    /**
     * Apply permission-based filters to the query
     */
    private function applyPermissionFilters(): void
    {
        if (!$this->config->hasPermission) {
            $noPermissionQuery = get_invoices_where_sql_for_staff(get_staff_user_id());
            $this->ci->db->where($noPermissionQuery);
        }
    }

    /**
     * Execute the query and prepare data for export
     * 
     * @return array Export data
     */
    private function executeQueryAndPrepareData(): array
    {
        $this->setDateQuery();

        $data = $this->ci->db->get()->result_array();
        $this->validateExportData($data);
        
        $years = $this->extractYearsFromQuery();
        $this->createYearDirectories($years);

        return $data;
    }

    /**
     * Get the template for export or redirect on error
     * 
     * @return object Template object
     */
    private function getExportTemplate(): object
    {
        $templateId = get_option('einvoice_default_' . $this->config->exportType . '_template');
        $template = $this->ci->templates_model->find($templateId);
        
        if (!$template) {
            set_alert('danger', _l('einvoice_no_template_set'));
            redirect($this->config->getErrorRedirectURL());
        }

        return $template;
    }

    /**
     * Process export data and generate files
     * 
     * @param array $exportData Export data
     * @param object $template Template object
     */
    private function processExportData(array $exportData, object $template): void
    {
        foreach ($exportData as $itemData) {
            $saleItem = $this->ci->invoices_model->get($itemData['id']);


            $einvoiceData = match ($this->config->exportType) {
                'invoice' => new \Perfexcrm\EInvoice\Data\Invoice($saleItem),
                'credit_note' => new \Perfexcrm\EInvoice\Data\CreditNote($saleItem),
            };

            $renderedContent = $this->einvoiceHandler->renderTemplate(
                $template->content,
                $einvoiceData,
                $template->content_type
            );

            $fileName = $this->generateFileName($saleItem, $template->content_type);
            $this->saveToDir($saleItem, $renderedContent, $fileName);
        }
    }

    /**
     * Generate filename for export file
     * 
     * @param object $saleItem Sale item data
     * @param string $contentType File extension
     * @return string Generated filename
     */
    private function generateFileName(object $saleItem, string $contentType): string
    {
        $saleItemNumber = sales_number_format(
            $saleItem->number, 
            $saleItem->number_format, 
            $saleItem->prefix, 
            $saleItem->{$this->config->dateColumn}
        );
        
        return strtolower(slug_it($saleItemNumber) . '.' . $contentType);
    }

    /**
     * Create the final ZIP archive
     */
    private function createZipArchive(): void
    {
        $this->zip($this->config->exportType . 's');
    }

    /**
     * Validate export data and redirect if empty
     * 
     * @param array $data Export data to validate
     */
    private function validateExportData(array $data): void
    {
        if (empty($data)) {
            set_alert('warning', _l('no_data_found_bulk_pdf_export'));
            redirect($this->config->getErrorRedirectURL());
        }
    }

    /**
     * Extract unique years from the last executed query
     * 
     * @return array Array of years from the data
     */
    private function extractYearsFromQuery(): array
    {
        $lastQuery = $this->ci->db->last_query();
        $withoutSelect = Str::after($lastQuery, 'FROM');

        $yearSelectQuery = 'SELECT DISTINCT(YEAR(' . $this->config->dateColumn . ')) as year, `date` FROM' . 
            str_replace('ORDER BY ' . $this->config->dateColumn, 'ORDER BY year', $withoutSelect);

        return $this->ci->db->query($yearSelectQuery)->result_array();
    }

    /**
     * Create year-based subdirectories for export organization
     * 
     * @param array $years Array of year data
     */
    private function createYearDirectories(array $years): void
    {
        $baseDir = $this->config->directory . '/';

        foreach ($years as $year) {
            $yearDir = $baseDir . $year['year'];
            if (!mkdir($yearDir, 0755, true) && !is_dir($yearDir)) {
                throw new RuntimeException(
                    sprintf('Failed to create year directory "%s"', $yearDir)
                );
            }
        }
    }

    /**
     * Apply date range filters to the database query
     * 
     * Adds WHERE clauses for date filtering based on configuration.
     * Handles both single date and date range queries.
     */
    private function setDateQuery(): void
    {
        if ($this->config->dateFrom && $this->config->dateTo) {
            $dateField = $this->config->dateColumn;
            
            if ($this->config->dateFrom === $this->config->dateTo) {
                // Single date query
                $this->ci->db->where($dateField, $this->config->dateFrom);
            } else {
                // Date range query
                $this->ci->db->where($dateField . ' BETWEEN "' . $this->config->dateFrom . '" AND "' . $this->config->dateTo . '"');
            }
        }
    }


    /**
     * Save export content to appropriate directory structure
     * 
     * @param object $saleItem Sale item object containing date information
     * @param string $content Rendered content to save
     * @param string $fileName Name of the file to create
     */
    private function saveToDir(object $saleItem, string $content, string $fileName): void
    {
        $dateColumn = $this->config->dateColumn;
        $baseDir = $this->config->directory . '/';

        // Handle table.column format
        if (str_contains($dateColumn, '.')) {
            $dateColumn = strafter($dateColumn, '.');
        }

        // Add year subdirectory if date is available
        if (!empty($saleItem->{$dateColumn})) {
            $year = date('Y', strtotime($saleItem->{$dateColumn}));
            $baseDir .= $year . '/';
        }

        $filePath = $baseDir . $fileName;
        $this->writeToFile($filePath, $content);
    }

    /**
     * Write data to file with proper error handling and resource management
     * 
     * @param string $filePath Full path to the file to write
     * @param string $content Content to write to the file
     * @throws RuntimeException if file cannot be created or written
     */
    private function writeToFile(string $filePath, string $content): void
    {
        // Validate input
        if (empty($filePath)) {
            throw new RuntimeException('File path cannot be empty');
        }

        // Ensure proper file protocol
        if (!str_contains($filePath, '://')) {
            $filePath = 'file://' . $filePath;
        } elseif (stream_is_local($filePath) !== true) {
            throw new RuntimeException('Only local file paths are supported: ' . $filePath);
        }

        // Open file for writing
        $fileHandle = fopen($filePath, 'wb');
        if (!$fileHandle) {
            throw new RuntimeException('Unable to create output file: ' . $filePath);
        }

        try {
            // Write content to file
            $bytesWritten = fwrite($fileHandle, $content);
            if ($bytesWritten === false || $bytesWritten !== strlen($content)) {
                throw new RuntimeException('Failed to write complete content to file: ' . $filePath);
            }
        } finally {
            // Always close the file handle
            fclose($fileHandle);
        }
    }

    /**
     * Create and download a ZIP archive containing all exported files
     * 
     * @param string $archiveType Type identifier for the archive name (e.g., 'invoice', 'credit_note')
     */
    public function zip(string $archiveType): void
    {
        if (empty($archiveType)) {
            throw new RuntimeException('Archive type cannot be empty');
        }

        $companyName = get_option('companyname');
        $archiveName = slug_it($companyName) . '-' . $archiveType . '.zip';
        
        $this->ci->zip->read_dir($this->config->directory, false);
        $this->ci->zip->download($archiveName);
        $this->ci->zip->clear_data();
    }
}

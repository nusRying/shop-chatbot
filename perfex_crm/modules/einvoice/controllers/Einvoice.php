<?php

use Perfexcrm\EInvoice\BulkExporter;
use Perfexcrm\EInvoice\BulkExporterConfig;
use Perfexcrm\EInvoice\EinvoiceHandler;
use Perfexcrm\EInvoice\OutputWriter;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Credit_notes_model|null $credit_notes_model
 * @property-read Invoices_model|null     $invoices_model
 * @property-read Templates_model         $templates_model
 */
class Einvoice extends AdminController
{
    public function template($id = ''): void
    {
        $this->app_scripts->add('codemirror-js', module_dir_url('einvoice', 'assets/builds/codemirror.js'));
        $this->app_css->add('codemirror-css', module_dir_url('einvoice', 'assets/builds/codemirror.css'));
        $this->app_scripts->add('einvoice-js', module_dir_url('einvoice', 'assets/builds/template.js'));

        $data = ['template' => null];
        if ($id !== '') {
            $this->load->model('templates_model');
            $template = $this->templates_model->find($id);
            if ($template === null || $template->type !== 'einvoice') {
                show_404();
            }
            $data['template'] = $template;
        }
        $data['title'] = _l('settings_einvoice_templates');
        $this->load->view('einvoice/template', $data);
    }

    public function validate_template_ajax(): void
    {
        if (! $this->input->is_ajax_request() || ! is_admin()) {
            ajax_access_denied();
        }

        $content     = $this->input->post('content', false) ?? '';
        $contentType = $this->input->post('content_type') ?? '';

        $validation = $this->validateTemplate($contentType, $content);

        if ($validation['valid']) {
            echo json_encode([
                'success' => true,
                'message' => _l('template_validation_success'),
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $validation['error'],
            ]);
        }
    }

    public function validate_and_save($id = null): void
    {
        if (! is_admin()) {
            access_denied();
        }

        // Only handle AJAX requests
        if (! $this->input->is_ajax_request()) {
            show_404();

            return;
        }

        $content     = $this->input->post('content', false) ?? '';
        $contentType = $this->input->post('content_type') ?? '';

        $validation = $this->validateTemplate($contentType, $content);
        if (! $validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => $validation['error'],
            ]);

            return;
        }

        $data['name']         = $this->input->post('name');
        $data['addedfrom']    = get_staff_user_id();
        $data['type']         = 'einvoice';
        $data['content']      = $content;
        $data['content_type'] = $contentType;

        $this->load->model('templates_model');

        if (is_numeric($id)) {
            $template = $this->templates_model->find($id);
            if (! $template) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('access_denied'),
                ]);

                return;
            }
            $success = $this->templates_model->update($id, $data);
            $message = _l('template_updated');
        } else {
            $success = $this->templates_model->create($data);
            $message = _l('template_added');
            $id      = $success; // For new templates, success returns the ID
        }

        if ($success) {
            echo json_encode([
                'success'  => true,
                'message'  => $message,
                'redirect' => admin_url('settings?group=einvoice'),
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => _l('something_went_wrong'),
            ]);
        }
    }

    private function validateTemplate(string $type, string $content)
    {
        if (empty(trim($content))) {
            return [
                'valid' => false,
                'error' => _l('template_content_required'),
            ];
        }

        try {
            $content = (new EinvoiceHandler())->renderTemplate($content, [], $type);
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => _l('einvoice_template_invalid_mustache') . ' Error: ' . $e->getMessage(),
            ];
        }

        // Validate based on content type
        if (strtoupper($type) === 'JSON') {
            // Clear any previous JSON errors
            json_decode('{}'); // Clear state

            // JSON validation
            $decoded   = json_decode($content);
            $jsonError = json_last_error();

            if ($jsonError !== JSON_ERROR_NONE) {
                return [
                    'valid' => false,
                    'error' => _l('einvoice_template_invalid_json') . ' Error: ' . json_last_error_msg(),
                ];
            }

            // Check if content is just whitespace or empty after decoding
            if ($decoded === null && trim($content) !== 'null') {
                return [
                    'valid' => false,
                    'error' => _l('einvoice_template_invalid_json'),
                ];
            }
        } else {
            // XML validation (default)
            $dom = new DOMDocument();

            // Suppress errors with @ to allow controlled error handling
            libxml_use_internal_errors(true);

            $isValid = $dom->loadXML($content);
            if (! $isValid) {
                $errors       = libxml_get_errors();
                $errorMessage = _l('einvoice_template_invalid_xml');
                if (! empty($errors)) {
                    $errorMessage .= ' Error: ' . $errors[0]->message;
                }
                libxml_clear_errors();

                return [
                    'valid' => false,
                    'error' => $errorMessage,
                ];
            }
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    public function output(string $relType, int $relId): void
    {
        $this->load->model('templates_model');

        $template = $this->templates_model->find(
            get_option('einvoice_default_' . $relType . '_template')
        );

        if (! $template) {
            set_alert('warning', _l('einvoice_no_template_set'));
            redirect(admin_url("{$relType}s#{$relId}"));
        }

        $this->load->model('invoices_model');
        $this->load->model('credit_notes_model');

        switch ($relType) {
            case 'invoice':
                $model = $this->invoices_model->get($relId);
                break;

            case 'credit_note':
                $model = $this->credit_notes_model->get($relId);
                break;

            default:
                show_404();

                return;
        }

        $einvoiceData = match ($relType) {
            'invoice'     => new Perfexcrm\EInvoice\Data\Invoice($model),
            'credit_note' => new Perfexcrm\EInvoice\Data\CreditNote($model),
        };

        $handler = new EinvoiceHandler();

        // Determine output format based on template content type
        $fileExtension = strtoupper($template->content_type) === 'JSON' ? 'json' : 'xml';

        $output = $handler->renderTemplate($template->content, $einvoiceData, $template->content_type);

        /** TODO: use invoice/credit note number formatted as filename */
        $filename = "{$relType}_{$relId}.{$fileExtension}";

        if ($this->input->get('output_type') === 'view') {
            OutputWriter::stream($filename, $output, $template->content_type);
        } else {
            OutputWriter::download($filename, $output, $template->content_type);
        }
    }

    public function export(): void
    {
        if (staff_cant('bulk_export', 'einvoice_module')) {
            access_denied();
        }

        if ($this->input->post()) {
            $exportType    = $this->input->post('export_type');
            $hasPermission = match ($exportType) {
                'invoice'     => staff_can('view', 'invoices'),
                'credit_note' => staff_can('view', 'credit_notes'),
                default       => false,
            };
            $config = new BulkExporterConfig(
                $exportType,
                $hasPermission,
                $this->input->post('date-from'),
                $this->input->post('date-to'),
                $this->input->post($exportType . 's_export_status'),
            );

            $bulkExporter = new BulkExporter($config);
            $bulkExporter->export();

            return;
        }

        $data['features'] = [
            'invoice'     => _l('invoices'),
            'credit_note' => _l('credit_notes'),
        ];
        $data['title']              = _l('einvoice_module_bulk_export');
        $data['invoiceStatuses']    = $this->invoices_model->get_statuses();
        $data['creditNoteStatuses'] = $this->credit_notes_model->get_statuses();

        $this->load->view('einvoice/bulk_export', $data);
    }
}

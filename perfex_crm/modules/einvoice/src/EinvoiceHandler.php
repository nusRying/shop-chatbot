<?php

namespace Perfexcrm\EInvoice;

use Exception;
use JsonSerializable;
use Mustache_Engine;
use Mustache_Loader_StringLoader;

class EinvoiceHandler
{
    private Mustache_Engine $mustache;

    public function __construct()
    {
        $options = [
            'cache'            => TEMP_FOLDER . 'mustache',
            'loader'           => new Mustache_Loader_StringLoader(),
            'charset'          => 'UTF-8',
            'strict_callables' => true,
        ];

        if (ENVIRONMENT === 'development') {
            $options['logger'] = new AppLogger();
        }

        $this->mustache = new Mustache_Engine($options);
    }

    /**
     * Render template replacing all placeholders with data
     *
     * @param string           $template Template with mustache placeholders
     * @param JsonSerializable $data     Data object with document information
     * @param string           $format   Output format ('xml' or 'json')
     *
     * @return string Rendered content
     */
    public function renderTemplate(string $template, JsonSerializable|array $data, string $format): string
    {
        $format = strtolower($format);

        try {
            $data   = hooks()->apply_filters('einvoice_template_data', $data);
            $result = $this->mustache->render(
                $template,
                is_array($data) ? $data : $data->jsonSerialize()
            );

            // Format-specific post-processing
            if ($format === 'xml') {
                // Ensure proper XML formatting with XML declaration if missing
                if (! str_contains($result, '<?xml')) {
                    $result = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $result;
                }

                return hooks()->apply_filters('einvoice_rendered_xml', $result, $data);
            }

            // Remove ending commas in JSON output to ensure valid JSON
            if ($format === 'json') {
                $pattern = '/,(\s*[}\]])/';

                do {
                    $result = preg_replace($pattern, '$1', $result, -1, $count);
                } while ($count > 0);
            }

            return hooks()->apply_filters('einvoice_rendered_json', $result, $data);
        } catch (Exception $e) {
            if ($format === 'xml') {
                log_activity('E-Invoice rendering error: ' . $e->getMessage());

                return $this->generateErrorXml($e->getMessage());
            }

            log_activity('E-Invoice JSON rendering error: ' . $e->getMessage());

            return $this->generateErrorJson($e->getMessage());
        }
    }

    /**
     * Generate error XML when template rendering fails
     *
     * @param string $errorMessage The error message
     *
     * @return string Error XML
     */
    private function generateErrorXml(string $errorMessage): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<error>' . htmlspecialchars($errorMessage, ENT_XML1, 'UTF-8') . '</error>';
    }

    /**
     * Generate error JSON when template rendering fails
     *
     * @param string $errorMessage The error message
     *
     * @return string Error JSON
     */
    private function generateErrorJson(string $errorMessage): string
    {
        return json_encode(['error' => $errorMessage]);
    }

    public function getMustache(): Mustache_Engine
    {
        return $this->mustache;
    }
}

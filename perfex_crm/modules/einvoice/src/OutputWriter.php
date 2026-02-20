<?php

namespace Perfexcrm\EInvoice;
defined('BASEPATH') or exit('No direct script access allowed');

class OutputWriter
{
    private static function defaultHeaders(string $name, string $data): void
    {
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            // the content length may vary if the server is using compression
            header('Content-Length: ' . strlen($data));
        }
    }

    private static function getContentType(string $format): string
    {
        return match (strtolower($format)) {
            'json' => 'application/json',
            'xml' => 'text/xml',
            default => 'text/plain',
        };
    }

    public static function download(string $name, string $data, string $format = 'xml'): void
    {
        self::defaultHeaders($name, $data);

        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename($name)) . '"; ' . 'filename*=UTF-8\'\'' . rawurlencode(basename($name)));

        $contentType = self::getContentType($format);

        // force download dialog
        if (!str_contains(PHP_SAPI, 'cgi')) {
            header('Content-Type: application/force-download');
            header('Content-Type: application/octet-stream', false);
            header('Content-Type: application/download', false);
            header("Content-Type: {$contentType}", false);
        } else {
            header("Content-Type: {$contentType}");
        }
        header('Content-Transfer-Encoding: binary');

        echo $data;
    }

    public static function stream(string $name, string $data, string $format = 'xml'): void
    {
        self::defaultHeaders($name, $data);

        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: inline; filename="' . rawurlencode(basename($name)) . '"; ' . 'filename*=UTF-8\'\'' . rawurlencode(basename($name)));

        $contentType = self::getContentType($format);

        if (!str_contains(PHP_SAPI, 'cgi')) {
            header("Content-Type: {$contentType}", false);
        } else {
            header("Content-Type: {$contentType}");
        }

        echo $data;
    }

    /**
     * @param string $name
     * @param string $data
     * @param string $format
     * @return array{attachment: string, filename: string, type: string}
     */
    public static function emailAttachment(string $name, string $data, string $format = 'xml'): array
    {
        return [
            'attachment' => $data,
            'filename' => $name,
            'type' => self::getContentType($format),
        ];
    }
}
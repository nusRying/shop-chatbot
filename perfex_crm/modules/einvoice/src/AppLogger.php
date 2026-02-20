<?php

namespace Perfexcrm\EInvoice;

use Mustache_Logger;

class AppLogger extends \Mustache_Logger_AbstractLogger
{
    public function log($level, $message, array $context = array()): void
    {
        $ciLevel = match ($level) {
            Mustache_Logger::ALERT, Mustache_Logger::EMERGENCY, Mustache_Logger::WARNING, Mustache_Logger::CRITICAL, Mustache_Logger::ERROR => 'ERROR',
            Mustache_Logger::INFO, Mustache_Logger::NOTICE => 'INFO',
            Mustache_Logger::DEBUG => 'DEBUG',
            default => 'ALL',
        };

        log_message($ciLevel, $message);
    }
}
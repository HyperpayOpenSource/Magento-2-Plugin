<?php

namespace Hyperpay\Extension\Logger;
use Magento\Framework\App\ObjectManager;

class HyperpayLogger
{
    protected $logFile;

    public function __construct($fileName = 'hyperpay_extension.log')
    {
        $this->logFile = BP . '/var/log/' . $fileName;
    }

    public function log($message)
    {
        $date = date('Y-m-d H:i:s');
        $message = "[$date] $message" . PHP_EOL;
        file_put_contents($this->logFile, $message, FILE_APPEND);
    }
}

<?php
/**
 * Sentry Writer for the Zend Framework Logger
 */
namespace ZendSentry\Log\Writer;

use Zend\Log\Writer\AbstractWriter;
use Zend\Log\Logger;
use Raven_Client as Raven;

class Sentry extends AbstractWriter
{
    /**
     * Translates Zend Framework log levels to Raven log levels.
     */
    private $logLevels = array(
        'DEBUG'     => Raven::DEBUG,
        'INFO'      => Raven::INFO,
        'NOTICE'    => Raven::INFO,
        'WARN'      => Raven::WARNING,
        'ERR'       => Raven::ERROR,
        'CRIT'      => Raven::FATAL,
        'ALERT'     => Raven::FATAL,
        'EMERG'     => Raven::FATAL,
    );

    protected $raven;

    public function __construct(Raven $raven, $options = null)
    {
        $this->raven = $raven;
        parent::__construct($options);
    }

    /**
     * Write a message to the log
     *
     * @param array $event log data event
     * @return void
     */
    protected function doWrite(array $event)
    {
        $extra['timestamp'] = $event['timestamp'];
        $this->raven->captureMessage($event['message'], array(), $this->logLevels[$event['priorityName']], false, $event['extra']);
    }
}
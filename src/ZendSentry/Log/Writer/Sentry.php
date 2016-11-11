<?php

/**
 * cloud solutions ZendSentry
 *
 * This source file is part of the cloud solutions ZendSentry package
 *
 * @package    ZendSentry\Log\Writer\Sentry
 * @license    New BSD License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2013, cloud solutions OÜ
 */

namespace ZendSentry\Log\Writer;

use Zend\Log\Writer\AbstractWriter;
use Raven_Client as Raven;

/**
 * @package    ZendSentry\Log\Wrier\Sentry
 */
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

    /**
     * Sentry constructor.
     *
     * @param Raven $raven
     * @param null  $options
     */
    public function __construct(Raven $raven, $options = null)
    {
        $this->raven = $raven;
        parent::__construct($options);
    }

    /**
     * Write a message to the log
     *
     * @param array $event log data event
     * @return string $eventID the event ID
     */
    protected function doWrite(array $event)
    {
        $extra = array();
        $extra['timestamp'] = $event['timestamp'];
        $eventID = $this->raven->captureMessage($event['message'], array(), $this->logLevels[$event['priorityName']], false, $event['extra']);

        return $eventID;
    }
}
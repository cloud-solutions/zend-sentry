<?php

/**
 * Bright Answer ZendSentry
 *
 * This source file is part of the Bright Answer ZendSentry package
 *
 * @package    ZendSentry\ZendSentry
 * @license    MIT License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2018, Bright Answer OÜ
 */

namespace ZendSentry;

use Raven_Client as RavenClient;
use Raven_ErrorHandler as RavenErrorHandler;

/**
 * @package    ZendSentry\ZendSentry
 */
class ZendSentry
{
    /**
     * @var RavenClient $ravenClient
     */
    private $ravenClient;

    /**
     * @var RavenErrorHandler $ravenErrorHandler
     */
    private $ravenErrorHandler;

    /**
     * @param RavenClient $ravenClient
     * @param RavenErrorHandler $ravenErrorHandler
     */
    public function __construct(RavenClient $ravenClient, RavenErrorHandler $ravenErrorHandler = null)
    {
        $this->ravenClient = $ravenClient;
        $this->setOrLoadRavenErrorHandler($ravenErrorHandler);
    }

    /**
     * @param bool   $callExistingHandler
     * @param int    $errorReporting
     *
     * @return ZendSentry
     */
    public function registerErrorHandler($callExistingHandler = true, $errorReporting = E_ALL): ZendSentry
    {
        $this->ravenErrorHandler->registerErrorHandler($callExistingHandler, $errorReporting);
        return $this;
    }

    /**
     * @param bool $callExistingHandler
     * @return ZendSentry
     */
    public function registerExceptionHandler($callExistingHandler = true): ZendSentry
    {
        $this->ravenErrorHandler->registerExceptionHandler($callExistingHandler);
        return $this;
    }

    /**
     * @param int $reservedMemorySize
     * @return ZendSentry
     */
    public function registerShutdownFunction($reservedMemorySize = 10): ZendSentry
    {
        $this->ravenErrorHandler->registerShutdownFunction($reservedMemorySize);
        return $this;
    }

    /**
     * @param null|RavenErrorHandler $ravenErrorHandler
     */
    private function setOrLoadRavenErrorHandler($ravenErrorHandler): void
    {
        if ($ravenErrorHandler !== null) {
            $this->ravenErrorHandler = $ravenErrorHandler;
        } else {
            $this->ravenErrorHandler = new RavenErrorHandler($this->ravenClient);
        }
    }
}
<?php

/**
 * cloud solutions ZendSentry
 *
 * This source file is part of the cloud solutions ZendSentry package
 *
 * @package    ZendSentry\Module
 * @license    New BSD License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2013, cloud solutions OÜ
 */

namespace ZendSentry;

use Zend\EventManager\EventManager;
use Zend\Mvc\MvcEvent;
use ZendSentry\Mvc\View\Http\ExceptionStrategy as SentryHttpStrategy;
use ZendSentry\Mvc\View\Console\ExceptionStrategy as SentryConsoleStrategy;
use Zend\Mvc\View\Http\ExceptionStrategy;
use Raven_Client as Raven;
use Zend\Log\Logger;

/**
 * Class Module
 *
 * @package ZendSentry
 */
class Module
{
    /**
     * Translates Zend Framework log levels to Raven log levels.
     */
    private $logLevels = array(
        7 => Raven::DEBUG,
        6 => Raven::INFO,
        5 => Raven::INFO,
        4 => Raven::WARNING,
        3 => Raven::ERROR,
        2 => Raven::FATAL,
        1 => Raven::FATAL,
        0 => Raven::FATAL,
    );

    /**
     * @var Raven $ravenClient
     */
    protected $ravenClient;

    /**
     * @var ZendSentry $zendSentry
     */
    protected $zendSentry;

    /**
     * @var $config
     */
    protected $config;

    /**
     * @var EventManager $eventManager
     */
    protected $eventManager;

    /**
     * @param MvcEvent $event
     */
    public function onBootstrap(MvcEvent $event)
    {
        // Setup RavenClient (provided by Sentry) and Sentry (provided by this module)
        $this->config = $event->getApplication()->getServiceManager()->get('Config');

        if (!$this->config['zend-sentry']['use-module']) {
            return;
        }

        if (isset($this->config['zend-sentry']['raven-config']) && is_array($this->config['zend-sentry']['raven-config'])) {
            $ravenConfig = $this->config['zend-sentry']['raven-config'];
        } else {
            $ravenConfig = array();
        }

        $sentryApiKey = $this->config['zend-sentry']['sentry-api-key'];
        $ravenClient = new Raven($sentryApiKey, $ravenConfig);

        // Register the RavenClient as a application wide service
        /** @noinspection PhpUndefinedMethodInspection */
        $event->getApplication()->getServiceManager()->setService('raven', $ravenClient);
        $this->ravenClient = $ravenClient;
        $this->zendSentry = new ZendSentry($ravenClient);

        // Get the eventManager and set it as a member for convenience
        $this->eventManager = $event->getApplication()->getEventManager();

        // If ZendSentry is configured to use the custom logger, attach the listener
        if ($this->config['zend-sentry']['attach-log-listener']) {
            $this->setupBasicLogging($event);
        }

        // If ZendSentry is configured to log exceptions, a few things need to be set up
        if ($this->config['zend-sentry']['handle-exceptions']) {
            $this->setupExceptionLogging($event);
        }

        // If ZendSentry is configured to log errors, register it as error handler
        if ($this->config['zend-sentry']['handle-errors']) {
            $errorReportingLevel = (isset($this->config['zend-sentry']['error-reporting'])) ? $this->config['zend-sentry']['error-reporting'] : -1;
            $this->zendSentry->registerErrorHandler($this->config['zend-sentry']['call-existing-error-handler'], $errorReportingLevel);
        }

        // If ZendSentry is configured to log shutdown errors, register it
        if ($this->config['zend-sentry']['handle-shutdown-errors']) {
            $this->zendSentry->registerShutdownFunction();
        }

        // If ZendSentry is configured to log Javascript errors, add needed scripts to the view
        if ($this->config['zend-sentry']['handle-javascript-errors']) {
            $this->setupJavascriptLogging($event);
        }
    }

    /**
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array('Zend\Loader\StandardAutoloader' => array('namespaces' => array(
            __NAMESPACE__ => __DIR__.'/src/'.__NAMESPACE__,
        )));
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return include __DIR__.'/config/module.config.php';
    }/** @noinspection PhpUnusedParameterInspection */
    /** @noinspection PhpUnusedParameterInspection */

    /**
     * Gives us the possibility to write logs to Sentry from anywhere in the application
     * Doesn't use the ZF compatible Log Writer because we want to return the Sentry event ID
     * ZF Logging doesn't provide the possibility to return values from writers
     *
     * @param MvcEvent $event
     */
    protected function setupBasicLogging(MvcEvent $event)
    {
        // Get the shared event manager and attach a logging listener for the log event on application level
        $sharedManager = $this->eventManager->getSharedManager();
        $raven = $this->ravenClient;
        $logLevels = $this->logLevels;

        $sharedManager->attach('*', 'log', function($event) use ($raven, $logLevels) {
            /** @var $event MvcEvent */
            if (is_object($event->getTarget())) {
                $target = get_class($event->getTarget());
            } else {
                $target = (string) $event->getTarget();
            }
            $message  = $event->getParam('message', 'No message provided');
            $priority = (int) $event->getParam('priority', Logger::INFO);
            $message  = sprintf('%s: %s', $target, $message);
            $tags     = $event->getParam('tags', array());
            $eventID = $raven->captureMessage($message, array(), array('tags' => $tags, 'level' => $logLevels[$priority]));

            return $eventID;
        }, 2);
    }

    /**
     * 1. Registers Sentry as exception handler
     * 2. Replaces the default ExceptionStrategy so Exception that are caught by Zend Framework can still be logged
     *
     * @param MvcEvent $event
     */
    protected function setupExceptionLogging(MvcEvent $event)
    {
        // Register Sentry as exception handler for exception that bubble up to the top
        $this->zendSentry->registerExceptionHandler($this->config['zend-sentry']['call-existing-exception-handler']);

        // Replace the default ExceptionStrategy with ZendSentry's strategy
        if ($event->getApplication()->getServiceManager()->has('HttpExceptionStrategy')) {
            /** @var $exceptionStrategy ExceptionStrategy */
            $exceptionStrategy = $event->getApplication()->getServiceManager()->get('HttpExceptionStrategy');
            $exceptionStrategy->detach($this->eventManager);
        }
        
        // Check if script is running in console
        $exceptionStrategy = (PHP_SAPI == 'cli') ? (new SentryConsoleStrategy()) : (new SentryHttpStrategy());
        $exceptionStrategy->attach($this->eventManager);
        $exceptionStrategy->setDisplayExceptions($this->config['zend-sentry']['display-exceptions']);
        $exceptionStrategy->setDefaultExceptionMessage($this->config['zend-sentry'][(PHP_SAPI == 'cli') ? 'default-exception-console-message' : 'default-exception-message']);

        $ravenClient = $this->ravenClient;

        // Attach an exception listener for the ZendSentry exception strategy, can be triggered from anywhere else too
        $this->eventManager->getSharedManager()->attach('*', 'logException', function($event) use ($ravenClient) {
            /** @var $event MvcEvent */
            $exception = $event->getParam('exception');
            $tags = $event->getParam('tags', array());
            $eventID = $ravenClient->captureException($exception, array('tags' => $tags));
            return $eventID;
        });
    }

    /**
     * Adds the necessary javascript, tries to prepend
     *
     * @param MvcEvent $event
     */
    protected function setupJavascriptLogging(MvcEvent $event)
    {
        $viewHelper = $event->getApplication()->getServiceManager()->get('viewhelpermanager')->get('headscript');
        /** @noinspection PhpUndefinedMethodInspection */
        $viewHelper->offsetSetFile(0, '//cdn.ravenjs.com/3.8.0/raven.min.js');
        $publicApiKey = $this->convertKeyToPublic($this->config['zend-sentry']['sentry-api-key']);
        /** @noinspection PhpUndefinedMethodInspection */
        $viewHelper->offsetSetScript(1, sprintf("Raven.config('%s').install()", $publicApiKey));
    }

    /**
     * @param string $key
     * @return string $publicKey
     */
    private function convertKeyToPublic($key)
    {
        // Find private part
        $start = strpos($key, ':', 6);
        $end = strpos($key, '@');
        $privatePart = substr($key, $start, $end - $start);

        // Replace it with an empty string
        $publicKey = str_replace($privatePart, '', $key);

        return $publicKey;
    }
}

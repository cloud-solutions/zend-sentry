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
use ZendSentry\Log\Writer\Sentry;
use ZendSentry\ZendSentry;

/*
 * @package    ZendSentry\Module
 */
class Module
{
    /**
     * @var Raven $ravenClient
     */
    private $ravenClient;

    /**
     * @var ZendSentry $zendSentry
     */
    private $zendSentry;

    /**
     * @var $config
     */
    private $config;

    /**
     * @var EventManager $eventManager
     */
    private $eventManager;

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

        $sentryApiKey = $this->config['zend-sentry']['sentry-api-key'];
        $this->ravenClient = new Raven($sentryApiKey);
        $this->zendSentry = new ZendSentry($this->ravenClient);

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
            $this->zendSentry->registerErrorHandler($this->config['zend-sentry']['call-existing-error-handler']);
        }

        // If ZendSentry is configured to log shutdown errors, register it
        if ($this->config['zend-sentry']['handle-shutdown-errors']) {
            $this->zendSentry->registerShutdownFunction();
        }

        // If ZendSentry is configured to log Javascript errors, add needed scripts to the view
        if ($this->config['zend-sentry']['handle-javascript-errors']) {
            $this->setupJavascriptLogging($event);
        }

        // If ZendSentry is configured to log slow queries, register the respective plugin
        if ($this->config['zend-sentry']['log-slow-queries']) {
            $this->setupSlowQueryLogging($event);
        }

    }

    public function getAutoloaderConfig()
    {
        return array('Zend\Loader\StandardAutoloader' => array('namespaces' => array(
            __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
        )));
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Gives us the possibility to write logs to Sentry from anywhere in the application
     * Doesn't use the ZF compatible Log Writer because we want to return the Sentry event ID
     * ZF Logging doesn't provide the possibility to return values from writers
     *
     * @param MvcEvent $event
     */
    private function setupBasicLogging(MvcEvent $event)
    {
        // Get the shared event manager and attach a logging listener for the log event on application level
        $sharedManager = $this->eventManager->getSharedManager();
        $raven = $this->ravenClient;

        $sharedManager->attach('*', 'log', function($event) use ($raven) {
            /** @var $event MvcEvent */
            if (is_object($event->getTarget())) {
                $target   = get_class($event->getTarget());
            } else {
                $target = (string) $event->getTarget();
            }
            $message  = $event->getParam('message', 'No message provided');
            $priority = (int) $event->getParam('priority', Logger::INFO);
            $message  = sprintf('%s: %s', $target, $message);
            $eventID = $raven->captureMessage($message, null, $priority);

            return $eventID;
        }, 2);
    }

    /**
     * Log slow queries to Sentry
     *
     * @param MvcEvent $event
     * @todo implement
     */
    private function setupSlowQueryLogging(MvcEvent $event)
    {
        // Setup the Zend Logger with our Sentry Writer
        $logger      = new Logger;
        $writer      = new Sentry($this->ravenClient);
        $logger->addWriter($writer);

        // Get the shared event manager and attach a listener to the finish event
        $sharedManager = $this->eventManager->getSharedManager();

        $sharedManager->attach('*', 'finish', function($event) use ($logger) {
            $logger->log(7, 'Logging of slow queries with ZendSentry is not implemented yet, you want to turn it off!');
        });
    }

    /**
     * 1. Registers Sentry as exception handler
     * 2. Replaces the default ExceptionStrategy so Exception that are caught by Zend Framework can still be logged
     *
     * @param MvcEvent $event
     */
    private function setupExceptionLogging(MvcEvent $event)
    {
        // Register Sentry as exception handler for exception that bubble up to the top
        $this->zendSentry->registerExceptionHandler($this->config['zend-sentry']['call-existing-exception-handler']);

        // Replace the default ExceptionStrategy with ZendSentry's strategy
        /** @var $exceptionStrategy ExceptionStrategy */
        $exceptionStrategy = $event->getApplication()->getServiceManager()->get('ViewManager')->getExceptionStrategy();
        $exceptionStrategy->detach($this->eventManager);
        // Check if script is running in console
        $exceptionStrategy = (PHP_SAPI == 'cli') ? (new SentryConsoleStrategy()) : (new SentryHttpStrategy());
        $exceptionStrategy->attach($this->eventManager);
        $exceptionStrategy->setDisplayExceptions($this->config['zend-sentry']['display-exceptions']);

        $ravenClient = $this->ravenClient;
        // Attach an exception listener for the ZendSentry exception strategy, can be triggered from anywhere else too
        $this->eventManager->getSharedManager()->attach('*', 'logException', function($event) use ($ravenClient) {
            /** @var $event MvcEvent */
            $exception = $event->getParam('exception');
            $eventID = $ravenClient->captureException($exception);
            return $eventID;
        });
    }

    /**
     * Adds the necessary javascript, tries to prepend
     *
     * @param MvcEvent $event
     */
    private function setupJavascriptLogging(MvcEvent $event)
    {
        $viewHelper = $event->getApplication()->getServiceManager()->get('viewhelpermanager')->get('headscript');
        $viewHelper->offsetSetFile(0, '//d3nslu0hdya83q.cloudfront.net/dist/1.0/raven.min.js');
        $publicApiKey = $this->convertKeyToPublic($this->config['zend-sentry']['sentry-api-key']);
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

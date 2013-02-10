<?php

namespace ZendSentry;

use Zend\EventManager\EventManager;
use Zend\Mvc\MvcEvent;
use ZendSentry\Mvc\View\Http\ExceptionStrategy as SentryStrategy;
use Zend\Mvc\View\Http\ExceptionStrategy;
use Raven_Client as Raven;
use Zend\Log\Logger;
use ZendSentry\Log\Writer\Sentry;
use ZendSentry\ZendSentry;

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
        $sentryApiKey = $this->config['zend-sentry']['sentry_api_key'];
        $this->ravenClient = new Raven($sentryApiKey);
        $this->zendSentry = new ZendSentry($this->ravenClient);

        // Get the eventManager and set it as a member for convenience
        $this->eventManager = $event->getApplication()->getEventManager();

        // Setup the possibility to send custom logs to Sentry
        $this->setupBasicLogging($event);

        // If ZendSentry is configured to log exceptions, a few things need to be set up
        if ($this->config['zend-sentry']['handle-exceptions']) {
            $this->setupExceptionLogging($event);
        }

        // If ZendSentry is configured to log errors, register it as error handler
        if ($this->config['zend-sentry']['handle-errors']) {
            $this->zendSentry->registerErrorHandler();
        }

        // If ZendSentry is configured to log shutdown errors, register it
        if ($this->config['zend-sentry']['handle-shutdown-errors']) {
            $this->zendSentry->registerShutdownFunction();
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
     *
     * @param MvcEvent $event
     */
    private function setupBasicLogging(MvcEvent $event)
    {
         // Setup the Zend Logger with our Sentry Writer
        $logger      = new Logger;
        $writer      = new Sentry($this->ravenClient);
        $logger->addWriter($writer);

        // Get the shared event manager and attach a logging listener for the log event on application level
        $sharedManager = $this->eventManager->getSharedManager();

        $sharedManager->attach('*', 'log', function($event) use ($logger) {
            /** @var $event MvcEvent */
            $target   = get_class($event->getTarget());
            $message  = $event->getParam('message', 'No message provided');
            $priority = (int) $event->getParam('priority', Logger::INFO);
            $message  = sprintf('%s: %s', $target, $message);
            $logger->log($priority, $message);
        }, 2);
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
        $this->zendSentry->registerExceptionHandler();

        // Replace the default ExceptionStrategy with ZendSentry's strategy
        /** @var $exceptionStrategy ExceptionStrategy */
        $exceptionStrategy = $event->getApplication()->getServiceManager()->get('ViewManager')->getExceptionStrategy();
        $exceptionStrategy->detach($this->eventManager);
        $exceptionStrategy = new SentryStrategy;
        $exceptionStrategy->attach($this->eventManager);
        $exceptionStrategy->setDisplayExceptions($this->config['zend-sentry']['display-exceptions']);

        $ravenClient = $this->ravenClient;
        // Attach an exception listener for the ZendSentry exception strategy, can be triggered from anywhere else too
        $this->eventManager->getSharedManager()->attach('*', 'logException', function($event) use ($ravenClient) {
            /** @var $event MvcEvent */
            $exception = $event->getParam('exception');
            $ravenClient->captureException($exception);
        });
    }
}
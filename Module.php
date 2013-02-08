<?php

namespace ZendSentry;

use Zend\EventManager\StaticEventManager;
use \Zend\Mvc\MvcEvent;
use Raven_Client as Raven;
use Zend\Log\Logger;
use ZendSentry\Log\Writer\Sentry;
use ZendSentry\ZendSentry;

class Module
{
    public function onBootstrap(MvcEvent $event)
    {
        // Setup the Zend Logger with our Sentry Writer
        $config = $event->getApplication()->getServiceManager()->get('Config');
        $sentryApiKey = $config['zend-sentry']['sentry_api_key'];
        $logger = new Logger;
        $ravenClient = new Raven($sentryApiKey);
        $writer = new Sentry($ravenClient);
        $logger->addWriter($writer);

        // Attach a logging listener for the log event on application level
        $events = StaticEventManager::getInstance();
        $events->attach('*', 'log', function($event) use ($logger) {
            $target = get_class($event->getTarget());
            $message = $event->getParam('message', 'No message provided');
            $priority = (int) $event->getParam('priority', 'No priority provided');
            $message = sprintf('%s: %s', $target, $message);
            $logger->log($priority, $message);
        });

        // Use the ZendSentry convenience methods to register different handlers if enabled in the module config
        $zendSentry = new ZendSentry($ravenClient);

        if ($config['zend-sentry']['handle-errors']) {
            $zendSentry->registerErrorHandler();
        }

        if ($config['zend-sentry']['handle-exceptions']) {
            $zendSentry->registerExceptionHandler();
        }

        //trigger_error('an error triggered to see if the new config vars work', E_USER_ERROR);
        //throw new \RuntimeException ('it also works if setup in the module config');

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
}
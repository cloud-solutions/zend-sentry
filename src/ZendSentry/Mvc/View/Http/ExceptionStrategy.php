<?php

/**
 * cloud solutions ZendSentry
 *
 * This source file is part of the cloud solutions ZendSentry package
 *
 * @package    ZendSentry\Mvc\View\Http\ExceptionStrategy
 * @license    New BSD License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2011, cloud solutions OÜ
 */

namespace ZendSentry\Mvc\View\Http;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\View\Model\ViewModel;

/**
 * For the moment, this is just an augmented copy of the default ZF ExceptionStrategy
 * This is on purpose despite the duplication of code until the module stabilizes and it's clear what need exactly
 *
 * @package    ZendSentry\Mvc\View\Http\ExceptionStrategy
 */
class ExceptionStrategy implements ListenerAggregateInterface
{
    /**
     * Display exceptions?
     * @var bool
     */
    protected $displayExceptions = false;
    
    /**
     * Default Exception Message
     * @var string
     */
    protected $defaultExceptionMessage = 'Oh no. Something went wrong, but we have been notified. If you are testing, tell us your eventID: %s';

    /**
     * Name of exception template
     * @var string
     */
    protected $exceptionTemplate = 'error/index';

    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * Attach the aggregate to the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'prepareExceptionViewModel'));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'prepareExceptionViewModel'));
    }

    /**
     * Detach aggregate listeners from the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Flag: display exceptions in error pages?
     *
     * @param  bool $displayExceptions
     * @return ExceptionStrategy
     */
    public function setDisplayExceptions($displayExceptions)
    {
        $this->displayExceptions = (bool) $displayExceptions;
        return $this;
    }

    /**
     * Should we display exceptions in error pages?
     *
     * @return bool
     */
    public function displayExceptions()
    {
        return $this->displayExceptions;
    }

    /**
     * Set the default exception message
     *
     * @param string $defaultExceptionMessage
     *
     * @return $this
     */
    public function setDefaultExceptionMessage($defaultExceptionMessage)
    {
        $this->defaultExceptionMessage = $defaultExceptionMessage;
        return $this;
    }

    /**
     * Set the exception template
     *
     * @param  string $exceptionTemplate
     * @return ExceptionStrategy
     */
    public function setExceptionTemplate($exceptionTemplate)
    {
        $this->exceptionTemplate = (string) $exceptionTemplate;
        return $this;
    }

    /**
     * Retrieve the exception template
     *
     * @return string
     */
    public function getExceptionTemplate()
    {
        return $this->exceptionTemplate;
    }

    /**
     * Create an exception view model, and set the HTTP status code
     *
     * @param  MvcEvent $e
     * @return void
     */
    public function prepareExceptionViewModel(MvcEvent $e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        // Proceed to showing an error page with or without exception
        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                // Specifically not handling these
                return;

            case Application::ERROR_EXCEPTION:
            default:
                // check if there really is an exception
                // zf2 also throw normal errors, for example: error-route-unauthorized
                // if there is no exception we have nothing to log
                if ($e->getParam('exception') == null) {
                    return;
                }

                // Log exception to sentry by triggering an exception event
                $eventID = $e->getApplication()->getEventManager()->trigger('logException', $this, array('exception' => $e->getParam('exception')));

                $model = new ViewModel(array(
                    'message'            => sprintf($this->defaultExceptionMessage, $eventID->last()),
                    'exception'          => $e->getParam('exception'),
                    'display_exceptions' => $this->displayExceptions(),
                ));
                $model->setTemplate($this->getExceptionTemplate());
                $e->setResult($model);

                $response = $e->getResponse();
                if (!$response) {
                    $response = new HttpResponse();
                    $response->setStatusCode(500);
                    $e->setResponse($response);
                } else {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        /** @noinspection PhpUndefinedMethodInspection */
                        $response->setStatusCode(500);
                    }
                }

                break;
        }
    }
}

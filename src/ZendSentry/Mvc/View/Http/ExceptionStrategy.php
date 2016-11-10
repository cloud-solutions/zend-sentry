<?php

/**
 * cloud solutions ZendSentry
 *
 * This source file is part of the cloud solutions ZendSentry package
 *
 * @package    ZendSentry\Mvc\View\Http\ExceptionStrategy
 * @license    New BSD License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2016, cloud solutions OÜ
 */

namespace ZendSentry\Mvc\View\Http;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
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
class ExceptionStrategy extends AbstractListenerAggregate
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
    protected $exceptionTemplate = 'error';

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'prepareExceptionViewModel'));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'prepareExceptionViewModel'));
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
     * @param string $defaultExceptionMessage
     * @return self
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
                // ZF also throws normal errors, for example: error-route-unauthorized
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
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        $response->setStatusCode(500);
                    }
                }

                break;
        }
    }
}

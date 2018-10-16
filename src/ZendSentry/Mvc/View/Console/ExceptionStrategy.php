<?php

/**
 * Bright Answer ZendSentry
 *
 * This source file is part of the Bright Answer ZendSentry package
 *
 * @package    ZendSentry\Mvc\View\Console\ExceptionStrategy
 * @license    MIT License {@link /docs/LICENSE}
 * @copyright  Copyright (c) 2016, Bright Answer OÜ
 */

namespace ZendSentry\Mvc\View\Console;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface;
use Zend\View\Model\ConsoleModel;

/**
 * For the moment, this is just an augmented copy of the default ZF ExceptionStrategy
 * This is on purpose despite the duplication of code until the module stabilizes and it's clear what need exactly
 *
 * @package    ZendSentry\Mvc\View\Console\ExceptionStrategy
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
    protected $defaultExceptionMessage = "Oh no. Something went wrong, but we have been notified.\n";

    /**
     * A template for message to show in console when an exception has occurred.
     * @var string|callable
     */
    protected $message = <<<EOT
    ======================================================================
       The application has thrown an exception!
    ======================================================================
     :className
     :message
    ----------------------------------------------------------------------
    :file::line
    :stack
    ======================================================================
       Previous Exception(s):
    ======================================================================
    :previous

EOT;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'prepareExceptionViewModel']);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'prepareExceptionViewModel']);
    }

    /**
     * Flag: display exceptions in error pages?
     *
     * @param  bool $displayExceptions
     * @return ExceptionStrategy
     */
    public function setDisplayExceptions($displayExceptions): ExceptionStrategy
    {
        $this->displayExceptions = (bool) $displayExceptions;
        return $this;
    }

    /**
     * Should we display exceptions in error pages?
     *
     * @return bool
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Get current template for message that will be shown in Console.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set the default exception message
     * @param string $defaultExceptionMessage
     * @return self
     */
    public function setDefaultExceptionMessage($defaultExceptionMessage): self
    {
        $this->defaultExceptionMessage = $defaultExceptionMessage;
        return $this;
    }

    /**
     * Set template for message that will be shown in Console.
     * The message can be a string (template) or a callable (i.e. a closure).
     *
     * The closure is expected to return a string and will be called with 2 parameters:
     *        Exception $exception           - the exception being thrown
     *        boolean   $displayExceptions   - whether to display exceptions or not
     *
     * If the message is a string, one can use the following template params:
     *
     *   :className   - full class name of exception instance
     *   :message     - exception message
     *   :code        - exception code
     *   :file        - the file where the exception has been thrown
     *   :line        - the line where the exception has been thrown
     *   :stack       - full exception stack
     *
     * @param string|callable  $message
     * @return ExceptionStrategy
     */
    public function setMessage($message): ExceptionStrategy
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Create an exception view model, and set the console status code
     *
     * @param  MvcEvent $e
     * @return void
     */
    public function prepareExceptionViewModel(MvcEvent $e): void
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof ResponseInterface) {
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
                // Prepare error message
                $exception = $e->getParam('exception');

                // Log exception to sentry by triggering an exception event
                $e->getApplication()->getEventManager()->trigger('logException', $this, ['exception' => $exception]);

                if (\is_callable($this->message)) {
                    $callback = $this->message;
                    $message = (string) $callback($exception, $this->displayExceptions);
                } elseif ($this->displayExceptions && $exception instanceof \Exception) {
                    /* @var $exception \Exception */
                    $message = str_replace(
                        [
                            ':className',
                            ':message',
                            ':code',
                            ':file',
                            ':line',
                            ':stack',
                            ':previous',
                        ], [
                        \get_class($exception),
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getFile(),
                        $exception->getLine(),
                        $exception->getTraceAsString(),
                        $exception->getPrevious(),
                    ],
                        $this->message
                    );
                } else {
                    $message = $this->defaultExceptionMessage;
                }

                // Prepare view model
                $model = new ConsoleModel();
                $model->setResult($message);
                $model->setErrorLevel(1);

                // Inject it into MvcEvent
                $e->setResult($model);

                break;
        }
    }
}
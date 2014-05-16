A Zend Framework 2 module that lets you log exceptions, errors or whatever you wish to the Sentry service.

ZendSentry is released under the New BSD License.

The current version of ZendSentry is `1.1.0`.

#Important Changes
- 1.1.0: updated raven dependency to latest (0.8.0), upgrade is recommended
- 1.0.1: updated raven dependency to latest (0.7.1), important if you run pre 7.16.2 curl
- 1.0.0: updated raven depencency to latest (0.7.0), first stable release (has been very stable)
- 0.3.1: dedicated CLI ExceptionStrategy (credits to Mateusz Mirosławski)

#Introduction

##What's Sentry?
[Sentry](https://www.getsentry.com/welcome/) is an online service to which you can log anything including your 
exceptions and errors. Sentry creates nice reports in real time and aggregates your logged data for you.

##What's ZendSentry
It is a module that builds the bridge between your Zend Framework 2 application and the Sentry service. It's extremely
easy to setup and does a lot of things out-of-the-box.

Current features:
* log uncaucht PHP exceptions to Sentry
* log PHP errors to Sentry
* log uncaught Javascript errors to Sentry
* log anything you like to Sentry by triggering registered log listeners
* ZF ExceptionStrategy for Http as well as the CLI (automatic selection)

#Installation

This module is available on [Packagist](https://packagist.org/packages/cloud-solutions/zend-sentry).
In your project's `composer.json` use:

    {   
        "require": {
            "cloud-solutions/zend-sentry": "1.1.0"
    }
    
Run `php composer.phar update` to download it into your vendor folder and setup autoloading.

Now copy `zend-sentry.local.php.dist` to `yourapp/config/autoload/zend-sentry.local.php` and add your Sentry API key.
Then copy `zend-sentry.global.php.dist` to the same place, also removing `.dist`. Adjust settings, if needed.

Add `ZendSentry` to the modules array in your `application.config.php`, preferably as the first module. 

That's it. There's nothing more you need to do, everything works at that stage, [try it](#try-it). Happy logging!

#Usage

Again, you don't need to write a single line of code to make this work. The default settings will make sure Sentry
is registered as both error and exception handler, [try it](#try-it) by triggering and error or throwing around some 
exceptions. You should instantly see them in your Sentry dashboard. ZendSentry also packages its own ExceptionStrategies
to make sure, exceptions ZF would otherwise intercept, are logged.

Additonally, the module registers a log event listener on application level. So you can trigger custom log events from
anywhere in your application. These will be logged using the `Zend\Log\Logger` with a Sentry writter provided by 
this mdule.

In a controller, you may do:

    $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO, 
        'message' => 'I am a message and I have been logged'
    ));

Or you can have the trigger return the Sentry `event_id` for processing:

    $eventID = $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO,
        'message' => 'I am a message and I have been logged'
    ));

Now you can tell your users or API consumers the ID so they can referr to it, e.g. when asking for support.

Make sure to pass `"log"` as the first parameter and `$this` **or** a custom context string as second parameter. 
Keep this consistent as Sentry will use this for grouping your log entries. As the third parameter, 
you want to pass an array with a priority key and a message key. It's best to use the priorities provided 
by the framework. They will be mapped onto Sentry's own priorities.

Besides the fact that uncaught exceptions and errors are automatically logged, you may also log caught or uncaught
exceptions manually by using the respective listener directly:

    try {
        throw new Exception('throw and catch');
    } catch (Exception $exception) {
        $result = $this->getEventManager()->trigger('logException', $this, array('exception' => $exception));

        //get Sentry event_id by retrieving it from the EventManager ResultCollection
        $eventID = $result->last();
    }

#Configuration options

Just for the record, a copy of the actual global configuration options:

    /**
     * Turn ZendSentry off or on as a whole package
     */
    'use-module' => true,

    /**
     * Attach a generic logger event listener so you can log custom messages from anywhere in your app
     */
    'attach-log-listener' => true,
    
    /**
     * Register the Sentry logger as PHP error handler
     */
    'handle-errors' => true,

    /**
     * Should the previously registered error handler be called as well?
     */
    'call-existing-error-handler' => true,

    /**
     * Register Sentry as shutdown error handler
     */
    'handle-shutdown-errors' => true,

    /**
     * Register the Sentry logger as PHP exception handler
     */
    'handle-exceptions' => true,

    /**
     * Should the previously registered exception handler be called as well
     */
    'call-existing-exception-handler' => true,

    /**
     * Should exceptions be displayed on the screen?
     */
    'display-exceptions' => false,

    /**
     * Should Sentry also log javascript errors?
     */
    'handle-javascript-errors' => true,
    
#Try it
A few ideas how to try the different features from a Controller:
    
    // Test logging of PHP errors
    // trigger_error('can I trigger an error from a controller');
    
    // Test logging of PHP exceptions
    // throw new \Exception('Some exception gets logged.');
    
    // Throw a javascript error and see it logged
    // $headScript = $this->getServiceLocator()->get('viewhelpermanager')->get('headscript')
                          ->appendScript("throw new Error('A javascript error should be logged.');");

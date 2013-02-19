ZendSentry
===========

A Zend Framework 2 module that lets you log to the Sentry service.

ZendSentry is released under the New BSD License.

The current version of ZendSentry is `0.1.2`. We consider it a good beta, we're using it in production.

###What's Sentry?
[Sentry](https://www.getsentry.com/welcome/) is an online service to which you can log anything including your 
exceptions and errors. Sentry creates nice reports in real time and aggregates your logged data for you.

###What's ZendSentry
It is a module that builds the bridge between your Zend Framework 2 application and the Sentry service. It's extremely
easy to setup and does a lot of things out-of-the-box.

Current features:
* log PHP exceptions to Sentry
* log PHP errors to Sentry
* log uncaught Javascript errors to Sentry
* log anything to Sentry by triggering a registered log listener

#Installation

This module is available on [Packagist](https://packagist.org/packages/cloud-solutions/zend-sentry).
In your project's `composer.json` use:

    {   
        "require": {
            "cloud-solutions/zend-sentry": "0.1.2"
    }
    
Run `php composer.phar update` to download it into your vendor folder and setup autoloading.

Now copy `zend-sentry.local.php.dist` to `yourapp/config/autoload/zend-sentry.local.php` and add your Sentry API key.
Then copy `zend-sentry.global.php.dist` to the same place, also removing `.dist`. Adjust settings, if needed.

Add `ZendSentry` to the modules array in your `application.config.php`, preferably as the first module. 

That's it. There's nothing more you need to do, everything works at that stage, [try it](#try-it). Happy logging!

#Usage

Again, you don't need to write a single line of code to make this work. The default settings will make sure Sentry
is registered as both error and exception handler, [try it](#try-it) by triggering and error or throwing around some 
exceptions. You should instantly see them in your Sentry dashboard. ZendSentry also packages its own ExceptionStrategy 
to make sure, exceptions ZF would otherwise intercept, are logged. 


Additonally, the module registers a log event listener on application level. So you can trigger custom log events from
anywhere in your application. These will be logged using the `Zend\Log\Logger` with a Sentry writter provided by 
this mdule.

In a controller, you may do:

    $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO, 
        'message' => 'if this works, I am the hero of the night'
    ));

Make sure to pass `"log"` as the first parameter and `$this` as second parameter. As the third parameter, 
you want to pass an array with a priority key and a message key. It's best to use the priorities provided 
by the framework. They will be mapped onto Sentry's own priorities.

#Configuration options

Just for the record, a copy of the actual global configuration options:

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

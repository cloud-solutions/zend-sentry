A Zend Framework 3 module that lets you log exceptions, errors or whatever you wish to the Sentry.io service.

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/cloud-solutions/zend-sentry/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/cloud-solutions/zend-sentry/?branch=master) [![Build Status](https://travis-ci.org/cloud-solutions/zend-sentry.svg?branch=master)](https://travis-ci.org/cloud-solutions/zend-sentry)

ZendSentry is released under the MIT License.

The current version of ZendSentry for ZF3 is `3.7.0`. It supports Zend Framework >= 3.0. For other versions see tags in the 1.* series as well as 2.* series. **NB!** We are not supporting the old branches anymore.

# Recent Changes
- 3.7.0: Add option to configure used Ravenjs version, upgrade Ravenjs to `3.27.0`
- 3.6.0: Add static setter to inject CSP nonce (temporary solution)
- 3.5.0: Add support for new Sentry DSN, deprecate old DSN for later removal
- 3.4.0: Add possibility to switch off usage of raven-js CDN
- 3.3.0: Add possibility to pass config options to ravenjs

# Introduction

## What's Sentry?
[Sentry](https://www.getsentry.com/welcome/) is an online service to which you can log anything including your 
exceptions and errors. Sentry creates nice reports in real time and aggregates your logged data for you.

## What's ZendSentry
It is a module that builds the bridge between your Zend Framework 3 application and the Sentry.io service. It's extremely
easy to setup and does a lot of things out-of-the-box.

Features and capabilities:

* log uncatched PHP exceptions to Sentry automagically.
* log PHP errors to Sentry automagically.
* log uncatched Javascript errors to Sentry automagically.
* capture Exceptions to Sentry by triggering an event listener.
* log anything you like to Sentry by triggering an event listener.
* ZF ExceptionStrategy for Http as well as the CLI (automatic selection).
* log actions return the Sentry event_id.
* Raven is registered as a Service.
* override Raven config defaults.
* pass config options to ravenjs.
* configure error messages.
* inject a Content-Security-Policy` nonce for the inline script rendering. Makes it possible for you to create a CSP without `unsafe-inline` as script source.

# Installation

This module is available on [Packagist](https://packagist.org/packages/cloud-solutions/zend-sentry).
In your project's `composer.json` use:

    {   
        "require": {
            "cloud-solutions/zend-sentry": "3.7.0"
    }
    
Run `php composer.phar update` to download it into your vendor folder and setup autoloading.

Now copy `zend-sentry.local.php.dist` to `yourapp/config/autoload/zend-sentry.local.php` and add your Sentry API key.
Then copy `zend-sentry.global.php.dist` to the same place, also removing `.dist`. Adjust settings, if needed.

Add `ZendSentry` to the modules array in your `application.config.php`, preferably as the first module. 

That's it. There's nothing more you need to do, everything works at that stage, [try it](#try-it). Happy logging!

# Basic Automatic Usage

Again, you don't need to write a single line of code to make this work. The default settings will make sure Sentry
is registered as both error and exception handler, [try it](#try-it) by triggering and error or throwing around some 
exceptions. You should instantly see them in your Sentry dashboard. ZendSentry also packages its own ExceptionStrategies
to make sure, exceptions ZF would otherwise intercept, are logged.

# Manual Usage
Additonally, the module registers a log event listener on application level. So you can trigger custom log events from
anywhere in your application.

In a controller, you may do:

    $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO, 
        'message' => 'I am a message and I have been logged'
    ));

Or you can store the returned Sentry `event_id` for processing:

    $eventID = $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO,
        'message' => 'I am a message and I have been logged'
    ));

Now you can tell your users or API consumers the ID so they can referr to it, e.g. when asking for support.

Make sure to pass `"log"` as the first parameter and `$this` **or** a custom context string as second parameter. 
Keep this consistent as Sentry will use this for grouping your log entries. As the third parameter, 
you want to pass an array with a priority key and a message key. It's best to use the priorities provided 
by the Zend Framework. They will be mapped onto Sentry's own priorities.

Besides the fact that uncaught exceptions and errors are automatically logged, you may also log caught or uncaught
exceptions manually by using the respective listener directly:

    try {
        throw new Exception('throw and catch');
    } catch (Exception $exception) {
        $result = $this->getEventManager()->trigger('logException', $this, array('exception' => $exception));

        //get Sentry event_id by retrieving it from the EventManager ResultCollection
        $eventID = $result->last();
    }

# Using Tags

You can also pass your own tags to Sentry. The service will automatically create filtering and sorting for these tags.
When using the `log` event, you can optionally pass tags like this:

    $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO,
        'message' => 'I am a message with a language tag',
        'tags' => array('language' => 'en'),
        'extra' => array('email' => 'test@test.com'),
    ));

If using the `logException` event manually, you can also pass along tags:

    try {
        throw new Exception('throw and catch with tags');
    } catch (Exception $exception) {
        $this->getEventManager()->trigger('logException', $this, array('exception' => $exception, 'tags' => array('language' => 'fr')));
    }

**NB!** Every tag needs a key and a value.

See how to use tags for automagically logged exceptions below.

# Raven as Service

The module registers the Raven_Client as an application wide service. Usually you don't want to access it directly
because triggering the event listeners leaves you with cleaner code. One example where the direct usage of Raven can
be helpful is for adding user context. For example you might want to do something like this during your bootstrap:

    if ($authenticationService->hasIdentity()) {
        $ravenClient = $this->serviceManager->get('raven');
        $ravenClient->user_context($authenticationService->getIdentity()->userID);
    }

You can also use Raven directly, if you would like to add some tags to the context, which will be sent with every automatic entry.
You might want to do something like this e.g. in your `AbstractActionController::preDispatch()`:

    $serviceManager = $mvcEvent->getApplication()->getServiceManager();
    if ($serviceManager->has('raven')) {
        $ravenClient = $serviceManager->get('raven');
        $ravenClient->tags_context(
            [
                'locale'  => $this->translator()->getLocale(),
            ]
        );
    }

# Injecting a CSP nonce (NB! temporary solution)

If you've already implemented a Content Security Policy in your app, chances are you're using a nonce for dynamic inline javascript. 
If so, you can now inject your nonce into ZendSentry:

    ZendSentry::setCSPNonce(ContentSecurityPolicy::getNonce());
    
... where `ContentSecurityPolicy` is your implementation of that http header.

If you inject a nonce, ZendSentry will add it as an attribute to the Raven loading script. Example:

    <script type="text/javascript" nonce="qlQa7LCu2ZLoVZzpn5s9OJNq7QE=">
        //<![CDATA[
        if (typeof Raven !== 'undefined') Raven.config('https://yourpublickey@sentry.io/5374', []).install()
        //]]>
    </script>
    
Please note that we regard this as a temporary solution. It would be much better for ZendSentry to define its own CSP header.
Right now Zend Framework is not handling multiple CSP headers the right way (see also [this issue](https://github.com/zendframework/zend-http/issues/159) in `zend-http`).

# Configuration options

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
     * Which errors should be reported to sentry (bitmask), e. g. E_ALL ^ E_DEPRECATED
     * Defaults to -1 to report all possible errors (equivalent to E_ALL in >= PHP 5.4)
     */
    'error-reporting' => -1,

    /**
     * Should exceptions be displayed on the screen?
     */
    'display-exceptions' => false,
    
    /**
     * If Exceptions are displayed on screen, this is the default message
     */
    'default-exception-message' => 'Oh no. Something went wrong, but we have been notified. If you are testing, tell us your eventID: %s',
    
    /**
     * If Exceptions are displayed on screen, this is the default message in php cli mode
     */
    'default-exception-console-message' => "Oh no. Something went wrong, but we have been notified.\n",

    /**
     * Should Sentry also log javascript errors?
     */
    'handle-javascript-errors' => true,
    
    /**
     * Should ZendSentry load raven-js via CDN?
     * If you set this to false you'll need to make sure to load raven-js some other way.
     */
    'use-ravenjs-cdn' => true,
    
    /**
     * Change the raven-js version loaded via CDN if you need to downgrade or we're lagging behind with updating.
     * No BC breaks, ZendSentry will set the version if your config is missing the key.
     */
    'ravenjs-version' => '3.27.0',

    /**
     * Alternatively, if not using CDN, you can specify a path or url to raven-js.
     * Set to empty to disable but make sure to load raven-js some other way.
     */
    'ravenjs-source' => '/js/raven.min.js',

    /**
     * Set raven config options for the getsentry/sentry-php package here.
     * Raven has sensible defaults set in Raven_Client, if you need to override them, this is where you can do it.
     */
    'raven-config' => array(),

    /**
     * Set ravenjs config options for the getsentry/raven-js package here.
     * This will be json encoded and passed to raven-js when doing Raven.install().
     */
    'ravenjs-config' => array(),
    
# Try it
A few ideas how to try the different features from a Controller or View:
    
    // Test logging of PHP errors
    // trigger_error('can I trigger an error from a controller');
    
    // Test logging of PHP exceptions
    // throw new \Exception('Some exception gets logged.');
    
    // Throw a javascript error and see it logged (add to view or layout)
    // $headScript->appendScript("throw new Error('A javascript error should be logged.');");

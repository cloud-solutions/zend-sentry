ZendSentry
===========

A Zend Framework 2 module that lets you log to the Sentry service.

**Caution**: This is just pre-beta. It works but don't take my word for it :) Anyways, it's harmless, so just try it...

###What's Sentry?
[Sentry](https://www.getsentry.com/welcome/) is an online service to which you can log anything including your 
exceptions and errors. Sentry creates nice reports in real time and aggregates your logged data for you.

###What's ZendSentry
It is a module that builds the bridge between your Zend Framework 2 application and the Sentry service. It's extremely
easy to setup. Require it with composer, copy the config files into your config/autoload folder, add your Sentry API
key to the local config file and you're good to go.

#Installation

This module is available on [Packagist](https://packagist.org/packages/cloud-solutions/zend-sentry).
In your project's `composer.json` use:

    {   
        "require": {
            "cloud-solutions/zend-sentry": "dev-master"
    }
    
Run `php composer.phar update` to download it into your vendor folder and setup autoloading.

Now copy `zend-sentry.local.php.dist` to `yourapp/config/autoload/zend-sentry.local.php` and add your Sentry API key.
Then copy `zend-sentry.global.php.dist` to the same place, also removing `.dist`. Adjust settings, if needed.

That's it.

#Usage

You don't need to write a single line of code to make this work. It just works. The default settings will make sure a 
`Zend\Log\Logger` instance with the Sentry Writer provided by this module, is registered as both error and exception 
handler, try it by triggering and error or throwing around some exceptions. 
You should instantly see them in your Sentry dashboard.

Additonally, the module registers a log event listener on application level. So you can trigger custom log events from
anywhere in your application.

In a controller, you may do:

    $this->getEventManager()->trigger('log', $this, array(
        'priority' => \Zend\Log\Logger::INFO, 
        'message' => 'if this works, I am the hero of the night'
    ));

Make sure to pass `"log"` as the first parameter and `$this` as second parameter. As the third parameter, 
you want to pass an array with a priority key and a message key. It's best to use the priorities provided 
by the framework. They will be mapped onto Sentry's own priorities.

<?php

namespace ZendSentryTest;

use PHPUnit\Framework\TestCase;
use Zend\Loader\StandardAutoloader;
use ZendSentry\Module as ZendSentryModule;

class ModuleTest extends TestCase
{
    /**
     * @var ZendSentryModule
     */
    private $module;

    public function setUp()
    {
        parent::setUp();
        $this->module = new ZendSentryModule();
    }

    public function testDefaultModuleConfig()
    {
        $expectedConfig = [];

        $actualConfig = $this->module->getConfig();

        $this->assertEquals($expectedConfig, $actualConfig);
    }

    public function testAutoloaderConfig()
    {
        $expectedConfig = [
            StandardAutoloader::class => [
                'namespaces' => [
                    'ZendSentry' => realpath(__DIR__.'/../../src/ZendSentry')
                ]
            ]
        ];

        $actualConfig = $this->module->getAutoloaderConfig();

        $this->assertEquals($expectedConfig, $actualConfig);
    }
}

<?php


use Codeception\Event\SuiteEvent;
use Codeception\Exception\ExtensionException;
use Codeception\Suite;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Symfony\Component\Process\Process;

class HeadlessChromeTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * dockerChrome
     *
     * @var Codeception\Extension\HeadlessChrome
     */
    protected $headlessChrome;

    /**
     * processProphecy
     *
     * @var Process | \Prophecy\Prophecy\ObjectProphecy
     */
    protected $processProphecy;

    /**
     * suiteEventProphecy
     *
     * @var SuiteEvent | \Prophecy\Prophecy\ObjectProphecy
     */
    protected $suiteEventProphecy;

    /**
     * configDefaultsDataProvider
     *
     * @return array
     */
    public function configDefaultsDataProvider()
    {
        return [
            'path should return specific path'                    => [
                'config'         => ['path' => __FILE__],
                'expectedConfig' => ['path' => __FILE__]
            ],
            'verbose should set to false by default'                => [
                'config'         => [],
                'expectedConfig' => ['verbose' => false]
            ],
            'verbose should set to true by config'                  => [
                'config'         => ['verbose' => true],
                'expectedConfig' => ['verbose' => true]
            ],
            'url-base should set to /wd/hub by default'    => [
                'config'         => [],
                'expectedConfig' => ['url-base' => '/wd/hub']
            ],
            'url-base should set to specific string by config'  => [
                'config'         => ['url-base' => '/wd/hub/'],
                'expectedConfig' => ['url-base' => '/wd/hub/']
            ],
            'port should set to default port by default'   => [
                'config'         => [],
                'expectedConfig' => ['port' => 9515]
            ],
            'port should set to specific string by config' => [
                'config'         => ['port' => 9515],
                'expectedConfig' => ['port' => 9515]
            ]
        ];
    }

    /**
     * testInitConfigDefaults
     *
     * @dataProvider configDefaultsDataProvider
     * @param array $config
     * @param array $expectedConfig
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \PHPUnit_Framework_Exception
     * @throws \Prophecy\Exception\Prophecy\ObjectProphecyException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testInitConfigDefaults(array $config, array $expectedConfig)
    {
        $this->headlessChrome = new Codeception\Extension\HeadlessChrome(array_merge(['path' => vfsStream::url('testDir/chromedriver.sh')], $config), [], $this->processProphecy->reveal(), vfsStream::url('testDir/chromedriver.sh'));
        $this->assertArraySubset($expectedConfig, $this->headlessChrome->getConfig());
    }

    /**
     * testInitConfigPathDefaultShouldThrowExceptionIfPathNotExist
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \PHPUnit_Framework_Exception
     * @throws \Prophecy\Exception\Prophecy\ObjectProphecyException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testInitConfigPathDefaultShouldThrowExceptionIfPathNotExist()
    {
        $this->expectException(ExtensionException::class);
        $this->expectExceptionMessage('File not found: /some/missing/path');
        $this->headlessChrome = new Codeception\Extension\HeadlessChrome(['path' => '/some/missing/path'], [], $this->processProphecy->reveal(), vfsStream::url('testDir/chromedriver.sh'));
    }

    /**
     * testInitConfigPathDefaultShouldThrowExceptionIfDefaultPathNotExist
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \PHPUnit_Framework_Exception
     * @throws \Prophecy\Exception\Prophecy\ObjectProphecyException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testInitConfigPathDefaultShouldThrowExceptionIfDefaultPathNotExist()
    {
        $this->expectException(ExtensionException::class);
        $this->expectExceptionMessage('File not found: /some/missing/path');
        $this->headlessChrome = new Codeception\Extension\HeadlessChrome([], [], $this->processProphecy->reveal(), '/some/missing/path');
    }

    /**
     * testDestructShouldStopServer
     *
     * @return void
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testDestructShouldStopServer()
    {
        $this->processProphecy->isRunning()->shouldBeCalled()->willReturn(true);
        $this->processProphecy->signal(2)->shouldBeCalled();
        $this->processProphecy->wait()->shouldBeCalled();
        $this->headlessChrome->__destruct();
    }

    /**
     * initExtension
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \LogicException
     * @throws \Prophecy\Exception\Prophecy\ObjectProphecyException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    protected function initExtension()
    {
        vfsStream::setup('testDir', null, [
            'chromedriver.sh' => 'binary'
        ]);

        $config = ['debug' => true, 'path' => vfsStream::url('testDir/chromedriver.sh')];
        $options = [];
        $this->processProphecy = $this->prophesize(Process::class);
        $this->headlessChrome = new Codeception\Extension\HeadlessChrome($config, $options, $this->processProphecy->reveal(), vfsStream::url('testDir/chromedriver.sh'));

        $this->suiteEventProphecy = $this->prophesize(SuiteEvent::class);

        //defaults for clean run
        $this->processProphecy->start(Argument::any());
        $this->processProphecy->isRunning();
        $this->processProphecy->signal(Argument::type('int'));
        $this->processProphecy->wait();
    }

    /**
     * _before
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \LogicException
     * @throws \Prophecy\Exception\Prophecy\ObjectProphecyException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    protected function _before()
    {
        $this->initExtension();
    }
}
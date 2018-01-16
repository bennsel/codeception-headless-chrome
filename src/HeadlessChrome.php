<?php
/**
 * This file is part of the teamneusta/codeception-headless-chrome package.
 *
 * Copyright (c) 2018 neusta GmbH | Ein team neusta Unternehmen
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Codeception\Extension;

use Codeception\Exception\ExtensionException;
use Codeception\Platform\Extension;
use Symfony\Component\Process\Process;

class HeadlessChrome extends Extension
{
    /**
     * events
     *
     * @var array
     */
    static public $events = [
        'module.init' => 'moduleInit',
    ];

    /**
     * process
     *
     * @var \Symfony\Component\Process\Process
     */
    private $process;

    /**
     * headlessChromeStarted
     *
     * @var bool
     */
    private $headlessChromeStarted = false;

    /**
     * chromedriverComposePath
     *
     * @var string
     */
    private $chromedriverComposePath;

    /**
     * HeadlessChromeChrome constructor.
     *
     * @param array $config
     * @param array $options
     * @param \Symfony\Component\Process\Process|null $process
     * @param string $defaultHeadlessChromeComposePath
     * @throws \Codeception\Exception\ExtensionException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function __construct(
        array $config,
        array $options,
        Process $process = null,
        string $defaultHeadlessChromeComposePath = null
    ) {
        // Set default https proxy
        if (!isset($options['silent'])) {
            $options['silent'] = false;
        }

        $this->chromedriverComposePath = $defaultHeadlessChromeComposePath;
        $this->process = $process;

        parent::__construct($config, $options);

        $this->initDefaultConfig();
    }

    /**
     * initDefaultConfig
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     */
    protected function initDefaultConfig()
    {
        $this->config['path'] = getcwd() . DIRECTORY_SEPARATOR . $this->config['path'] ?? $this->chromedriverComposePath;

        // Set default WebDriver port
        $this->config['port'] = $this->config['port'] ?? 9515;

        // Set default verbose mode
        $this->config['verbose'] = $this->config['verbose'] ?? false;

        // Set default url-base mode
        $this->config['url-base'] = $this->config['url-base'] ?? '/wd/hub';

        if (!file_exists($this->config['path'])) {
            throw new ExtensionException($this, "File not found: {$this->config['path']}.");
        }
    }

    /**
     * getCommand
     *
     * @return string
     */
    private function getCommand(): string
    {
        $arguments = [
            ' --url-base=' . $this->config['url-base'],
            ' --port=' . $this->config['port']
        ];
        return 'exec ' . escapeshellarg(realpath($this->config['path'])) . ' ' . implode(' ', $arguments);
    }

    /**
     * getConfig
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function __destruct()
    {
        $this->stopServer();
    }

    /**
     * stopServer
     *
     * @return void
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    private function stopServer()
    {
        if ($this->process && $this->process->isRunning()) {
            $this->write('Stopping Headless Chrome');

            $this->process->signal(2);
            $this->process->wait();
        }
    }

    /**
     * moduleInit
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function moduleInit(\Codeception\Event\SuiteEvent $e)
    {
        if (!$this->suiteAllowed($e)) {
            return;
        }

        $this->overrideWithModuleConfig($e);
        $command = $this->getCommand();
        $this->process = $this->process ?: new Process($command, realpath(__DIR__), null, null, 3600);
        $this->startServer();
    }

    /**
     * suiteAllowed
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return bool
     */
    protected function suiteAllowed(\Codeception\Event\SuiteEvent $e): bool
    {
        $allowed = true;
        if (isset($this->config['suites'])) {
            $suites = (array)$this->config['suites'];

            $e->getSuite()->getBaseName();

            if (!in_array($e->getSuite()->getBaseName(), $suites)
                && !in_array($e->getSuite()->getName(), $suites)
            ) {
                $allowed = false;
            }
        }

        return $allowed;
    }

    /**
     * overrideWithModuleConfig
     *
     * @param \Codeception\Event\SuiteEvent $e
     * @return void
     */
    protected function overrideWithModuleConfig(\Codeception\Event\SuiteEvent $e)
    {
        $modules = array_filter($e->getSettings()['modules']['enabled']);
        foreach ($modules as $module) {
            if (is_array($module)) {
                $moduleSettings = current($module);
                $this->config['port'] = $moduleSettings['port'] ?? $this->config['port'];
            }
        }
    }

    /**
     * startServer
     *
     * @return void
     * @throws \Codeception\Exception\ExtensionException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    private function startServer()
    {
        if ($this->config['verbose']) {
            $this->writeln(['Generated Headless Chrome Command:', $this->process->getCommandLine()]);
        }
        $this->writeln('Starting Headless Chrome');
        $this->process->start(function($type, $buffer) {
            if (strpos($buffer, 'connections are allowed') !== false) {
                $this->headlessChromeStarted = true;
            }
        });

        // wait until headlessChrome is finished to start
        while ($this->process->isRunning() && !$this->headlessChromeStarted) {
        }

        if (!$this->process->isRunning()) {
            throw new ExtensionException($this, 'Failed to start Headless Chrome.');
        }
        $this->writeln(['', 'Headless Chrome now accessible']);
    }
}

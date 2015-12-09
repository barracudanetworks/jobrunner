<?php

namespace Barracuda\CronDaemon;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CronDaemon
{
	/**
	 * @var array
	 */
	private $modules = array();

	/**
	 * @var null|LoggerInterface|NullLogger
	 */
	private $logger;

	/**
	 * @var \fork_daemon|null
	 */
	private $fork_daemon;

	/**
	 * @var CronDaemonConfig
	 */
	private $config;

	/**
	 * @param CronDaemonConfig $config
	 * @param \fork_daemon|null $fork_daemon
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(CronDaemonConfig $config, \fork_daemon $fork_daemon = null, LoggerInterface $logger = null)
	{
		// set up a default logger if one was not passed
		if ($logger === null)
		{
			$logger = new NullLogger();
		}

		$this->logger = $logger;
		$this->config = $config;

		$this->modules = $this->buildModuleList();

		if ($fork_daemon == null)
		{
			$fork_daemon = new \fork_daemon();
		}
		$this->fork_daemon = $fork_daemon;
		$this->createModuleBuckets();
	}

	/**
	 * This is the main function that will run modules, should be called from a while loop
	 */
	public function run()
	{
		foreach ($this->modules as $module)
		{
			$workRunning = $this->fork_daemon->work_running($module->getShortName());
			if (count($workRunning) == 0)
			{
				$module_name = $module->getShortName();
				if ($this->canModuleRun($module))
				{
					$this->updateModuleLastRunTime($module);
					$this->fork_daemon->addwork(array($module), "$module_name", $module->getShortName());
					$this->fork_daemon->process_work(false, $module->getShortName());
				}
			}
		}
	}

	/**
	 * @param array $module Fork daemon can only add work as an array, so this should have 1 item in it - the module object passed from the run() function
	 */
	public function processWork(array $module)
	{
		$module = $module[0];
		if ($module instanceof Module)
		{
			if ($module instanceof ForkingModule)
			{
				$class = get_class($this->fork_daemon);
				$fork_daemon = new $class;
				$module->setUpForking($fork_daemon);
			}

			$module->start();
		}
	}

	/**
	 * @return array of Module objects keyd on their short names
	 */
	private function buildModuleList()
	{
		$module_list = array();

		$path = $this->config->getDirPath();
		$psr4Path = $this->config->getPsr4Path();

		$dir_handle = opendir($path);

		while (false !== ($filename = readdir($dir_handle)))
		{
			if (substr($filename, -4) == '.php')
			{
				$this->logger->info("Found module file '" . basename($filename) . "'");

				$module_name = $psr4Path . substr(basename($filename), 0, strlen(basename($filename)) - 4);
				// instantiate the module class
				if (is_subclass_of($module_name, 'Barracuda\\CronDaemon\\Module'))
				{
					$module = new $module_name($this->logger);

					// Another sanity check
					if ($module instanceof Module)
					{
						$module_list[$module->getShortName()] = $module;
					}
					else
					{
						$this->logger->info($module_name . ' is not an instance of Module, skipping.');
					}
				}

			}
		}

		closedir($dir_handle);

		return $module_list;
	}

	/**
	 * This function creates a bucket for each module in fork daemon so it is easier to manage if it should run or not
	 */
	private function createModuleBuckets()
	{
		foreach ($this->modules as $module_name => $module)
		{
			$this->fork_daemon->add_bucket($module_name);
			$this->fork_daemon->max_children_set(1, $module_name);
			$this->fork_daemon->register_child_run(array($this, 'processWork'), $module_name);
			$this->fork_daemon->child_max_run_time_set($module->getMaxRuntime(), $module_name);
		}
	}

	/**
	 * @param Module $module
	 * @return bool
	 */
	private function canModuleRun(Module $module)
	{
		if ($module->getState() == ModuleConstants::MODULE_NO_START)
		{
			return false;
		}

		$lastRunTime = $module->getLastRunTime();
		if ($lastRunTime == null)
		{
			return true;
		}

		if (time() - $lastRunTime > $module->getRunInterval())
		{
			return true;
		}

		return false;

	}

	/**
	 * @param Module $module
	 */
	private function updateModuleLastRunTime(Module $module)
	{
		$module->setLastRunTime(time());
	}
}

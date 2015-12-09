<?php

namespace Barracuda\CronDaemon;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Module
 * @package Barracuda\Backup\Cloud\Workerd\Config
 */
abstract class Module implements ModuleInterface
{
	/**
	 * @var null|LoggerInterface|NullLogger
	 */
	protected $logger;

	/**
	 * @var State of the module, e.g. RUNNING/INACTIVE
	 */
	private $state;

	/**
	 * @var The last time the module ran
	 */
	private $lastRunTime;

	/**
	 * @var How often the module should run, e.g. every 6 hours
	 */
	private $runInterval;

	/**
	 * @var
	 */
	private $netclientModule;

	/**
	 * @var
	 */
	private $ticketIdDecommission;

	/**
	 * @var The max time the module should run
	 */
	private $maxRuntime;

	/**
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(LoggerInterface $logger = null)
	{
		// set up a default logger if one was not passed
		if ($logger === null)
		{
			$logger = new NullLogger();
		}

		$this->logger = $logger;

		// Default to every 6 hours
		$this->setRunInterval(21600);
		// Default to 2 days
		$this->setMaxRuntime(172800);
	}

	/**
	 * @param $state
	 */
	public function setState($state)
	{
		$this->state = $state;
	}

	/**
	 * @return int
	 */
	public function getState()
	{
		return $this->state;
	}

	/**
	 * @return int
	 */
	public function getLastRunTime()
	{
		return $this->lastRunTime;
	}

	/**
	 * @param $lastRunTime
	 */
	public function setLastRunTime($lastRunTime)
	{
		$this->lastRunTime = $lastRunTime;
	}

	/**
	 * @return int
	 */
	public function getRunInterval()
	{
		return $this->runInterval;
	}

	/**
	 * @param $runInterval
	 */
	public function setRunInterval($runInterval)
	{
		$this->runInterval = $runInterval;
	}

	/**
	 * @return int
	 */
	public function getNetclientModule()
	{
		return $this->netclientModule;
	}

	/**
	 * @param $netclientModule
	 */
	public function setNetclientModule($netclientModule)
	{
		$this->netclientModule = $netclientModule;
	}

	/**
	 * @return int
	 */
	public function getTicketIdDecommission()
	{
		return $this->ticketIdDecommission;
	}

	/**
	 * @param $ticketIdDecommission
	 */
	public function setTicketIdDecommission($ticketIdDecommission)
	{
		$this->ticketIdDecommission = $ticketIdDecommission;
	}

	/**
	 * @return int
	 */
	public function getMaxRuntime()
	{
		return $this->maxRuntime;
	}

	/**
	 * @param $maxRuntime
	 */
	public function setMaxRuntime($maxRuntime)
	{
		$this->maxRuntime = $maxRuntime;
	}

	/**
	 * @return LoggerInterface|NullLogger
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @return string the short name of the instantiated class
	 */
	public function getShortName()
	{
		$names = explode('\\', get_class($this));
		return $names[count($names) - 1];
	}

	/**
	 * @return string the full namespaced name of the instantiated class
	 */
	public function getName()
	{
		return get_class($this);
	}
}


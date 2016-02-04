<?php

namespace Barracuda\JobRunner;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Module
 */
abstract class Job implements JobInterface
{
	/**
	 * @var null|LoggerInterface|NullLogger
	 */
	protected $logger;

	/**
	 * @var int The last time the module ran
	 */
	private $last_run_time;

	/**
	 * @param LoggerInterface $logger PSR-3 logger object.
	 */
	public function __construct(LoggerInterface $logger = null)
	{
		// set up a default logger if one was not passed
		if ($logger === null)
		{
			$logger = new NullLogger();
		}

		$this->logger = $logger;
	}

	/**
	 * @return int
	 */
	public function getLastRunTime()
	{
		return $this->last_run_time;
	}

	/**
	 * @param int $lastRunTime
	 * @return void
	 */
	public function setLastRunTime($lastRunTime)
	{
		$this->last_run_time = $lastRunTime;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger()
	{
		return $this->logger;
	}
}


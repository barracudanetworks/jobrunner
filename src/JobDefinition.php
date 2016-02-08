<?php

namespace Barracuda\JobRunner;


class JobDefinition
{
	private $enabled;

	private $run_time;

	private $interval;

	private $max_run_time;

	private $reflection;

	private $last_run_time_start;

	private $last_run_time_finish;

	/**
	 * JobDefinition constructor.
	 * @param bool   $enabled      True if the job should run, false if not.
	 * @param string $run_time     When the job should run in the format of 14:00 (2 pm every day).
	 * @param int    $interval     Amount of seconds in between job runs. Default is every 6 hours.
	 * @param int    $max_run_time The max amount of time the job should run for. Default is 2 days.
	 */
	public function __construct($enabled = true, $run_time = null, $interval = 21600, $max_run_time = 172800)
	{
		$this->enabled = $enabled;
		$this->run_time = $run_time;
		$this->interval = $interval;
		$this->max_run_time = $max_run_time;
	}

	/**
	 * @return bool
	 */
	public function getEnabled()
	{
		return $this->enabled;
	}

	/**
	 * @param bool $enabled
	 */
	public function setEnabled($enabled)
	{
		$this->enabled = $enabled;
	}

	/**
	 * @return string
	 */
	public function getRunTime()
	{
		return $this->run_time;
	}

	/**
	 * @param string $run_time
	 */
	public function setRunTime($run_time)
	{
		$this->run_time = $run_time;
	}

	/**
	 * @return int
	 */
	public function getInterval()
	{
		return $this->interval;
	}

	/**
	 * @param int $interval
	 */
	public function setInterval($interval)
	{
		$this->interval = $interval;
	}

	/**
	 * @return int
	 */
	public function getMaxRunTime()
	{
		return $this->max_run_time;
	}

	/**
	 * @param int $max_run_time
	 */
	public function setMaxRunTime($max_run_time)
	{
		$this->max_run_time = $max_run_time;
	}

	/**
	 * Getters and setters for internals below
	 */

	/**
	 * @return \ReflectionClass
	 */
	public function getReflection()
	{
		return $this->reflection;
	}

	/**
	 * @param \ReflectionClass $reflection
	 */
	public function setReflection(\ReflectionClass $reflection)
	{
		$this->reflection = $reflection;
	}

	/**
	 * @return int
	 */
	public function getLastRunTimeStart()
	{
		return $this->last_run_time_start;
	}

	/**
	 * @param int $last_run_time_start
	 */
	public function setLastRunTimeStart($last_run_time_start)
	{
		$this->last_run_time_start = $last_run_time_start;
	}

	/**
	 * @return int
	 */
	public function getLastRunTimeFinish()
	{
		return $this->last_run_time_finish;
	}

	/**
	 * @param int $last_run_time_finish
	 */
	public function setLastRunTimeFinish($last_run_time_finish)
	{
		$this->last_run_time_finish = $last_run_time_finish;
	}
}

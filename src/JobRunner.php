<?php

namespace Barracuda\JobRunner;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class JobRunner
{
	/**
	 * @var array
	 */
	private $jobs = array();

	/**
	 * @var null|LoggerInterface|NullLogger
	 */
	private $logger;

	/**
	 * @var \fork_daemon|null
	 */
	private $fork_daemon;

	/**
	 * @var JobRunnerConfig
	 */
	private $config;

	/**
	 * @param JobRunnerConfig $config
	 * @param \fork_daemon|null $fork_daemon
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(JobRunnerConfig $config, \fork_daemon $fork_daemon = null, LoggerInterface $logger = null)
	{
		// set up a default logger if one was not passed
		if ($logger === null)
		{
			$logger = new NullLogger();
		}

		$this->logger = $logger;
		$this->config = $config;

		$this->jobs = $this->buildJobList();

		if ($fork_daemon == null)
		{
			$fork_daemon = new \fork_daemon();
		}
		$this->fork_daemon = $fork_daemon;
		$this->createJobBuckets();
	}

	/**
	 * This is the main function that will run jobs, should be called from a while loop
	 */
	public function run()
	{
		foreach ($this->jobs as $job)
		{
			$workRunning = $this->fork_daemon->work_running($job->getShortName());
			if (count($workRunning) == 0)
			{
				$job_name = $job->getShortName();
				if ($this->canJobRun($job))
				{
					$this->updateJobLastRunTime($job);
					$this->fork_daemon->addwork(array($job), "$job_name", $job->getShortName());
					$this->fork_daemon->process_work(false, $job->getShortName());
				}
			}
		}
	}

	/**
	 * @param array $job Fork daemon can only add work as an array, so this should have 1 item in it - the job object passed from the run() function
	 */
	public function processWork(array $job)
	{
		$job = $job[0];
		if ($job instanceof Job)
		{
			if ($job instanceof ForkingJob)
			{
				$class = get_class($this->fork_daemon);
				$fork_daemon = new $class;
				$job->setUpForking($fork_daemon);
			}

			$job->start();
		}
	}

	/**
	 * @return array of Job objects keyd on their short names
	 */
	private function buildJobList()
	{
		$job_list = array();

		$path = $this->config->getDirPath();
		$psr4Path = $this->config->getPsr4Path();

		$dir_handle = opendir($path);

		while (false !== ($filename = readdir($dir_handle)))
		{
			if (substr($filename, -4) == '.php')
			{
				$this->logger->info("Found job file '" . basename($filename) . "'");

				$job_name = $psr4Path . substr(basename($filename), 0, strlen(basename($filename)) - 4);
				// instantiate the job class
				if (is_subclass_of($job_name, 'Barracuda\\JobRunner\\Job'))
				{
					$job = new $job_name($this->logger);

					// Another sanity check
					if ($job instanceof Job)
					{
						$job_list[$job->getShortName()] = $job;
					}
					else
					{
						$this->logger->info($job_name . ' is not an instance of Job, skipping.');
					}
				}

			}
		}

		closedir($dir_handle);

		return $job_list;
	}

	/**
	 * This function creates a bucket for each job in fork daemon so it is easier to manage if it should run or not
	 */
	private function createJobBuckets()
	{
		foreach ($this->jobs as $job_name => $job)
		{
			$this->fork_daemon->add_bucket($job_name);
			$this->fork_daemon->max_children_set(1, $job_name);
			$this->fork_daemon->register_child_run(array($this, 'processWork'), $job_name);
			$this->fork_daemon->child_max_run_time_set($job->getMaxRuntime(), $job_name);
		}
	}

	/**
	 * @param Job $job
	 * @return bool
	 */
	protected function canJobRun(Job $job)
	{
		if ($job->getState() == JobConstants::MODULE_NO_START)
		{
			return false;
		}

		$lastRunTime = $job->getLastRunTime();

		// If the job is supposed to run at a scheduled time
		$timeSchedule = $job->getRunAt();
		if ($timeSchedule != null)
		{
			$currentTime = new \DateTime();
			if ($lastRunTime != null)
			{
				$lastRun = new \DateTime();
				$lastRun = $lastRun->setTimestamp($lastRunTime);

				$difference = $lastRun->diff($currentTime);

				// If the last time this ran is the same as the current time, don't let this job run.
				if ($difference->h == 0 && $difference->i == 0)
				{
					return false;
				}
			}

			$currentTimeHoursMinutes = $currentTime->format('H:i');
			if ($currentTimeHoursMinutes == $timeSchedule)
			{
				return true;
			}
			return false;
		}

		// If there is no lastRunTime set, it means we are running for the first time, so return true.
		if ($lastRunTime == null)
		{
			return true;
		}

		if (time() - $lastRunTime > $job->getRunInterval())
		{
			return true;
		}

		return false;
	}

	/**
	 * @param Job $job
	 */
	protected function updateJobLastRunTime(Job $job)
	{
		$job->setLastRunTime(time());
	}

	/**
	 * @return array
	 */
	public function getJobs()
	{
		return $this->jobs;
	}

	/**
	 * @param array $jobs
	 */
	public function setJobs($jobs)
	{
		$this->jobs = $jobs;
	}

	/**
	 * @return null|LoggerInterface|NullLogger
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @param null|LoggerInterface|NullLogger $logger
	 */
	public function setLogger($logger)
	{
		$this->logger = $logger;
	}

	/**
	 * @return \fork_daemon|null
	 */
	public function getForkDaemon()
	{
		return $this->fork_daemon;
	}

	/**
	 * @param \fork_daemon|null $fork_daemon
	 */
	public function setForkDaemon($fork_daemon)
	{
		$this->fork_daemon = $fork_daemon;
	}

	/**
	 * @return JobRunnerConfig
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * @param JobRunnerConfig $config
	 */
	public function setConfig($config)
	{
		$this->config = $config;
	}
}

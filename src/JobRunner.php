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
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var \fork_daemon
	 */
	private $fork_daemon;

	/**
	 * @var JobRunnerConfig
	 */
	private $config;

	/**
	 * @param JobRunnerConfig $config      JobRunner config object.
	 * @param \fork_daemon    $fork_daemon Instance of ForkDaemon.
	 * @param LoggerInterface $logger      Optionally, a logger.
	 */
	public function __construct(
		JobRunnerConfig $config,
		\fork_daemon $fork_daemon = null,
		LoggerInterface $logger = null
	)
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
	 * This is the main function that will run jobs. It should be called in the
	 * main event loop.
	 *
	 * @throws JobRunnerFinishedException When the jobs array is empty.
	 * @return void
	 */
	public function run()
	{
		foreach ($this->jobs as $job)
		{
			$name = $job->getShortName();

			$workRunning = $this->fork_daemon->work_running($name);
			if (count($workRunning) == 0)
			{
				if ($this->canJobRun($job))
				{
					$this->fork_daemon->addwork(array($job), $name, $name);
					$this->fork_daemon->process_work(false, $name);
				}
			}
		}

		throw new JobRunnerFinishedException('No more jobs to do. Stopping JobRunner');
	}

	/**
	 * @param array $job Fork daemon can only add work as an array, so this
	 *                   should have 1 item in it - the job object passed
	 *                   from the run() function.
	 * @return void
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

		// If jobName is set in the config, we are only going to run that job. Good for dev testing.
		if ($this->config->getJobName() !== null)
		{
			$job_name = $psr4Path . $this->config->getJobName();
			$job_list = $this->instantiateJob($job_name, $job_list);
			return $job_list;
		}

		$dir_handle = opendir($path);

		while (false !== ($filename = readdir($dir_handle)))
		{
			if (substr($filename, -4) == '.php')
			{
				$this->logger->info("Found job file '" . basename($filename) . "'");

				$job_name = $psr4Path . substr(basename($filename), 0, strlen(basename($filename)) - 4);
				$job_list = $this->instantiateJob($job_name, $job_list);
			}
		}

		closedir($dir_handle);

		return $job_list;
	}

	/**
	 * Instantiates a job class (if it's indeed a job), and adds it to the
	 * provided job list.
	 *
	 * @param string $job_name The fully qualified path to the class.
	 * @param array  $job_list Array of jobs already instantiated.
	 * @return array Updated $job_list.
	 */
	protected function instantiateJob($job_name, array $job_list)
	{
		if (is_subclass_of($job_name, 'Barracuda\\JobRunner\\Job'))
		{
			$job = new $job_name($this->logger);

			$job_list[$job->getShortName()] = $job;
		}
		else
		{
			$this->logger->warning($job_name . ' is not a subclass of Job, skipping.');
		}

		return $job_list;
	}

	/**
	 * This function creates a bucket for each job in fork daemon so it is
	 * easier to manage if it should run or not.
	 *
	 * @return void
	 */
	private function createJobBuckets()
	{
		foreach ($this->jobs as $job_name => $job)
		{
			$this->fork_daemon->add_bucket($job_name);
			$this->fork_daemon->max_children_set(1, $job_name);
			$this->fork_daemon->register_child_run(array($this, 'processWork'), $job_name);
			$this->fork_daemon->register_parent_child_exit(array($this, 'parentChildExit'), $job_name);
			$this->fork_daemon->child_max_run_time_set($job->getMaxRuntime(), $job_name);
		}
	}

	/**
	 * Returns true if a job can run, false otherwise.
	 *
	 * @param Job $job The job to check.
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
	 * Update the last run time of the job after it is finished.
	 *
	 * @param int $pid The pid of the child exiting.
	 * @return void
	 */
	public function parentChildExit($pid)
	{
		$bucket = $this->fork_daemon->getForkedChildren()[$pid]['bucket'];
		$job = $this->jobs[$bucket];
		$this->updateJobLastRunTime($job);
	}

	/**
	 * Updates a given job's last runtime to now.
	 *
	 * @param Job $job The job to update.
	 * @return void
	 */
	protected function updateJobLastRunTime(Job $job)
	{
		$job->setLastRunTime(time());
	}

	/**
	 * Returns all jobs.
	 * @return array
	 */
	public function getJobs()
	{
		return $this->jobs;
	}

	/**
	 * Sets the jobs array.
	 * @param array $jobs New list of jobs.
	 * @return void
	 */
	public function setJobs(array $jobs)
	{
		$this->jobs = $jobs;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @param LoggerInterface $logger New logger instance.
	 * @return void
	 */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * @return \fork_daemon
	 */
	public function getForkDaemon()
	{
		return $this->fork_daemon;
	}

	/**
	 * @param \fork_daemon $fork_daemon New instance of ForkDaemon.
	 * @return void
	 */
	public function setForkDaemon(\fork_daemon $fork_daemon)
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
	 * @param JobRunnerConfig $config New config object.
	 * @return void
	 */
	public function setConfig(JobRunnerConfig $config)
	{
		$this->config = $config;
	}
}

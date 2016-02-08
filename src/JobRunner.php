<?php

namespace Barracuda\JobRunner;

use ReflectionClass;
use Exception;
use InvalidArgumentException;

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
	 * @param \fork_daemon    $fork_daemon Instance of ForkDaemon.
	 * @param LoggerInterface $logger      Optionally, a logger.
	 */
	public function __construct(
		\fork_daemon $fork_daemon = null,
		LoggerInterface $logger = null
	)
	{
		$this->logger = is_null($logger) ? new NullLogger() : $logger;
		$this->fork_daemon = is_null($fork_daemon) ? new \fork_daemon() : $fork_daemon;
	}

	/**
	 * Adds a job to the JobRunner instance.
	 *
	 * @param string         $class      Path to the Job class.
	 * @param JobDefinition  $definition Job definition (e.g. interval).
	 * @throws InvalidArgumentException If the given class does not subclass Job.
	 * @return void
	 */
	public function addJob($class, JobDefinition $definition = null)
	{
		$reflection = new ReflectionClass($class);
		if (!$reflection->isSubclassOf(Job::class))
		{
			$this->logger->error("{$reflection->getShortName()} does not subclass " . Job::class);

			throw new InvalidArgumentException("{$reflection->getShortName()} must subclass " . Job::class);
		}

		if (isset($this->jobs[$class]))
		{
			$this->logger->warning("{$reflection->getShortName()} is already registered, skipping");
			return;
		}

		if (is_null($definition))
		{
			$definition = new JobDefinition();
		}
		else
		{
			// If we have a run_time and interval set, we will ignore the interval when checking if job can run.
			if (!is_null($definition->getInterval()) && !is_null($definition->getRunTime()))
			{
				$this->logger->warning("Both run_time and interval are set for {$reflection->getShortName()} — prioritizing run_time");
			}
		}

		// Set internal definitions
		$definition->setLastRunTimeStart(null);
		$definition->setLastRunTimeFinish(null);
		$definition->setReflection($reflection);

		// Add to job list, using defaults where necessary
		$this->jobs[$class] = $definition;
		$this->createJobBuckets($class);

		$this->logger->info("Registered job {$reflection->getShortName()}");
	}

	/**
	 * This is the main function that will run jobs. It should be called in the
	 * main event loop.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->logger->debug("Looking for jobs to run");

		foreach ($this->jobs as $class => $definition)
		{
			// Check if it's time to run the job
			if ($this->canJobRun($class, $this->jobs[$class]))
			{
				$this->queueJob($class);
			}
		}

		$this->logger->debug("No more jobs to run");
	}

	/**
	 * Adds a job to the fork_daemon work list so we'll start it.
	 *
	 * @param string $class Job class to start.
	 * @return void
	 */
	protected function queueJob($class)
	{
		$this->logger->info("Adding job {$this->jobs[$class]->getReflection()->getShortName()} to work list");

		// Update runtime now, so that subsequent calls to run()
		// dont kick the job off multiple times
		$this->jobs[$class]->setLastRunTimeStart(time());

		$this->fork_daemon->addwork(array($class), $class, $class);
		$this->fork_daemon->process_work(false, $class);

	}

	/**
	 * Called by fork_daemon when there is work to be processed.
	 *
	 * @param array $work Fork daemon can only add work as an array, so this
	 *                    should have 1 item in it - the class name of a
	 *                    registered Job.
	 * @return void
	 */
	public function processWork(array $work)
	{
		// There should only be one element, so we'll only operate with the first
		$class = array_pop($work);
		if (!isset($this->jobs[$class]))
		{
			$this->logger->warning("Unknown work unit in fork_daemon, is something else adding work?", $work);
			return;
		}

		$this->logger->info("Running job {$this->jobs[$class]->getReflection()->getShortName()}");

		try
		{
			// Try to run the job
			$job = $this->instantiateJob($class);
			if ($job instanceof Job)
			{
				// Pass relevant info to the job from the parent before calling start
				$job->setLastRunTime($this->jobs[$class]->getLastRunTimeFinish());

				$job->start();
			}
		}
		// Catching the very general Exception here so that we might also catch
		// exceptions in the job's code.
		catch (Exception $e)
		{
			$this->logger->error("Exception while trying to run {$this->jobs[$class]->getReflection()->getShortName()}: " .
				$e->getMessage());

			return;
		}
	}

	/**
	 * Instantiates a job class (if it's indeed a job).
	 *
	 * @param string $class The fully qualified path to the class.
	 * @return object Instantiated object.
	 */
	protected function instantiateJob($class)
	{
		// Make sure this is a real job
		$reflection = $this->getJob($class)->getReflection();

		// Create a new instance
		$job = $reflection->newInstance($this->logger);
		if ($job instanceof ForkingJob)
		{
			// If it's a ForkingJob, give it its own fork_daemon, using the same
			// class that JobRunner uses.
			$fork_daemon = (new ReflectionClass($this->fork_daemon))->newInstance();
			$job->setUpForking($fork_daemon);
		}

		return $job;
	}

	/**
	 * This function creates a bucket for each job in fork daemon so it is
	 * easier to manage if it should run or not.
	 *
	 * @param string $class Job to create buckets for.
	 * @return void
	 */
	private function createJobBuckets($class)
	{
		$job = $this->getJob($class);

		$this->fork_daemon->add_bucket($class);
		$this->fork_daemon->max_children_set(1, $class);
		$this->fork_daemon->register_child_run(array($this, 'processWork'), $class);
		$this->fork_daemon->register_parent_child_exit(array($this, 'parentChildExit'), $class);
		$this->fork_daemon->child_max_run_time_set($job->getMaxRunTime(), $class);
	}

	/**
	 * Returns true if a job can run, false otherwise.
	 *
	 * @param string         $class          The job to check.
	 * @param JobDefinition  $job_definition The job definition.
	 * @return bool
	 */
	protected function canJobRun($class, JobDefinition $job_definition)
	{
		if ($job_definition->getEnabled() == false)
		{
			return false;
		}

		// If this job is already running, don't start it again
		if (count($this->fork_daemon->work_running($class)) != 0)
		{
			return false;
		}

		$last_run_time = $job_definition->getLastRunTimeStart();

		// If the job is supposed to run at a scheduled time
		if (!is_null($job_definition->getRunTime()))
		{
			$now = new \DateTime();

			// Check if the run time is now
			if ($job_definition->getRunTime() == $now->format('H:i'))
			{
				// If we haven't run before, we can run
				if (is_null($last_run_time))
				{
					return true;
				}
				else
				{
					$last_run = new \DateTime();
					$last_run->setTimestamp($last_run_time);

					$difference = $last_run->diff($now);

					// If the last run wasn't this minute, we can run
					if ($difference->days != 0 || $difference->h != 0 || $difference->i != 0)
					{
						return true;
					}
				}
			}
			return false;
		}

		// If the job runs on an interval, check if it's ready to run
		if (!is_null($job_definition->getInterval()))
		{
			// If it hasn't run yet, run it!
			if (is_null($last_run_time))
			{
				return true;
			}

			if ((time() - $last_run_time) > $job_definition->getInterval())
			{
				return true;
			}
		}

		// No run condition hit
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
		// Bucket should be named after the job class
		$class = $this->fork_daemon->getForkedChildren()[$pid]['bucket'];

		$this->jobFinished($class, $this->jobs[$class]);
	}

	/**
	 * Called whenever a job exits (according to fork_daemon).
	 *
	 * @param string        $class          The job that finished.
	 * @param JobDefinition $job_definition The job definition.
	 * @return void
	 */
	protected function jobFinished($class, JobDefinition $job_definition)
	{
		$job_definition->setLastRunTimeFinish(time());
		$this->logger->info("Job {$this->jobs[$class]->getReflection()->getShortName()} finished");
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
	 * Returns a given job's definition.
	 *
	 * @param string $class The job class to lookup.
	 * @throws InvalidArgumentException If the job isn't registered.
	 * @return array
	 */
	public function getJob($class)
	{
		if (!isset($this->jobs[$class]))
		{
			throw new InvalidArgumentException("{$class} is not a registered job");
		}

		return $this->jobs[$class];
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @return \fork_daemon
	 */
	public function getForkDaemon()
	{
		return $this->fork_daemon;
	}
}

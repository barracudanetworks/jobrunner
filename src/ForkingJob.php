<?php

namespace Barracuda\JobRunner;

use Exception;
use InvalidArgumentException;

use Psr\Log\LoggerInterface;

use fork_daemon;

abstract class ForkingJob extends Job implements ForkingJobInterface
{
	/**
	 * @var \fork_daemon
	 */
	private $fork_daemon;

	private $num_children;

	private $item_count;

	/**
	 * Sets up the job.
	 *
	 * @param LoggerInterface $logger PSR-3 logger object.
	 */
	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->num_children = 10;
		$this->item_count = 500;
	}

	/**
	 * Calls createWork() to generate work units, and calls process_work() on
	 * fork_daemon.
	 *
	 * @throws Exception If createWork() returns a non-array.
	 * @return void
	 */
	public function start()
	{
		$work = $this->createWork();
		if (is_array($work))
		{
			$this->addWork($work);
		}
		elseif (!is_null($work))
		{
			throw new Exception("createWork() may only return an array!");
		}

		// process all work sets
		do
		{
			$this->fork_daemon->process_work(false);
		} while ($this->fork_daemon->work_sets_count() > 0);

		// wait for children to finish working
		do
		{
			$children_remaining = $this->fork_daemon->children_running();
			if ($children_remaining)
			{
				$this->logger->debug("Waiting for all children to finish, {$children_remaining} remaining");
				sleep(1);
			}
		} while ($children_remaining);

		$this->logger->info("All children exited, fin!");
	}

	/**
	 * Adds a list of work units to fork_daemon.
	 *
	 * @param array $work A list of work units.
	 * @return void
	 */
	protected function addWork(array $work)
	{
		$this->fork_daemon->addwork($work);
	}

	/**
	 * Sets up forking.
	 *
	 * @param fork_daemon $fork_daemon Fork daemon object.
	 * @return void
	 */
	public function setUpForking(fork_daemon $fork_daemon)
	{
		$this->fork_daemon = $fork_daemon;

		$this->fork_daemon->max_children_set($this->getNumChildren());
		$this->fork_daemon->register_child_run(array($this, 'processWork'));
		$this->fork_daemon->register_parent_exit(array($this, 'cleanUp'));
		$this->fork_daemon->max_work_per_child_set($this->getItemCount());
	}

	/**
	 * Should either return a list of all work units, or call addWork() as many
	 * times as necessary to fully populate a work list.
	 *
	 * @return array|null
	 */
	abstract public function createWork();

	/**
	 * Receives a list of work units to process.
	 *
	 * @param array $work Work units.
	 * @return void
	 */
	abstract public function processWork(array $work);

	/**
	 * Optional code to be called before forking any worker children.
	 * @return void
	 */
	public function prepareToFork()
	{
	}

	/**
	 * Optional cleanup code, called when the Job's fork_daemon exits.
	 * @return void
	 */
	public function cleanUp()
	{
	}

	/**
	 * Set the number of children processes to spawn.
	 *
	 * @param int $numChildren Children processes to spawn.
	 * @throws InvalidArgumentException If $numChildren is not an integer.
	 * @return void
	 */
	public function setNumChildren($numChildren)
	{
		if (!is_int($numChildren))
		{
			throw new InvalidArgumentException("numChildren must be an integer");
		}

		$this->num_children = $numChildren;
	}

	/**
	 * @return int
	 */
	public function getNumChildren()
	{
		return $this->num_children;
	}

	/**
	 * Set the number of work units each child should process.
	 *
	 * @param int $itemCount Number of work units to process.
	 * @throws InvalidArgumentException If $itemCount is not an integer.
	 * @return void
	 */
	public function setItemCount($itemCount)
	{
		if (!is_int($itemCount))
		{
			throw new InvalidArgumentException("itemCount must be an integer");
		}

		$this->item_count = $itemCount;
	}

	/**
	 * @return int
	 */
	public function getItemCount()
	{
		return $this->item_count;
	}
}

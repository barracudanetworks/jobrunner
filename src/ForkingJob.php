<?php

namespace Barracuda\JobRunner;

use Psr\Log\LoggerInterface;

abstract class ForkingJob extends Job implements ForkingJobInterface
{
	/**
	 * @var \fork_daemon
	 */
	private $fork_daemon;

	private $num_children;

	private $item_count;

	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->num_children = 10;
		$this->item_count = 500;
	}

	public function start()
	{
		$workUnitsCount = $this->getItemCount();

		$workUnits = $this->createWork($workUnitsCount);
		while ($workUnits != null)
		{
			$this->fork_daemon->addwork(array($workUnits));
			$this->fork_daemon->process_work(false);

			$workUnits = $this->createWork($workUnitsCount);
		}

		// wait for children to finish working
		$this->fork_daemon->process_work(true);
	}

	public function setUpForking(\fork_daemon $fork_daemon)
	{
		$this->fork_daemon = $fork_daemon;
		$this->fork_daemon->max_children_set($this->getNumChildren());
		$this->fork_daemon->register_child_run(array($this, 'processWork'));
		$this->fork_daemon->register_parent_exit(array($this, 'cleanUp'));
		$this->fork_daemon->max_work_per_child_set(1);
	}

	abstract public function createWork($workUnitsCount);

	abstract public function processWork(array $work);

	abstract public function trackProcessedWork($workUnitsCount);

	abstract public function cleanUp();

	public function setNumChildren($numChildren)
	{
		$this->num_children = $numChildren;
	}

	public function getNumChildren()
	{
		return $this->num_children;
	}

	public function setItemCount($itemCount)
	{
		$this->item_count = $itemCount;
	}

	public function getItemCount()
	{
		return $this->item_count;
	}
}

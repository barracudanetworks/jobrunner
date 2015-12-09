<?php

namespace Barracuda\CronDaemon;

use Psr\Log\LoggerInterface;

abstract class ForkingModule extends Module implements ForkingModuleInterface
{
	/**
	 * @var \fork_daemon
	 */
	private $fork_daemon;

	private $numChildren;

	private $itemCount;

	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->numChildren = 10;
		$this->itemCount = 500;
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

	abstract protected function createWork($workUnitsCount);

	abstract public function processWork(array $work);

	abstract protected function trackProcessedWork($workUnitsCount);

	abstract public function cleanUp();

	public function setNumChildren($numChildren)
	{
		$this->numChildren = $numChildren;
	}

	public function getNumChildren()
	{
		return $this->numChildren;
	}

	public function setItemCount($itemCount)
	{
		$this->itemCount = $itemCount;
	}

	public function getItemCount()
	{
		return $this->itemCount;
	}
}

<?php

namespace Barracuda\JobRunner\Examples;


use Barracuda\JobRunner\ForkingJob;
use Psr\Log\LoggerInterface;

class ForkingComplimenter extends ForkingJob
{
	private $noMoreWork = false;
	private $currentPointer = 0;

	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->setRunInterval(5);
	}

	protected function createWork($workUnitsCount)
	{
		if ($this->noMoreWork)
		{
			return null;
		}

		$this->trackProcessedWork($workUnitsCount);

		return array('You have ' . $this->currentPointer . ' friends!');
	}

	public function processWork(array $work)
	{
		$compliments = $work[0];

		$pid = posix_getpid();

		foreach ($compliments as $compliment)
		{
			echo "pid: {$pid} | " . $compliment . PHP_EOL;
		}
	}

	protected function trackProcessedWork($workUnitsCount)
	{
		$this->currentPointer += $workUnitsCount;

		if ($this->currentPointer >= 7000)
		{
			$this->noMoreWork = true;
		}
	}

	public function cleanUp()
	{
		$this->noMoreWork = false;
		$this->currentPointer = 0;
	}
}

<?php
namespace Barracuda\JobRunner\Examples;

use Barracuda\JobRunner\ForkingJob;
use Psr\Log\LoggerInterface;

class ForkingComplimenter extends ForkingJob
{
	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->setItemCount(10);
		$this->setNumChildren(5);
	}

	public function createWork()
	{
		// Adding a bunch at once
		$work = [];
		$count = 1;
		while ($count <= 100)
		{
			$work[] = "You have " . $count . " friends!";
			$count++;
		}
		$this->addWork($work);

		// Alternatively, you may return an array (like you would pass to
		// $this->addWork), and it will be added automatically.
	}

	public function processWork(array $work)
	{
		$pid = posix_getpid();
		echo "[{$this->getShortName()}] pid: {$pid} | Has " . count($work) . " work units to work on" . PHP_EOL;

		// Simulate some work being done so you can see 5 different children spawn at a time
		sleep(1);
	}

	public function cleanUp()
	{
		echo "[{$this->getShortName()}] Doing some post job clean up..." . PHP_EOL;
	}
}

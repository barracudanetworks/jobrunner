<?php
namespace Barracuda\JobRunner\Examples;

use Barracuda\JobRunner\ForkingJob;
use Psr\Log\LoggerInterface;

class ForkingComplimenter extends ForkingJob
{
	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->setItemCount(100);
	}

	public function createWork()
	{
		// Adding a bunch at once
		$work = [];
		$count = 1;
		while ($count <= 500)
		{
			$work[] = "You have " . $count . " friends!";
			$count++;
		}
		$this->addWork($work);
	}

	public function processWork(array $work)
	{
		$pid = posix_getpid();

		foreach ($work as $compliment)
		{
			echo "pid: {$pid} | " . $compliment . PHP_EOL;
		}
	}
}

<?php

namespace Barracuda\JobRunner\Examples;

use Psr\Log\LoggerInterface;
use Barracuda\JobRunner\Job;

class Complainer extends Job
{
	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);
	}

	public function start()
	{
		echo 'I am hungry!' . PHP_EOL;
	}
}

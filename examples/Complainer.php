<?php

namespace Barracuda\JobRunner\Examples;


use Barracuda\JobRunner\Job;
use Psr\Log\LoggerInterface;

class Complainer extends Job
{
	public function __construct(LoggerInterface $logger = null)
	{
		parent::__construct($logger);

		$this->setRunInterval(3);
	}

	public function start()
	{
		echo 'I am hungry!' . PHP_EOL;
	}
}

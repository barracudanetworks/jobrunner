<?php

namespace Barracuda\CronDaemon\Examples;


use Barracuda\CronDaemon\Module;
use Psr\Log\LoggerInterface;

class Complainer extends Module
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
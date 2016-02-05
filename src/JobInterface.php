<?php

namespace Barracuda\JobRunner;

use \Psr\Log\LoggerInterface;

interface JobInterface
{
	public function start();

	public function getLastRunTime();

	public function setLastRunTime($lastRunTime);

	public function getLogger();
}

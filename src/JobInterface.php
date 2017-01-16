<?php

namespace Barracuda\JobRunner;

use \Psr\Log\LoggerInterface;

interface JobInterface
{
	public function start();

	public function getLastFinishRunTime();

	public function getLastStartRunTime();

	public function setLastFinishRunTime($lastRunTime);

	public function setLastStartRunTime($lastRunTime);

	public function getLogger();

	public function getShortName();

	public function getName();
}

<?php

namespace Barracuda\JobRunner;

use \Psr\Log\LoggerInterface as Log;

interface JobInterface
{
	function __construct(Log $logger);

	public function start();

	public function setState($state);

	public function getState();

	public function getLastRunTime();

	public function setLastRunTime($lastRunTime);

	public function getRunAt();

	public function setRunAt($runAt);

	public function getRunInterval();

	public function setRunInterval($runInterval);

	public function getMaxRuntime();

	public function setMaxRuntime($maxRuntime);

	public function getLogger();

	public function getShortName();

	public function getName();
}

<?php

namespace Barracuda\CronDaemon;

use \Psr\Log\LoggerInterface as Log;

interface ModuleInterface
{
	function __construct(Log $logger);

	public function start();

	public function setState($state);

	public function getState();

	public function getLastRunTime();

	public function setLastRunTime($lastRunTime);

	public function getRunInterval();

	public function setRunInterval($runInterval);

	public function getMaxRuntime();

	public function setMaxRuntime($maxRuntime);

	public function getLogger();

	public function getShortName();

	public function getName();
}

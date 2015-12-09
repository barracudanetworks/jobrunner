<?php

declare(ticks=1);

require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Detroit');

// Create the config that has your namespace for the modules and the directory they are located in
$config = new \Barracuda\JobRunner\JobRunnerConfig('Barracuda\\JobRunner\\Examples\\', __DIR__);

// Instantiate the CronDaemon
$jobRunner = new \Barracuda\JobRunner\JobRunner($config);

// Have the run method live inside of a while (true) daemonize the process
while (true)
{
	try
	{
		$jobRunner->run();
	}
	catch (Exception $e)
	{
		echo 'Something went wrong: ' . $e->getMessage() . PHP_EOL;
	}
}

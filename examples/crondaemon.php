<?php

declare(ticks=1);

require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Detroit');

// Create the config that has your namespace for the modules and the directory they are located in
$config = new \Barracuda\CronDaemon\CronDaemonConfig('Barracuda\\CronDaemon\\Examples\\', __DIR__);

// Instantiate the CronDaemon
$crondaemon = new \Barracuda\CronDaemon\CronDaemon($config);


// Have the run method live inside of a while (true) daemonize the process
while (true)
{
	try
	{
		$crondaemon->run();
	}
	catch (Exception $e)
	{
		echo 'Something went wrong: ' . $e->getMessage() . PHP_EOL;
	}
}

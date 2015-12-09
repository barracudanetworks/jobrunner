<?php

require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Detroit');

$config = new \Barracuda\CronDaemon\CronDaemonConfig('Barracuda\\CronDaemon\\Examples\\', __DIR__);

$crondaemon = new \Barracuda\CronDaemon\CronDaemon($config);

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

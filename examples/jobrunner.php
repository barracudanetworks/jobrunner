<?php

declare(ticks=1);

require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Detroit');

$options = getopt('j:i');

$jobName = null;
if (array_key_exists('j', $options))
{
	$jobName = $options['j'];
}

$ignoreLastRunTime = false;
if (array_key_exists('i', $options))
{
	$ignoreLastRunTime = $options['i'];
}

// Create the config that has your namespace for the modules and the directory they are located in
$config = new \Barracuda\JobRunner\JobRunnerConfig('Barracuda\\JobRunner\\Examples\\', __DIR__, $jobName, $ignoreLastRunTime);

// Instantiate the JobRunner
$jobRunner = new \Barracuda\JobRunner\JobRunner($config);

// Have the run method live inside of a while (true) daemonize the process
while (true)
{
	try
	{
		$jobRunner->run();
	}
	catch (\Barracuda\JobRunner\JobRunnerFinishedException $e)
	{
		echo $e->getMessage() . PHP_EOL;
		break;
	}
	catch (Exception $e)
	{
		echo 'Something went wrong: ' . $e->getMessage() . PHP_EOL;
	}
}

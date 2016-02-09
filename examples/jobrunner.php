<?php
/**
 * Example daemon utilizing JobRunner.
 */

// This line is important! Without it, fork_daemon won't behave properly.
declare(ticks=1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

use \Barracuda\JobRunner\JobDefinition;

// Instantiate the JobRunner
$jobRunner = new \Barracuda\JobRunner\JobRunner();
$jobRunner->addJob(new JobDefinition(\Barracuda\JobRunner\Examples\ForkingComplimenter::class, true, null, 5, null));
$jobRunner->addJob(new JobDefinition(\Barracuda\JobRunner\Examples\Complainer::class, true, null, 3, null));

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
	sleep(1);
}

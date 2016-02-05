<?php
/**
 * Example daemon utilizing JobRunner.
 */

// This line is important! Without it, fork_daemon won't behave properly.
declare(ticks=1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');

// Instantiate the JobRunner
$jobRunner = new \Barracuda\JobRunner\JobRunner();
$jobRunner->addJob(\Barracuda\JobRunner\Examples\ForkingComplimenter::class, ['interval' => 5]);
$jobRunner->addJob(\Barracuda\JobRunner\Examples\Complainer::class, ['interval' => 3]);

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

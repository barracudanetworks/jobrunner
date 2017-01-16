<?php namespace Barracuda\JobRunner;

use Psr\Log\LoggerInterface;

/**
 * Test Job class
 */
class JobTest extends \PHPUnit_Framework_TestCase
{
	public function testConstructor()
	{
		$job = $this->getMockForAbstractClass(Job::class);
		$this->assertInstanceOf(LoggerInterface::class, $job->getLogger());
	}

	public function testSetLastFinishRunTime()
	{
		$job = $this->getMockForAbstractClass(Job::class);

		$time = time();
		$job->setLastFinishRunTime($time);
		$this->assertEquals($time, $job->getLastFinishRunTime());
	}

	public function testSetLastStartRunTime()
	{
		$job = $this->getMockForAbstractClass(Job::class);

		$time = time();
		$job->setLastStartRunTime($time);
		$this->assertEquals($time, $job->getLastStartRunTime());
	}

	public function testGetName()
	{
		$job = new JobNameStub;

		$this->assertEquals(JobNameStub::class, $job->getName());
		$this->assertEquals('JobNameStub', $job->getShortName());
	}
}

class JobNameStub extends Job {
	public function start()
	{
	}
}

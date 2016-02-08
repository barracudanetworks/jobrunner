<?php namespace Barracuda\JobRunner;

use Exception;
use InvalidArgumentException;

use fork_daemon;
use Psr\Log\LoggerInterface;
use Mockery as m;

/**
 * Test ForkingJob class
 */
class ForkingJobTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->fork_daemon = m::mock('fork_daemon')->makePartial();

		$this->job = m::mock(ForkingJob::class . '[createWork,processWork]');
		$this->job->setUpForking($this->fork_daemon);
	}

	public function tearDown()
	{
		m::close();
	}

	public function testConstructor()
	{
		// Test defaults
		$this->assertEquals(10, $this->job->getNumChildren());
		$this->assertEquals(500, $this->job->getItemCount());
	}

	public function testSetNumChildren()
	{
		$this->job->setNumChildren(5);
		$this->assertEquals(5, $this->job->getNumChildren());

		$this->setExpectedException(InvalidArgumentException::class);
		$this->job->setNumChildren('foo');
	}

	public function testSetItemCount()
	{
		$this->job->setItemCount(10);
		$this->assertEquals(10, $this->job->getItemCount());

		$this->setExpectedException(InvalidArgumentException::class);
		$this->job->setItemCount('foo');
	}

	public function testStart()
	{
		$work = ['foo', 'bar'];

		// If createWork() returns an array, JobRunner should call addwork()
		$this->job->shouldReceive('createWork')->once()->andReturn($work);

		$this->fork_daemon->shouldReceive('addwork')->with($work)->once();
		$this->fork_daemon->shouldReceive('process_work')->with(true)->atLeast()->once();

		$this->job->start();

		// If createWork() returns a non-array, JobRunner should throw Exception
		$this->job->shouldReceive('createWork')->once()->andReturn('foo');

		$this->setExpectedException(Exception::class);

		$this->job->start();
	}

	public function testCleanup()
	{
		$this->job->cleanUp();
	}
}

<?php namespace Barracuda\JobRunner;

use stdClass;
use DateTime;
use ReflectionClass;
use Exception;
use InvalidArgumentException;

use Mockery as m;
use Psr\Log\NullLogger;
use fork_daemon;

/**
 * Tests the JobRunner class.
 */
class JobRunnerTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * Clean up Mockery and run assertions.
	 * @return void
	 */
	public function tearDown()
	{
		m::close();
	}

	/**
	 * Test __construct()
	 * @return void
	 */
	public function testConstructor()
	{
		// Check defaults
		$jr = new JobRunner();

		$this->assertInstanceOf(NullLogger::class, $jr->getLogger());
		$this->assertInstanceOf(fork_daemon::class, $jr->getForkDaemon());

		// Pass a logger and fork_daemon
		$logger = new NullLogger();
		$fork_daemon = new fork_daemon();
		$jr = new JobRunner();

		$this->assertEquals($logger, $jr->getLogger());
		$this->assertEquals($fork_daemon, $jr->getForkDaemon());
	}

	/**
	 * Tests adding a job class.
	 * @return void
	 */
	public function testAddJob()
	{
		$mock = m::mock('fork_daemon');
		$jr = new JobRunner($mock);

		// Make sure buckets are created correctly for the job
		$mock->shouldReceive('add_bucket')->with(JobStub::class)->once();
		$mock->shouldReceive('max_children_set')->with(1, JobStub::class)->once();
		$mock->shouldReceive('register_child_run')->with([$jr, 'processWork'], JobStub::class)->once();
		$mock->shouldReceive('register_parent_child_exit')->with([$jr, 'parentChildExit'], JobStub::class)->once();
		$mock->shouldReceive('child_max_run_time_set')->with(172800, JobStub::class)->once();

		// Check Job added correctly
		$jr->addJob(JobStub::class, []);
		$jobs = $jr->getJobs();

		$this->assertArrayHasKey(JobStub::class, $jobs);
		$this->assertCount(1, $jobs);

		// Check job default definitions
		$job = $jobs[JobStub::class];

		$this->assertContains('enabled', $job);
		$this->assertContains('reflection', $job);
		$this->assertContains('last_run_time', $job);
		$this->assertContains('run_time', $job);
		$this->assertContains('interval', $job);

		// Check enabled by default, and ReflectionClass saved
		$this->assertTrue($job['enabled']);
		$this->assertInstanceOf(ReflectionClass::class, $job['reflection']);

		// Adding it twice should have no effect
		$jr->addJob(JobStub::class, []);

		$this->assertArrayHasKey(JobStub::class, $jobs);
		$this->assertCount(1, $jobs);

		// Try again, setting some definition values
		$jr = new JobRunner();
		$jr->addJob(JobStub::class, [
			'enabled' => false,
			'interval' => 3600,
			'run_time' => '12:00',

			// Should not be retained
			'reflection' => 'FOOBAR',
			'last_run_time' => time(),
		]);

		$job = $jr->getJobs()[JobStub::class];

		$this->assertFalse($job['enabled']);
		$this->assertEquals(3600, $job['interval']);
		$this->assertEquals('12:00', $job['run_time']);

		// Should not retain given values
		$this->assertEmpty($job['last_run_time']);
		$this->assertInstanceOf(ReflectionClass::class, $job['reflection']);

		// Adding a non-Job class should throw an exception
		$this->setExpectedException(Exception::class);

		$jr = new JobRunner();
		$jr->addJob(stdClass::class, []);
	}

	/**
	 * Tests running a job.
	 * @return void
	 */
	public function testRun()
	{
		$mock = m::mock('fork_daemon')->makePartial();
		$jr = new JobRunner($mock);

		$mock->shouldReceive('addwork')
			->with([JobStub::class], JobStub::class, JobStub::class)
			->twice();
		$mock->shouldReceive('addwork')
			->with([AnotherJobStub::class], AnotherJobStub::class, AnotherJobStub::class)
			->once();
		$mock->shouldReceive('process_work')->with(false, JobStub::class)->twice();
		$mock->shouldReceive('process_work')->with(false, AnotherJobStub::class)->once();

		// Interval of -1 means this should run every time run() is called
		$jr->addJob(JobStub::class, ['interval' => -1]);

		// Should only run once every 100 seconds
		$jr->addJob(AnotherJobStub::class, ['interval' => 100]);

		$jobs = $jr->getJobs();

		// Call run() twice, so that the mock expectations are satisfied
		$jr->run();

		// Make sure job run times are set
		$jobs = $jr->getJobs();

		$this->assertNotEmpty($jobs[JobStub::class]['last_run_time']);
		$this->assertNotEmpty($jobs[AnotherJobStub::class]['last_run_time']);

		$jr->run();

		// Test we can't run a job when it's already running
		$mock->shouldReceive('work_running')->with(JobStub::class)->once()->andReturn(['foo']);
		$mock->shouldNotReceive('addwork');

		$jr->run();

		// Test running a job by run_time
		$mock = m::mock('fork_daemon')->makePartial();
		$jr = new JobRunner($mock);

		// JobStub should trigger once
		$mock->shouldReceive('addwork')
			->with([JobStub::class], JobStub::class, JobStub::class)
			->once();
		$mock->shouldReceive('process_work')->with(false, JobStub::class)->once();

		// AnotherJobStub should not trigger
		$mock->shouldNotReceive('addwork')
			->with([AnotherJobStub::class], AnotherJobStub::class, AnotherJobStub::class);
		$mock->shouldNotReceive('process_work')->with(false, AnotherJobStub::class);

		// Unset interval, and define a run_time of now.
		$jr->addJob(JobStub::class, [
			'run_time' => (new DateTime)->format('H:i'),
			'interval' => null
		]);

		// Set interval to false and define a run_time that isn't now
		$jr->addJob(AnotherJobStub::class, [
			'run_time' => (new DateTime('+1 hour'))->format('H:i'),
			'interval' => false,
		]);

		$jr->run();

		// Running a second time shouldn't do anything
		$jr->run();

		// Test disabled job
		$mock = m::mock('fork_daemon')->makePartial();
		$jr = new JobRunner($mock);

		$mock->shouldNotReceive('addwork')->with([JobStub::class], JobStub::class, JobStub::class);
		$mock->shouldNotReceive('process_work')->with(false, JobStub::class);

		$jr->addJob(JobStub::class, ['enabled' => false]);
		$jr->run();
	}

	/**
	 * Make sures jobs are started when processWork() is called.
	 * @return void
	 */
	public function testProcessWork()
	{
		global $jobStarted;
		global $setUpForkingCalled;

		$jobStarted = false;
		$setUpForkingCalled = false;

		$jr = new JobRunner();
		$jr->addJob(JobStub::class, []);
		$jr->processWork(array(JobStub::class));

		// JobStub's start() method will toggle this.
		$this->assertTrue($jobStarted);

		$jr->addJob(ForkingJobStub::class, []);
		$jr->processWork(array(ForkingJobStub::class));

		// ForkingJobStub's setUpForking() method will toggle this.
		$this->assertTrue($setUpForkingCalled);

		// Try adding work for a non-existent job
		$jr->processWork(array('Foo'));

		$jr->addJob(ExceptionJobStub::class, []);
		$jr->processWork(array(ExceptionJobStub::class));
	}

	public function testGetJob()
	{
		$jr = new JobRunner();

		$jr->addJob(JobStub::class, []);
		$this->assertInternalType('array', $jr->getJob(JobStub::class));

		// Trying to get a non-existent job should throw an exception
		$this->setExpectedException(InvalidArgumentException::class);
		$jr->getJob(AnotherJobStub::class);
	}
}

/**
 * Stub used for adding a job to JobRunner.
 */
class JobStub extends Job
{
	/**
	 * Toggles a global flag showing that start() was called.
	 * @return void
	 */
	public function start()
	{
		global $jobStarted;
		$jobStarted = true;
	}
}

/**
 * Another stub, for working with multiple jobs.
 */
class AnotherJobStub extends Job
{
	/**
	 * Must implement abstract method.
	 * @return void
	 */
	public function start()
	{
	}
}

/**
 * Another stub, for testing exceptions.
 */
class ExceptionJobStub extends JobStub
{
	public function start()
	{
		throw new Exception("I OBJECT");
	}
}

/**
 * Stub for forking jobs.
 */
class ForkingJobStub extends ForkingJob
{
	/**
	 * Sets a global flag to verify this method was called.
	 * @param fork_daemon $fork_daemon Inherited.
	 * @return void
	 */
	public function setUpForking(fork_daemon $fork_daemon)
	{
		global $setUpForkingCalled;
		$setUpForkingCalled = true;

		parent::setUpForking($fork_daemon);
	}

	/**
	 * Must implement abstract method.
	 * @param int $workUnitsCount Inherited.
	 * @return void
	 */
	public function createWork($workUnitsCount)
	{
	}

	/**
	 * Must implement abstract method.
	 * @param array $work Inherited.
	 * @return void
	 */
	public function processWork(array $work)
	{
	}

	/**
	 * Must implement abstract method.
	 * @param int $workUnitsCount Inherited.
	 * @return void
	 */
	public function trackProcessedWork($workUnitsCount)
	{
	}

	/**
	 * Must implement abstract method.
	 * @param int $workUnitsCount Inherited.
	 * @return void
	 */
	public function cleanUp()
	{
	}
}

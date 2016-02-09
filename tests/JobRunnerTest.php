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
 * Mocks time() in the current namespace, for testing purposes.
 * @return int
 */
function time()
{
	global $mockTime;
	if ($mockTime)
	{
		return \time() - 120;
	}
	return \time();
}

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
		$jr->addJob(new JobDefinition(JobStub::class));
		$jobs = $jr->getJobs();

		$this->assertArrayHasKey(JobStub::class, $jobs);
		$this->assertCount(1, $jobs);

		// Check job default definitions
		$job = $jobs[JobStub::class];

		$this->assertInstanceOf(JobDefinition::class, $job);

		// Check enabled by default, and ReflectionClass saved
		$this->assertTrue($job->getEnabled());
		$this->assertInstanceOf(ReflectionClass::class, $job->getReflection());

		// Adding it twice should have no effect
		$jr->addJob(new JobDefinition(JobStub::class));

		$this->assertArrayHasKey(JobStub::class, $jobs);
		$this->assertCount(1, $jobs);

		// Try again, setting some definition values
		$jr = new JobRunner();
		$jd = new JobDefinition(JobStub::class, false, null, 3600);

		// should not be retained
		$jd->setLastRunTimeStart(time());
		$jd->setLastRunTimeFinish(time());
		$jr->addJob($jd);

		$job = $jr->getJobs()[JobStub::class];

		$this->assertInstanceOf(JobDefinition::class, $job);

		$this->assertFalse($job->getEnabled());
		$this->assertEquals(3600, $job->getInterval());

		// Should not retain given values
		$this->assertEmpty($job->getLastRunTimeStart());
		$this->assertEmpty($job->getLastRunTimeFinish());
		$this->assertInstanceOf(ReflectionClass::class, $job->getReflection());

		// Add a job with a run_time and interval set to see that interval is ignored and set back to null
		$definition = new JobDefinition(JobStub::class, true, '12:00', 5);
		$jr = new JobRunner();

		$jr->addJob($definition);
		$updated_jd = $jr->getJob($definition->getClassName());
		$this->assertNull($updated_jd->getInterval());

		// Test adding a class extending JobDefinition
		$jr = new JobRunner();
		$jr->addJob(new ExtendingJobDefinition(JobStub::class));
		$this->assertInstanceOf(JobDefinition::class, $jr->getJob(JobStub::class));

		// Adding a non-Job class should throw an exception
		$this->setExpectedException(Exception::class);

		$jr = new JobRunner();
		$jr->addJob(new JobDefinition(stdClass::class));

	}

	/**
	 * Tests running a job.
	 * @return void
	 */
	public function testRun()
	{
		// When set to true, time() will return 2 minutes past
		global $mockTime;

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
		$jr->addJob(new JobDefinition(JobStub::class, true, null, -1));

		// Should only run once every 100 seconds
		$jr->addJob(new JobDefinition(AnotherJobStub::class, true, null, 100));

		// Call run() twice, so that the mock expectations are satisfied
		$jr->run();

		// Make sure job run times are set
		$jobs = $jr->getJobs();

		$this->assertNotEmpty($jobs[JobStub::class]->getLastRunTimeStart());
		$this->assertNotEmpty($jobs[AnotherJobStub::class]->getLastRunTimeStart());

		$jr->run();

		// Test we can't run a job when it's already running
		$mock->shouldReceive('work_running')->with(JobStub::class)->once()->andReturn(['foo']);
		$mock->shouldNotReceive('addwork');

		$jr->run();

		// Test running a job by run_time
		$mock = m::mock('fork_daemon')->makePartial();
		$jr = new JobRunner($mock);

		// AnotherJobStub should not trigger
		$mock->shouldNotReceive('addwork')
			->with([AnotherJobStub::class], AnotherJobStub::class, AnotherJobStub::class);
		$mock->shouldNotReceive('process_work')->with(false, AnotherJobStub::class);

		// Set interval to false and define a run_time that isn't now
		$jr->addJob(new JobDefinition(AnotherJobStub::class, true, (new DateTime('+1 hour'))->format('H:i'), false));


		// JobStub should trigger twice because of the time() mocking magic below
		$mock->shouldReceive('addwork')
			->with([JobStub::class], JobStub::class, JobStub::class)
			->twice();
		$mock->shouldReceive('process_work')->with(false, JobStub::class)->twice();

		// Unset interval, and define a run_time of now.
		$jr->addJob(new JobDefinition(JobStub::class, true, (new DateTime())->format('H:i'), false));

		// Run, but by mocking time here, we're setting last_run_time_start
		// back two minutes, so we should be able to test the condition where
		// last_run_time_start is set, but the job should run anyway.
		$mockTime = true;
		$jr->run();

		// Run it again, but without mocking time this time.
		$mockTime = false;
		$jr->run();

		// Running a second time shouldn't do anything (running within the same minute)
		$jr->run();

		// Test disabled job
		$mock = m::mock('fork_daemon')->makePartial();
		$jr = new JobRunner($mock);

		$mock->shouldNotReceive('addwork')->with([JobStub::class], JobStub::class, JobStub::class);
		$mock->shouldNotReceive('process_work')->with(false, JobStub::class);

		$jr->addJob(new JobDefinition(JobStub::class, false));
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
		$jr->addJob(new JobDefinition(JobStub::class));
		$jr->processWork(array(JobStub::class));

		// JobStub's start() method will toggle this.
		$this->assertTrue($jobStarted);

		$jr->addJob(new JobDefinition(ForkingJobStub::class));
		$jr->processWork(array(ForkingJobStub::class));

		// ForkingJobStub's setUpForking() method will toggle this.
		$this->assertTrue($setUpForkingCalled);

		// Try adding work for a non-existent job
		$jr->processWork(array('Foo'));

		$jr->addJob(new JobDefinition(ExceptionJobStub::class));
		$jr->processWork(array(ExceptionJobStub::class));
	}

	public function testGetJob()
	{
		$jr = new JobRunner();

		$jr->addJob(new JobDefinition(JobStub::class));
		$this->assertInstanceOf(JobDefinition::class, $jr->getJob(JobStub::class));

		// Trying to get a non-existent job should throw an exception
		$this->setExpectedException(InvalidArgumentException::class);
		$jr->getJob(AnotherJobStub::class);
	}

	public function testParentChildExit()
	{
		$mock = m::mock('fork_daemon')->shouldIgnoreMissing();
		$jr = new JobRunner($mock);

		$mock->shouldReceive('getForkedChildren')->andReturn([
			1 => ['bucket' => JobStub::class],
		])->once();

		$jr->addJob(new JobDefinition(JobStub::class));
		$jr->run();

		$jr->parentChildExit(1);

		$this->assertInternalType('int', $jr->getJob(JobStub::class)->getLastRunTimeFinish());
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
	public function createWork()
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
	public function cleanUp()
	{
	}
}

class ExtendingJobDefinition extends JobDefinition
{
	/**
	 * @var int
	 */
	private $someOtherVar;

	/**
	 * @return int
	 */
	public function getSomeOtherVar()
	{
		return $this->someOtherVar;
	}

	/**
	 * @param int $someOtherVar
	 */
	public function setSomeOtherVar($someOtherVar)
	{
		$this->someOtherVar = $someOtherVar;
	}
}

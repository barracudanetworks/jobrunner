<?php

namespace Barracuda\JobRunner;

class JobDefinitionTest extends \PHPUnit_Framework_TestCase
{
	public function testConstructor()
	{
		$jd = new JobDefinition(self::class, false, '12:00', 55, 1234);
		$this->assertEquals(self::class, $jd->getClassName());
		$this->assertEquals(false, $jd->getEnabled());
		$this->assertEquals('12:00', $jd->getRunTime());
		$this->assertEquals(55, $jd->getInterval());
		$this->assertEquals(1234, $jd->getMaxRunTime());
	}

	public function testGettersAndSetters()
	{
		$expected_int = 123;
		$expected_string = 'lala';

		$jd = new JobDefinition(self::class);

		$jd->setLastRunTimeStart($expected_int);
		$this->assertEquals($expected_int, $jd->getLastRunTimeStart());

		$jd->setLastRunTimeFinish($expected_int);
		$this->assertEquals($expected_int, $jd->getLastRunTimeFinish());

		$jd->setReflection(new \ReflectionClass(self::class));
		$this->assertInstanceOf(\ReflectionClass::class, $jd->getReflection());

		$jd->setClassName($expected_string);
		$this->assertEquals($expected_string, $jd->getClassName());

		$jd->setEnabled(false);
		$this->assertEquals(false, $jd->getEnabled());

		$jd->setMaxRunTime($expected_int);
		$this->assertEquals($expected_int, $jd->getMaxRunTime());

		$jd->setRunTime($expected_string);
		$this->assertEquals($expected_string, $jd->getRunTime());

		$jd->setInterval($expected_int);
		$this->assertEquals($expected_int, $jd->getInterval());
	}
}

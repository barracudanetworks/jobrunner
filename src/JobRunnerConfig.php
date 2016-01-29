<?php

namespace Barracuda\JobRunner;


class JobRunnerConfig
{
	private $psr4Path;

	private $dirPath;

	private $jobName;

	/**
	 * CronDaemonConfig constructor.
	 * @param $psr4Path
	 * @param $dirPath
	 * @param $jobName
	 */
	public function __construct($psr4Path, $dirPath, $jobName = null)
	{
		$this->psr4Path = $psr4Path;
		$this->dirPath = $dirPath;
		$this->jobName = $jobName;
	}

	/**
	 * @return string
	 */
	public function getPsr4Path()
	{
		return $this->psr4Path;
	}

	/**
	 * @param string $psr4Path
	 */
	public function setPsr4Path($psr4Path)
	{
		$this->psr4Path = $psr4Path;
	}

	/**
	 * @return string
	 */
	public function getDirPath()
	{
		return $this->dirPath;
	}

	/**
	 * @param string $dirPath
	 */
	public function setDirPath($dirPath)
	{
		$this->dirPath = $dirPath;
	}

	/**
	 * @return string
	 */
	public function getJobName()
	{
		return $this->jobName;
	}

	/**
	 * @param string $jobName
	 */
	public function setJobName($jobName)
	{
		$this->jobName = $jobName;
	}
}

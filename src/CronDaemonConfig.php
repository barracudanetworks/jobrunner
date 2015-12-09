<?php

namespace Barracuda\CronDaemon;


class CronDaemonConfig
{
	private $psr4Path;

	private $dirPath;

	/**
	 * CronDaemonConfig constructor.
	 * @param $psr4Path
	 * @param $dirPath
	 */
	public function __construct($psr4Path, $dirPath)
	{
		$this->psr4Path = $psr4Path;
		$this->dirPath = $dirPath;
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
}

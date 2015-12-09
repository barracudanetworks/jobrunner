<?php

namespace Barracuda\CronDaemon;


interface ForkingModuleInterface extends ModuleInterface
{
	public function setNumChildren($numChildren);

	public function getNumChildren();

	public function setItemCount($itemCount);

	public function getItemCount();
}

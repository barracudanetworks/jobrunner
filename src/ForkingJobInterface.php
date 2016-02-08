<?php

namespace Barracuda\JobRunner;


interface ForkingJobInterface extends JobInterface
{
	public function prepareToFork();

	public function setNumChildren($numChildren);

	public function getNumChildren();

	public function setItemCount($itemCount);

	public function getItemCount();
}

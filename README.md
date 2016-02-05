# PHP Job Runner
A library to make runnable jobs with PHP easy. The library also supports creating jobs that have children backed by our PHP Fork Daemon.

## Usage
Create your jobs by extending `Job`, or the special `ForkingJob` class for work that will utilize forking children.

Instantiate `JobRunner`, and add your jobs using the `addJob($class, $options)` method. Then execute the `run()` method in a loop to daemonize the process.

Try the example in the `examples` folder by running `php examples/jobrunner.php`.

## Setting options
- `JobRunner->addJob()` accepts two parameters: First, a job class (e.g. `Vendor\Package\Job::class` or `'Vendor\Package\Job'`). Secondly, an array of options.
  - `enabled` may be set to `"false"` to disable the job. By default, it is set to "true".
  - `run_time` may be set to a time at which a job should be run (e.g. `"11:30"`).
  - `interval` may be set to an interval (in seconds) on which the job should run (e.g. `3600` to run every hour).
- To set the number of child workers in a forking job, and the number of work units they should process, override `__construct` in `ForkingJob` to set your own settings:
  - `$this->setNumChildren(int)` sets the max number of children your job can have.
  - `$this->setItemCount(int)` sets the amount of work each child should do. See `examples/ForkingComplimenter.php` for an example.
  - *Note*: You should still call `parent::__construct($logger)` before using the above methods.

## Caveats
- You need to specify `declare(ticks=1);` before inclusion of the fork-daemon library, otherwise signals wont be handled. This *must* be done in the main PHP file, as `declare(ticks=N);` only works for the file, and files included by the file, in which it is declared in. Reference: [PHP Documentation](http://php.net/manual/en/control-structures.declare.php#control-structures.declare.ticks)
- OSX and Windows are unsupported.

## License
Copyright 2015 Barracuda Networks, Inc.
Licensed under the MIT License

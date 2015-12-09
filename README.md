# PHP Cron Daemon
A library to make cron jobs with PHP easy. The library also supports creating modules that have children backed by our
PHP Fork Daemon.

## Usage
Create your modules and extend ``Module`` or ``ForkingModule`` for work that will need to be in forked processes.
Instantiate ``CronDaemon`` and execute the ``run()`` method in a loop to daemonize the process.

See examples in the ``examples`` folder by running ``php crondaemon.php``.

## Setting options
-   Override the ``__construct`` in your class extending ``Module`` to set your own settings:
    -   ``$this->setRunInterval(int)`` sets how often you want your module to run. E.g. every 10 hours (millis)
    -   ``$this->setMaxRuntime(int)`` sets the longest time you want this module to run. After the time you set in millis, it will be stopped.
    -   ``$this->setState(ModuleConstants)`` can be used to never run a module. E.g. ``$this->setState(ModuleConstants::MODULE_NO_START);``
-   Override the ``__construct`` in your class extending ``ForkingModule`` to set your own settings:
    -   ``$this->setNumChildren(int)`` sets the max number of children your module can have.
    -   ``$this->setItemCount(int)`` sets the amount of work each child should do. See ``examples/ForkingComplimenter.php`` for an example.

## Caveats
-	You need to specify ``declare(ticks=1);`` before inclusion of the fork-daemon library, otherwise signals wont be handled. This *must* be done in the main PHP file, as ``declare(ticks=N);`` only works for the file in which it is declared and the files which that file includes. Reference: [PHP Documentation](http://php.net/manual/en/control-structures.declare.php#control-structures.declare.ticks)

## License
Copyright 2013 Barracuda Networks, Inc.
Licensed under the MIT License
# PHP Cron Daemon
A library to make cron jobs with php easy. The library also supports creating modules that have children backed by our
PHP Fork Daemon.

## Usage
Create your modules and extend ``Module`` or ``ForkingModule`` for work that will need to be in forked processes.
Instantiate ``CronDaemon`` and execute the ``run()`` method in a loop to daemonize the process.

See examples in the ``examples`` folder by running ``php crondaemon.php``.


## Caveats
-	You need to specify ``declare(ticks=1);`` before inclusion of the fork-daemon library, otherwise signals wont be handled. This *must* be done in the main PHP file, as ``declare(ticks=N);`` only works for the file in which it is declared and the files which that file includes. Reference: [PHP Documentation](http://php.net/manual/en/control-structures.declare.php#control-structures.declare.ticks)

## License
Copyright 2013 Barracuda Networks, Inc.
Licensed under the MIT License
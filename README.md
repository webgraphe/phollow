# Live PHP Error Tracking

[Phollow](https://github.com/webgraphe/phollow) (pronounced \\ˈfälō\\) is a PHP error tracking utility for developers
running a server in the background to track your application's errors.

**DON'T use this in production.**

This project works better with the `pcntl` extension (shipped by default on non-Windows PHP executables).

## Installation

The installation process is simply put adding a development depending on your project and registering a error handler.

### Add dependency on your project

```bash
composer require webgraphe/phollow --dev
```

### (optional) Create configuration 

You may dump a configuration INI file that would be used by both the error handler and the server. The server defaults
to `phollow.ini` if it exists.

```bash
vendor/bin/phollow generate-configuration > phollow.ini
```

You may edit the file to customize the few settings available.

### Register the error handler

This should be done as early in the execution process of your project as possible.

```php
<?php

use Webgraphe\Phollow\ErrorHandler;
use Webgraphe\Phollow\Configuration;
use Webgraphe\Phollow\Documents\Error;

$configuration = Configuration::fromIniFile('path/to/configuration.ini');
ErrorHandler::create($configuration->getErrorLogFile())
    ->withBasePath('path/to/project') // fancies backtraces
    ->withErrorReporting(E_ALL) // calls error_reporting() for you and shuts down error display
    ->withErrorFilter( // filters errors reported using an indicator function
        function (Error $error) {
            return 'report-error-from.my-host.only' === $error->getHost();
        }
    )
    ->register(); // you're done, next PHP error will be reported to the handler
```

### Launch the server

The error handler will only track error if the file tailed by the server exists (e.g. usually only available when
the server is running):

```bash
vendor/bin/phollow run
```

Hit the URL for the HTTP server listed.

To get support on the server commands:

```bash
vendor/bin/phollow help
```

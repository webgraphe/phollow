# Live PHP Error Tracking

[Phollow](https://github.com/webgraphe/phollow) (pronounced \\ˈfälō\\) is a PHP error tracking utility for developers
running a server in the background to track your application's errors.

**DON'T use this in production.**

This library relies on Unix sockets and is therefore not compatible natively on Windows machines.

## Installation

Put simply, the installation process consists in adding a development dependency to your project's Composer and registering the error handler.

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

### Register the error handler

This should be done as early in the execution process of your PHP project as possible.

```php
<?php

use Webgraphe\Phollow\ErrorHandler;
use Webgraphe\Phollow\Configuration;
use Webgraphe\Phollow\Documents\Error;

$configuration = Configuration::fromIniFile('path/to/configuration.ini');
ErrorHandler::create($configuration->getLogFile())
    // Fancies backtraces
    ->withBasePath('path/to/project')
    // Calls error_reporting() for you and shuts down error display
    ->withErrorReporting(E_ALL)
    // Filters errors reported using an indicator function
    ->withErrorFilter(
        function (Error $error) {
            return 'report-error-from.my-host.only' === $error->getHostName();
        }
    )
    // You're done! next PHP error triggered will be reported to the handler
    ->register();
```

### Launch the server

The error handler will only track errors if the file tailed by the server exists (e.g. usually only available when
the server is running):

```bash
vendor/bin/phollow run
```

Hit the URL for the HTTP server listed.

To get support on the server commands:

```bash
vendor/bin/phollow help
```

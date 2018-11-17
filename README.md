# Live PHP Script Monitoring

[Phollow](https://github.com/webgraphe/phollow) (pronounced \\ˈfälō\\) is a lightweight PHP script monitoring utility 
for developers running a server in the background to track your application's performance and errors.

This library is **not ready for production** use. It relies on Unix sockets and is therefore incompatible
_natively_ on Windows machines.

## Installation

Put simply, the installation process consists in adding a development dependency to your project's
[Composer](http://www.getcomposer.org) and registering the error handler.

Your bare bones installation goes like this:

### Add dependency on your project

```bash
composer require webgraphe/phollow --dev
```

### Register the error handler

As early as possible in the execution process of your PHP projects:

```php
<?php

use Webgraphe\Phollow\ErrorHandler;

ErrorHandler::create()->register();
```

### Launch the server

```bash
vendor/bin/phollow run --colors
```

### Follow tracked errors in your browser

Once server runs, hit the URL listed for the HTTP server. 

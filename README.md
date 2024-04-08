<img src="Turso-Syncd.gif" width="100%" />

<h1 align="center">~ TursoSyncd ~</h1>

Turso Dabatabase Background Sync in the Background

<h2>Installation</h2>

**Globally Install**

```bash
composer global require darkterminal/turso-syncd
```

or you can install locally within your porject:

```bash
composer require darkterminal/turso-syncd
```

<h2>Usage</h2>

```bash
turso-syncd --database=<database_name> --organization=<organization_name> --token=<token> [--file_recorder=<file>] [--action_log_file=<file>] [--errors_log_file=<file>]
```

<h2>Options</h2>

- `--database=<database_name>` or `-d`: The name of the database.
- `--organization=<organization_name>` or `-o`: The name of the organization.
- `--token=<token>` or `-t`: The token for authentication.
- `--file_recorder=<file>`: Specify file recorder.
- `--action_log_file=<file>`: Specify action log file.
- `--errors_log_file=<file>`: Specify errors log file.
- `--help`: Display this help message.

<h2>Extend in Your Own Daemon</h2>

```php
<?php

use Darkterminal\TursoSyncd;

require_once "vendor/autoload.php";

$databaseName       = "your-database-name";
$organizationName   = "your-organization-name";
$token              = "your-turso-token";
$config             = [
    'file_recorder'     => "/path/to/your/recorder-file/recorded_queries.json",
    'action_log_file'   => "/path/to/your/action-log-file/actions.log",
    'errors_log_file'   => "/path/to/your/error-log-file/errors.log",
];

$tursoSyncd = new TursoSyncd($databaseName, $organizationName, $token, $config);
$tursoSyncd->start();
```

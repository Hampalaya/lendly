<?php

// Run from the project: php scripts/import-database.php
// Validate the input without connecting: php scripts/import-database.php --check

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
chdir($root);
$envChanged = false;
$target = null;
$backupDirectory = null;
$cachedConfig = null;
$envBefore = null;

try {
    if (array_diff(array_slice($argv, 1), ['--check'])) {
        throw new RuntimeException('Usage: php scripts/import-database.php [--check]');
    }

    $sql = file_get_contents($root.'/lendly.sql');
    if ($sql === false || ! preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches)) {
        throw new RuntimeException('lendly.sql is missing or contains no tables.');
    }
    if (preg_match('/^\s*(?:USE\s|(?:CREATE|DROP|ALTER)\s+(?:DATABASE|SCHEMA)\b|DELIMITER\b)/im', $sql)) {
        throw new RuntimeException('The dump must contain only tables and data for a single database.');
    }
    $tables = $matches[1];
    foreach (['users', 'listings', 'rental_requests', 'rentals', 'migrations'] as $table) {
        if (! in_array($table, $tables, true)) {
            throw new RuntimeException("Required table missing from dump: {$table}");
        }
    }
    echo 'Dump checked: '.count($tables).' tables; SHA256 '.hash('sha256', $sql).PHP_EOL;
    if (in_array('--check', $argv, true)) {
        exit(0);
    }

    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    if (! $app->environment('local')) {
        throw new RuntimeException('This replacement helper is intended for APP_ENV=local only.');
    }

    $source = DB::connection();
    $config = $source->getConfig();
    if (! in_array($config['driver'], ['mysql', 'mariadb'], true) || ! empty($config['url'])) {
        throw new RuntimeException('Use a MySQL/MariaDB connection with individual DB_* settings, without DB_URL.');
    }
    if (! empty($config['read']) || ! empty($config['write']) || ! empty($config['unix_socket']) || ! is_string($config['host'])) {
        throw new RuntimeException('This helper requires a single MySQL TCP host.');
    }
    $envBefore = file_get_contents($root.'/.env');
    if ($envBefore === false || ! is_writable($root.'/.env')) {
        throw new RuntimeException('The project .env must exist and be writable.');
    }
    $pdo = $source->getPdo(); // Stop here if the current database cannot be reached.
    $current = $source->getDatabaseName();
    $dumpExecutable = (new ExecutableFinder)->find('mysqldump');
    if (! $dumpExecutable) {
        throw new RuntimeException('mysqldump must be on PATH. Use the Laragon terminal.');
    }

    $stamp = date('Ymd_His').'_'.bin2hex(random_bytes(3));
    $backupDirectory = $root.'/storage/app/private/database-backups/'.$stamp;
    if (! mkdir($backupDirectory, 0700, true)) {
        throw new RuntimeException('Could not create the backup directory.');
    }
    $backup = $backupDirectory.'/previous-database.sql';
    if (file_put_contents($backupDirectory.'/previous.env', $envBefore) === false) {
        throw new RuntimeException('Could not back up .env.');
    }
    $configCachePath = $app->getCachedConfigPath();
    if (is_file($configCachePath)) {
        $cachedConfig = file_get_contents($configCachePath);
        if ($cachedConfig === false || file_put_contents($backupDirectory.'/previous-config.php', $cachedConfig) === false) {
            throw new RuntimeException('Could not back up cached configuration.');
        }
    }
    echo "Backing up {$current}...".PHP_EOL;
    $process = new Process([
        $dumpExecutable, '--no-defaults', '--protocol=TCP',
        '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
        '--single-transaction', '--routines', '--events', '--triggers', '--hex-blob',
        '--no-tablespaces', '--set-gtid-purged=OFF', '--column-statistics=0',
        '--result-file='.$backup, '--', $current,
    ], $root, ['MYSQL_PWD' => (string) $config['password']]);
    $process->setTimeout(null);
    $process->run();
    clearstatcache(true, $backup);
    if (! $process->isSuccessful() || ! is_file($backup) || filesize($backup) === 0) {
        throw new RuntimeException('Backup failed; no database was changed. '.$process->getErrorOutput());
    }
    echo "Backup saved: {$backup}".PHP_EOL;

    // Never drop or overwrite an existing database, including a previous import.
    $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $exists->execute(['lendly']);
    $target = $exists->fetchColumn() ? 'lendly_import_'.$stamp : 'lendly';
    $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $targetConfig = $config;
    $targetConfig['database'] = $target;
    $targetConfig['url'] = null;
    $targetConfig['options'][PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
    config(['database.connections.lendly_import' => $targetConfig]);
    DB::setDefaultConnection('lendly_import');
    echo "Importing into {$target}...".PHP_EOL;
    $import = DB::connection('lendly_import');
    $import->getPdo()->exec($sql);
    foreach ($tables as $table) {
        $import->table($table)->count();
    }

    echo 'Applying pending migrations...'.PHP_EOL;
    $result = Artisan::call('migrate', ['--database' => 'lendly_import', '--force' => true]);
    echo Artisan::output();
    if ($result !== 0) {
        throw new RuntimeException('Migrations failed.');
    }
    $migrator = $app->make('migrator');
    $files = $migrator->getMigrationFiles([$root.'/database/migrations']);
    if (array_diff(array_keys($files), $migrator->getRepository()->getRan())) {
        throw new RuntimeException('Some migrations are still pending.');
    }

    $envAfter = preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE='.$target, $envBefore, -1, $count);
    if ($count !== 1) {
        throw new RuntimeException('.env must contain exactly one DB_DATABASE line.');
    }
    // Detect an editor changing .env while the import was in progress.
    if (file_get_contents($root.'/.env') !== $envBefore) {
        throw new RuntimeException('.env changed during the import; connection was not switched.');
    }
    $envChanged = true;
    if (file_put_contents($root.'/.env', $envAfter, LOCK_EX) === false) {
        throw new RuntimeException('Could not update .env.');
    }
    if (Artisan::call('config:clear') !== 0) {
        throw new RuntimeException('Could not clear cached configuration.');
    }

    // Verify in a new PHP process, using the connection the application will load.
    $verifyCode = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db = Illuminate\Support\Facades\DB::connection();
    if ($db->selectOne('SELECT DATABASE() AS name')->name !== $argv[1]) { exit(1); }
    foreach (['users', 'listings', 'rental_requests', 'rentals', 'sessions'] as $table) {
        echo $table.': '.$db->table($table)->count().PHP_EOL;
    }
    PHP;
    $verify = new Process([PHP_BINARY, '-r', $verifyCode, $target], $root, [
        'DB_DATABASE' => $target, 'DB_URL' => false, 'APP_CONFIG_CACHE' => $configCachePath,
    ]);
    $verify->setTimeout(60);
    $verify->run();
    if (! $verify->isSuccessful()) {
        throw new RuntimeException('Verification failed. '.$verify->getErrorOutput());
    }
    echo $verify->getOutput();
    echo "Success: Lendly now uses {$target}. Previous database {$current} was preserved.".PHP_EOL;
    echo "Restart php artisan serve and any queue workers to load the new connection.".PHP_EOL;
} catch (Throwable $error) {
    if ($envChanged && $envBefore !== null) {
        $restored = file_put_contents($root.'/.env', $envBefore, LOCK_EX) !== false;
        if ($cachedConfig !== null) {
            $restored = (file_put_contents($configCachePath, $cachedConfig, LOCK_EX) !== false) && $restored;
        }
        fwrite(STDERR, $restored ? "Previous connection settings restored.\n" : "Could not restore connection settings; restore them from {$backupDirectory}.\n");
    }
    fwrite(STDERR, 'Stopped: '.$error->getMessage().PHP_EOL);
    if ($target !== null) {
        fwrite(STDERR, "Import database {$target} was left in place for inspection.\n");
    }
    exit(1);
}

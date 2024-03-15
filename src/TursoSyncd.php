<?php

namespace Darkterminal;

use Darkterminal\TursoHTTP;
use React\EventLoop\Loop;

class TursoSyncd
{
    private $recorder;
    private $tursoAPI;
    private $db;
    private $isEmpty = false;

    public function __construct($databaseName, $organizationName, $token, $options = [])
    {
        if (!empty($options)) {
            $requiredKeys = ['file_recorder', 'action_log_file', 'errors_log_file'];

            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $options)) {
                    throw new \InvalidArgumentException("Missing required option: $key");
                }
            }
        }

        $jsonFilePath = empty($options['file_recorder']) ? __DIR__ . '/../logs/recorded_queries.json' : $options['file_recorder'];
        $actionLogFilePath = empty($options['action_log_file']) ? null : $options['action_log_file'];
        $errorsLogFilePath = empty($options['errors_log_file']) ? null : $options['errors_log_file'];

        $this->recorder = new DatabaseOperationRecorder($jsonFilePath, $actionLogFilePath, $errorsLogFilePath);
        $this->tursoAPI = new TursoHTTP($databaseName, $organizationName, $token);

        $this->connectToDatabase($databaseName);
    }

    private function connectToDatabase($databaseName)
    {
        if (!file_exists("{$databaseName}.db")) {
            $dbFile = fopen("{$databaseName}.db", "w");
            fclose($dbFile);
            chmod("{$databaseName}.db", 0644);

            $os = strtoupper(substr(PHP_OS, 0, 3));
            if ($os === 'WIN') {
                $dumpCommand = "turso db shell {$databaseName} .dump > {$databaseName}.sql";
                exec($dumpCommand, $dumpOutput, $dumpStatus);
                if ($dumpStatus !== 0) {
                    unlink("{$databaseName}.db");
                    $errorMessage = "Error encountered while dumping the database.";
                    $this->recorder->log('ERROR', $errorMessage);
                    echo $errorMessage . PHP_EOL;
                    exit(1);
                }

                $sqliteCommand = "sqlite3 {$databaseName}.db < {$databaseName}.sql";
                exec($sqliteCommand, $sqliteOutput, $sqliteStatus);
                if ($sqliteStatus !== 0) {
                    unlink("{$databaseName}.db");
                    $errorMessage = "Error encountered while importing data to the database.";
                    $this->recorder->log('ERROR', $errorMessage);
                    echo $errorMessage . PHP_EOL;
                    exit(1);
                }

                unlink("{$databaseName}.sql");
            } else {
                $command = "turso db shell {$databaseName} .dump > {$databaseName}.sql && sqlite3 {$databaseName}.db < {$databaseName}.sql && rm {$databaseName}.sql";
                exec($command, $output, $status);
                if ($status !== 0) {
                    unlink("{$databaseName}.db");
                    unlink("{$databaseName}.sql");
                    $errorMessage = "Error encountered while executing commands.";
                    $this->recorder->log('ERROR', $errorMessage);
                    echo $errorMessage . PHP_EOL;
                    exit(1);
                }
            }
        }

        try {
            $this->db = new \SQLite3("{$databaseName}.db");

            if (!$this->db) {
                $errorConnection = 'Whoops, could not connect to the SQLite database!';
                $this->recorder->log('ERROR', $errorConnection);
                echo $errorConnection . PHP_EOL;
                exit();
            }

            $successConnection = 'Connected to the SQLite database successfully!';
            $this->recorder->log('INFO', $successConnection);
            echo $successConnection . PHP_EOL;
        } catch (\Exception $e) {
            $errorException = 'Exception: ' . $e->getMessage();
            $this->recorder->log('ERROR', $errorException);
            echo $errorException . PHP_EOL;
            exit();
        }
    }

    public function start()
    {
        $intervalCallback = function () {
            if (!empty($this->recorder->listRecords())) {
                echo time() . ": The queue are not empty" . PHP_EOL;
                $records = $this->recorder->listRecords();
                usort($records, function ($a, $b) {
                    return $a["timestamp"] - $b["timestamp"];
                });
                print_r($records);
                foreach ($records as $record) {
                    $localWrite = $this->db->exec($record['query']);
                    if ($localWrite === false) {
                        $error = "Error: " . $this->db->lastErrorMsg();
                        $this->recorder->log('ERROR', $error);
                        echo $error . PHP_EOL;
                    }

                    $this->recorder->log('DEBUG', $record['query']);
                    $remoteWrite = $this->tursoAPI->addRequest('execute', $record['query'])->addRequest('close')->queryDatabase()->toJSON();
                    $writeResult = $remoteWrite;
                    $this->recorder->log('INFO', $writeResult);
                    echo $writeResult . PHP_EOL;

                    sleep(5);
                    $this->recorder->removeRecordedQuery($record);
                    echo $record['timestamp'] . PHP_EOL;
                }
                return;
            }

            if (!$this->isEmpty) {
                echo "The queue are empty..." . PHP_EOL;
                $this->isEmpty = true;
            }
        };

        Loop::addPeriodicTimer(1, $intervalCallback);
    }
}

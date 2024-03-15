<?php
namespace Darkterminal;

class DatabaseOperationRecorder
{
    private $jsonFilePath;
    private $errorsLogFilePath;
    private $actionLogFilePath;

    public function __construct($jsonFilePath, $actionLogFilePath = null, $errorsLogFilePath = null)
    {
        $this->jsonFilePath = $jsonFilePath;
        $this->actionLogFilePath = $actionLogFilePath == null ? __DIR__ . '/../logs/actions.log' : $actionLogFilePath;
        $this->errorsLogFilePath = $errorsLogFilePath == null ? __DIR__ . '/../logs/errors.log' : $errorsLogFilePath;
    }

    public function listRecords(): array
    {
        return file_exists($this->jsonFilePath) ? json_decode(file_get_contents($this->jsonFilePath), true) : [];
    }

    public function recordQuery($type, $query): bool
    {
        $currentData = $this->listRecords();
        $currentData[] = ['type' => $type, 'query' => $query, 'timestamp' => time()];
        file_put_contents($this->jsonFilePath, json_encode($currentData, JSON_PRETTY_PRINT));

        return true;
    }

    public function removeRecordedQuery($queryData): bool
    {
        $recordedData = file_exists($this->jsonFilePath) ? json_decode(file_get_contents($this->jsonFilePath), true) : [];

        foreach ($recordedData as $key => $record) {
            if ($record['timestamp'] === $queryData['timestamp'] && $record['query'] === $queryData['query'] && $record['type'] === $queryData['type']) {
                unset($recordedData[$key]);
                break;
            }
        }

        file_put_contents($this->jsonFilePath, json_encode(array_values($recordedData), JSON_PRETTY_PRINT));

        return true;
    }

    public function log($type = 'INFO', $message): bool
    {
        $type = strtoupper($type);
        $logMessage = date('Y-m-d H:i:s') . ' ' . $type . ' - ' . $message . PHP_EOL;

        switch ($type) {
            case 'ERROR':
                file_put_contents($this->errorsLogFilePath, $logMessage, FILE_APPEND | LOCK_EX);
                break;
            case 'INFO':
            case 'DEBUG':
            case 'WARNING':
                file_put_contents($this->actionLogFilePath, $logMessage, FILE_APPEND | LOCK_EX);
                break;

            default:
                file_put_contents($this->actionLogFilePath, $logMessage, FILE_APPEND | LOCK_EX);
                break;
        }

        return true;
    }
}

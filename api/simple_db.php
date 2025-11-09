<?php

class SimpleDatabase {
    private $dataDir;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/';
        if (!file_exists($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }
    
    private function getFilePath($table) {
        return $this->dataDir . $table . '.json';
    }
    
    private function read($table) {
        $file = $this->getFilePath($table);
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    
    private function write($table, $data) {
        $file = $this->getFilePath($table);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function insert($table, $data) {
        $records = $this->read($table);
        $data['id'] = count($records) + 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $records[] = $data;
        $this->write($table, $records);
        return $data;
    }
    
    public function findOne($table, $key, $value) {
        $records = $this->read($table);
        foreach ($records as $record) {
            if (isset($record[$key]) && $record[$key] === $value) {
                return $record;
            }
        }
        return null;
    }
    
    public function findAll($table, $key = null, $value = null) {
        $records = $this->read($table);
        if ($key === null) {
            return $records;
        }
        return array_filter($records, function($record) use ($key, $value) {
            return isset($record[$key]) && $record[$key] === $value;
        });
    }
    
    public function update($table, $id, $data) {
        $records = $this->read($table);
        foreach ($records as &$record) {
            if ($record['id'] === $id) {
                $record = array_merge($record, $data);
                $record['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        $this->write($table, $records);
        return true;
    }
    
    public function delete($table, $id) {
        $records = $this->read($table);
        $records = array_filter($records, function($record) use ($id) {
            return $record['id'] !== $id;
        });
        $this->write($table, array_values($records));
        return true;
    }
}

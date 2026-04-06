<?php

class FenyDB
{
    private $path;
    private $non_indexed_types = ['image'];
    public function __construct($path)
    {
        $this->path = $path;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function dropDatabase()
    {
        $this->deleteDirectory($this->path);
    }


    public function createTable($name)
    {
        $tablePath = $this->path . '/' . $name;
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0777, true);
        }
        if (!is_dir($tablePath . '/index')) {
            mkdir($tablePath . '/index', 0777, true);
        }
    }

    public function dropTable($name)
    {
        //delete the folder even if not empty
        $tablePath = $this->path . '/' . $name;
        if (is_dir($tablePath)) {
            $this->deleteDirectory($tablePath);
        }
    }

    public function createColumn($tableName, $columnName, $type)
    {
        $tablePath = $this->path . '/' . $tableName . '/index';
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0777, true);
        }
        if (!in_array($type, $this->non_indexed_types)) {
            // create json {type="", index={}}
            $columnPath = $tablePath . '/' . $columnName . '.json';
            if (!is_file($columnPath)) {
                file_put_contents($columnPath, json_encode(array('type' => $type, 'index' => array())));
            }
        }
        // write the object structure columns in json file called structure.json
        $structurePath = $this->path . '/' . $tableName . '/structure.json';
        if (!is_file($structurePath)) {
            file_put_contents($structurePath, json_encode(array()));
        }
        $structure = json_decode(file_get_contents($structurePath), true);
        $structure[$columnName] = $type;
        file_put_contents($structurePath, json_encode($structure));
    }


    public function insert($tableName, $data)
    {
        $tablePath = $this->path . '/' . $tableName;
        $indexTablePath = $tablePath . '/index';
        if (!is_dir($tablePath)) {
            return false;
        }
        $structure = json_decode(file_get_contents($tablePath . '/structure.json'), true);
        $data = array_intersect_key($data, $structure);
        $data['id'] = $this->getNextId($tableName);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($tablePath . '/' . $data['id'] . '.json', json_encode($data));
        foreach ($structure as $key => $value) {
            if (!in_array($value, $this->non_indexed_types)) {
                $columnPath = $indexTablePath . '/' . $key . '.json';
                if (!is_file($columnPath)) {
                    file_put_contents($columnPath, json_encode(array('type' => $value, 'index' => array())));
                }
                $column = json_decode(file_get_contents($columnPath), true);
                $column['index'][$data[$key]][] = $data['id'];
                file_put_contents($columnPath, json_encode($column));
            }
        }
        return $data['id'];
    }

    public function find($tableName, $columnName, $value)
    {
        $tablePath = $this->path . '/' . $tableName . '/index';
        $columnPath = $tablePath . '/' . $columnName . '.json';
        if (!is_file($columnPath)) {
            return [];
        }
        $column = json_decode(file_get_contents($columnPath), true);
        if (!isset($column['index'][$value])) {
            return [];
        }
        return $column['index'][$value];
    }

    public function findById($tableName, $id)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            return [];
        }
        return json_decode(file_get_contents($filePath), true);
    }

    public function getAll($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            return [];
        }
        $files = scandir($tablePath);
        $results = [];
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && $file != 'index' && $file != 'structure.json' && is_file($tablePath . '/' . $file)) {
                $results[] = json_decode(file_get_contents($tablePath . '/' . $file), true);
            }
        }
        return $results;
    }

    private function getNextId($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            return 1;
        }
        $files = scandir($tablePath);
        $maxId = 0;
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $tablePath . '/' . $file;
                if (is_file($filePath)) {
                    $maxId = max($maxId, (int) $file);
                }
            }
        }
        return $maxId + 1;
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $dir . '/' . $file;
                if (is_dir($filePath)) {
                    $this->deleteDirectory($filePath);
                } else {
                    unlink($filePath);
                }
            }
        }
        rmdir($dir);
    }
}
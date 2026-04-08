<?php

class FenyDB
{
    private $path;
    private $non_indexed_types = ['image', 'array'];
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

    public function createColumn($tableName, $columnName, $type, $is_indexed = false)
    {
        $tablePath = $this->path . '/' . $tableName . '/index';
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0777, true);
        }
        if (!in_array($type, $this->non_indexed_types) && $is_indexed) {
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
        $structure[$columnName] = array('type' => $type, 'is_indexed' => $is_indexed);
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
        foreach ($structure as $key => $columnDef) {
            if (!in_array($columnDef['type'], $this->non_indexed_types) && $columnDef['is_indexed']) {
                $columnPath = $indexTablePath . '/' . $key . '.json';
                if (!is_file($columnPath)) {
                    file_put_contents($columnPath, json_encode(array('type' => $columnDef['type'], 'index' => array())));
                }
                $column = json_decode(file_get_contents($columnPath), true);
                $column['index'][$data[$key]][] = $data['id'];
                file_put_contents($columnPath, json_encode($column));
            }
        }
        return $data['id'];
    }

    public function update($tableName, $id, $data)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            return false;
        }
        $data['id'] = (int) $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $oldData = json_decode(file_get_contents($filePath), true);
        file_put_contents($filePath, json_encode($data));
        $structure = json_decode(file_get_contents($tablePath . '/structure.json'), true);

        // if indexed update
        foreach ($oldData as $key => $value) {
            if (isset($structure[$key]) && $oldData[$key] != $data[$key] && $structure[$key]['is_indexed'] && !in_array($structure[$key]['type'], $this->non_indexed_types)) {
                $indexPath = $this->path . '/' . $tableName . '/index/' . $key . '.json';
                if (!is_file($indexPath)) {
                    continue;
                }
                $column = json_decode(file_get_contents($indexPath), true);
                unset($column['index'][$oldData[$key]]);
                $column['index'][$data[$key]][] = $data['id'];
                file_put_contents($indexPath, json_encode($column));
            }
        }
        return true;
    }

    public function delete($tableName, $id)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            return false;
        }
        $data = json_decode(file_get_contents($filePath), true);
        unlink($filePath);
        foreach ($data as $key => $value) {
            $indexPath = $this->path . '/' . $tableName . '/index/' . $key . '.json';
            if (!is_file($indexPath)) {
                continue;
            }
            $column = json_decode(file_get_contents($indexPath), true);
            unset($column['index'][$value]);
            file_put_contents($indexPath, json_encode($column));
        }
        return true;
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

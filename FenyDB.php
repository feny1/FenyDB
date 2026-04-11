<?php

class FenyDB
{
    private $tag = "FenyDB";
    private $path;
    private $non_indexed_types = ['image', 'array'];
    public function __construct($path)
    {
        $this->path = $path;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (!is_writable($path)) {
            throw new Exception("$this->tag: You don't have permission to write to the database!");
        }
        if (!is_readable($path)) {
            throw new Exception("$this->tag: You don't have permission to read from the database!");
        }

    }

    public function dropDatabase()
    {
        $this->deleteDirectory($this->path);
    }


    public function createTable($name)
    {
        $tablePath = $this->path . '/' . $name;
        if (is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $name: You can't create a table that already exists!");
        }

        // Explicitly build the entire architecture immediately
        mkdir($tablePath . '/rows', 0777, true);
        mkdir($tablePath . '/metadata', 0777, true);
        mkdir($tablePath . '/index', 0777, true);
    }

    public function dropTable($name)
    {
        //delete the folder even if not empty
        $tablePath = $this->path . '/' . $name;
        if (is_dir($tablePath)) {
            $this->deleteDirectory($tablePath);
        } else {
            throw new Exception("$this->tag, Table $name: You can't drop a table that doesn't exist!");
        }
    }

    public function createColumn($tableName, $columnName, $type, $is_indexed = false)
    {
        $tablePath = $this->path . '/' . $tableName . '/index';
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't create a column in a table that doesn't exist!");
        }
        if (!in_array($type, $this->non_indexed_types) && $is_indexed) {
            $columnPath = $tablePath . '/' . $columnName . '.json';
            if (!is_file($columnPath)) {
                file_put_contents($columnPath, json_encode(array('type' => $type, 'index' => array())));
            }
        }
        $structurePath = $this->path . '/' . $tableName . '/metadata/structure.json';
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
            throw new Exception("$this->tag, Table $tableName: You can't insert into a table that doesn't exist!");
        }
        $structure = $this->getStructure($tableName);
        $_data = array_intersect_key($data, $structure);
        if ($_data != $data) {
            $diff = array_diff_key($data, $structure);
            throw new Exception("$this->tag, Table $tableName: You can't insert into a table with columns that don't exist. Columns: [" . implode(", ", $diff) . "] don't exist!");
        }
        $_data['id'] = $this->getNextId($tableName);
        $_data['created_at'] = date('Y-m-d H:i:s');
        $_data['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($tablePath . '/rows/' . $_data['id'] . '.json', json_encode($_data));
        foreach ($structure as $key => $columnDef) {
            if (!in_array($columnDef['type'], $this->non_indexed_types) && $columnDef['is_indexed']) {
                $this->insertIndex($tableName, $key, $data[$key], $_data['id']);
            }
        }
        return $_data['id'];
    }

    public function insertIndex($tableName, $columnName, $value, $row_id)
    {
        $tablePath = $this->path . '/' . $tableName;

        $hash = md5($value);
        $firstSharedPrefix = substr($hash, 0, 2);
        $secondSharedPrefix = substr($hash, 2, 2);
        $thirdSharedPrefix = substr($hash, 4, 2);
        $fourthSharedPrefix = substr($hash, 6, 2);

        $indexPath = $tablePath . '/index/' . $columnName . '/' . $firstSharedPrefix . '/' . $secondSharedPrefix . '/' . $thirdSharedPrefix . '/' . $fourthSharedPrefix;

        if (!is_dir($indexPath)) {
            mkdir($indexPath, 0777, true);
        }

        $sharedFile = $indexPath . '/' . $hash . '.json';

        $sharedData = is_file($sharedFile) ? $this->readJsonFile($sharedFile) : [];
        $sharedData[$value][] = $row_id;
        file_put_contents($sharedFile, json_encode($sharedData));
    }

    public function deleteIndex($tableName, $columnName, $value, $row_id)
    {
        $tablePath = $this->path . '/' . $tableName;

        $hash = md5($value);
        $firstSharedPrefix = substr($hash, 0, 2);
        $secondSharedPrefix = substr($hash, 2, 2);
        $thirdSharedPrefix = substr($hash, 4, 2);
        $fourthSharedPrefix = substr($hash, 6, 2);

        $indexPath = $tablePath . '/index/' . $columnName . '/' . $firstSharedPrefix . '/' . $secondSharedPrefix . '/' . $thirdSharedPrefix . '/' . $fourthSharedPrefix;

        $sharedFile = $indexPath . '/' . $hash . '.json';

        if (is_file($sharedFile)) {
            $sharedData = $this->readJsonFile($sharedFile);
            if (isset($sharedData[$value])) {
                $sharedData[$value] = array_values(array_diff($sharedData[$value], [$row_id]));
                if (empty($sharedData[$value])) {
                    unset($sharedData[$value]);
                }
            }
            if (empty($sharedData)) {
                unlink($sharedFile);
            } else {
                file_put_contents($sharedFile, json_encode($sharedData));
            }
        }
    }

    public function update($tableName, $id, $data)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/rows/' . $id . '.json';
        if (!is_file($filePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't update a row that doesn't exist!");
        }
        $data['id'] = (int) $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $oldData = json_decode(file_get_contents($filePath), true);
        file_put_contents($filePath, json_encode($data));
        $structure = $this->getStructure($tableName);

        // if indexed update
        foreach ($oldData as $key => $value) {
            if (isset($structure[$key]) && $oldData[$key] != $data[$key] && $structure[$key]['is_indexed'] && !in_array($structure[$key]['type'], $this->non_indexed_types)) {
                $this->deleteIndex($tableName, $key, $oldData[$key], (int) $id);
                $this->insertIndex($tableName, $key, $data[$key], (int) $id);
            }
        }
        return true;
    }

    public function delete($tableName, $id)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/rows/' . $id . '.json';
        if (!is_file($filePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't delete a row that doesn't exist!");
        }
        $data = json_decode(file_get_contents($filePath), true);
        unlink($filePath);
        foreach ($data as $key => $value) {
            $structure = $this->getStructure($tableName);
            if (isset($structure[$key]) && $structure[$key]['is_indexed'] && !in_array($structure[$key]['type'], $this->non_indexed_types)) {
                $this->deleteIndex($tableName, $key, $value, (int) $id);
            }
        }
        return true;
    }

    public function find($tableName, $columnName, $value)
    {
        $tablePath = $this->path . '/' . $tableName . '/index';

        $hash = md5($value);
        $firstSharedPrefix = substr($hash, 0, 2);
        $secondSharedPrefix = substr($hash, 2, 2);
        $thirdSharedPrefix = substr($hash, 4, 2);
        $fourthSharedPrefix = substr($hash, 6, 2);

        $indexPath = $tablePath . '/' . $columnName . '/' . $firstSharedPrefix . '/' . $secondSharedPrefix . '/' . $thirdSharedPrefix . '/' . $fourthSharedPrefix;

        if (!is_dir($indexPath)) {
            return [];
        }

        $sharedFile = $indexPath . '/' . $hash . '.json';

        $sharedData = is_file($sharedFile) ? $this->readJsonFile($sharedFile) : [];

        return $sharedData[$value] ?? [];
    }

    public function findById($tableName, $id)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't find a row in a table that doesn't exist!");
        }
        $filePath = $tablePath . '/rows/' . $id . '.json';
        if (!is_file($filePath)) {
            return [];
        }
        return json_decode(file_get_contents($filePath), true);
    }

    public function getAll($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get all rows from a table that doesn't exist!");
        }
        foreach ($this->dirReadAll("$tablePath/rows") as $file) {
            yield $this->readJsonFile("$tablePath/rows/$file");
        }
    }

    private function getNextId($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get the next id from a table that doesn't exist!");
        }
        $sequencePath = $tablePath . '/metadata/sequence.json';
        $fileHandle = fopen($sequencePath, 'c+');
        if (!$fileHandle) {
            throw new Exception("$this->tag, Table $tableName: There's a problem with the sequence.json file!");
        }
        if (flock($fileHandle, LOCK_EX)) {
            $fileSize = filesize($sequencePath);
            $currentId = $fileSize > 0 ? (int) fread($fileHandle, $fileSize) : 0;
            $nextId = $currentId + 1;
            ftruncate($fileHandle, 0);
            rewind($fileHandle);
            fwrite($fileHandle, (string) $nextId);
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return $nextId;
        } else {
            throw new Exception("$this->tag, Table $tableName: sequence.json file is locked!");
        }
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            throw new Exception("$this->tag, Directory $dir: You can't delete a directory that doesn't exist!");
        }

        $files = $this->dirReadAll($dir);
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

    private function getStructure($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get the structure from a table that doesn't exist!");
        }
        $structurePath = $tablePath . '/metadata/structure.json';
        if (!is_file($structurePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get the structure that its json file is not found!");
        }
        if (!is_readable($structurePath)) {
            throw new Exception("$this->tag, Table $tableName: You don't have permission to read the structure from the table!");
        }
        $structure = json_decode(file_get_contents($structurePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("$this->tag, Table $tableName: You can't get the structure because its json file is corrupted!");
        }
        return $structure;
    }

    private function dirReadAll($path)
    {
        $handle = opendir($path);
        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                yield $entry;
            }
        }
    }


    private function readJsonFile($filePath)
    {
        if (!is_file($filePath)) {
            return null;
        }
        if (!is_readable($filePath)) {
            throw new Exception("$this->tag, File $filePath: You don't have permission to read the file!");
        }
        $data = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("$this->tag, File $filePath: You can't get the data because its json file is corrupted!");
        }
        return $data;
    }

}

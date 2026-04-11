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
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0777, true);
        } else {
            throw new Exception("$this->tag, Table $name: You can't create a table that already exists!");
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
            throw new Exception("$this->tag, Table $tableName: You can't insert into a table that doesn't exist!");
        }
        $structure = json_decode(file_get_contents($tablePath . '/structure.json'), true);
        $_data = array_intersect_key($data, $structure);
        if ($_data != $data) {
            $diff = array_diff_key($data, $structure);
            throw new Exception("$this->tag, Table $tableName: You can't insert into a table with columns that don't exist. Columns: [" . implode(", ", $diff) . "] don't exist!");
        }
        $_data['id'] = $this->getNextId($tableName);
        $_data['created_at'] = date('Y-m-d H:i:s');
        $_data['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($tablePath . '/' . $_data['id'] . '.json', json_encode($_data));
        foreach ($structure as $key => $columnDef) {
            if (!in_array($columnDef['type'], $this->non_indexed_types) && $columnDef['is_indexed']) {
                $columnPath = $indexTablePath . '/' . $key . '.json';
                if (!is_file($columnPath)) {
                    file_put_contents($columnPath, json_encode(array('type' => $columnDef['type'], 'index' => array())));
                }
                
                $valueToIndex = $data[$key] ?? null;
                if ($valueToIndex !== null) {
                    $column = json_decode(file_get_contents($columnPath), true);
                    $column['index'][$valueToIndex][] = $data['id'];
                    file_put_contents($columnPath, json_encode($column));
                }
            }
        }
        return $data['id'];
    }

    public function update($tableName, $id, $data)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't update a row that doesn't exist!");
        }

        $oldData = json_decode(file_get_contents($filePath), true);
        $structure = json_decode(file_get_contents($tablePath . '/structure.json'), true);

        // Merge partial data with old data to preserve existing fields
        $data['id'] = (int) $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $mergedData = array_merge($oldData, $data);

        file_put_contents($filePath, json_encode($mergedData));

        // Update indexes only for modified columns present in $data
        foreach ($data as $key => $newValue) {
            if (isset($structure[$key]) && (!isset($oldData[$key]) || $oldData[$key] != $newValue)) {
                if ($structure[$key]['is_indexed'] && !in_array($structure[$key]['type'], $this->non_indexed_types)) {
                    $indexPath = $tablePath . '/index/' . $key . '.json';
                    if (is_file($indexPath)) {
                        $column = json_decode(file_get_contents($indexPath), true);

                        // Remove ID from old index value (if it existed)
                        if (isset($oldData[$key]) && isset($column['index'][$oldData[$key]])) {
                            $oldValue = $oldData[$key];
                            $column['index'][$oldValue] = array_values(array_diff($column['index'][$oldValue], [$id]));
                            if (empty($column['index'][$oldValue])) {
                                unset($column['index'][$oldValue]);
                            }
                        }

                        // Add ID to new index value
                        $column['index'][$newValue][] = (int) $id;
                        file_put_contents($indexPath, json_encode($column));
                    }
                }
            }
        }
        return true;
    }

    public function delete($tableName, $id)
    {
        $tablePath = $this->path . '/' . $tableName;
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't delete a row that doesn't exist!");
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
            foreach ($this->getAll($tableName) as $row) {
                if ($row[$columnName] == $value) {
                    return $row['id'];
                }
            }
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
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't find a row in a table that doesn't exist!");
        }
        $filePath = $tablePath . '/' . $id . '.json';
        if (!is_file($filePath)) {
            return [];
        }
        return json_decode(file_get_contents($filePath), true);
    }

    public function getAll($tableName, $index_column = null, $index_search = null)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get all rows from a table that doesn't exist!");
        }

        if ($index_column !== null && $index_search !== null) {
            $results = [];
            $indexPath = $tablePath . '/index/' . $index_column . '.json';
            if (is_file($indexPath)) {
                $column = json_decode(file_get_contents($indexPath), true);
                if (isset($column['index'][$index_search])) {
                    $ids = $column['index'][$index_search];
                    foreach ($ids as $id) {
                        $data = $this->findById($tableName, $id);
                        if (!empty($data)) {
                            $results[] = $data;
                        }
                    }
                }
            }
            return $results;
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
            throw new Exception("$this->tag, Table $tableName: You can't get the next id from a table that doesn't exist!");
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
            throw new Exception("$this->tag, Directory $dir: You can't delete a directory that doesn't exist!");
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

    private function getStructure($tableName)
    {
        $tablePath = $this->path . '/' . $tableName;
        if (!is_dir($tablePath)) {
            throw new Exception("$this->tag, Table $tableName: You can't get the structure from a table that doesn't exist!");
        }
        $structurePath = $tablePath . '/structure.json';
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
}

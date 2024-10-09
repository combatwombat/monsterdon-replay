<?php

namespace RTF;

use PDO;

class DB {

    public $pdo;

    function __construct($dbOrArray, $user = 'root', $pass = '', $host = 'localhost', $charset = 'utf8mb4') {

        if (is_array($dbOrArray)) {
            $db = $dbOrArray['db'];
            $user = $dbOrArray['user'];
            $pass = $dbOrArray['pass'];
            $host = $dbOrArray['host'];
            $charset = $dbOrArray['charset'];
        } else {
            $db = $dbOrArray;
        }

        $dsn = "mysql:host=".$host.";dbname=".$db.";charset=" . $charset;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Some convenience methods.
     * @param string $name Method name.
     * @param array $args Method arguments.
     * @return array|null
     */
    public function __call($name, $args) {

        // getBy{columnName}. Converts columnName to column_name, calls getBy($table, $column, $value)
        if (strlen($name) > 5 && substr($name, 0, 5) == 'getBy' && count($args) == 2) {
            $columnUnderscore = $this->camelCaseToUnderscores(substr($name, 5));
            return $this->getBy($args[0], $columnUnderscore, $args[1]);
        }

        // getAllBy{columnName}. Converts columnName to column_name, calls getAllBy($table, $column, $value)
        if (strlen($name) > 8 && substr($name, 0, 8) == 'getAllBy' && count($args) == 2) {
            $columnUnderscore = $this->camelCaseToUnderscores(substr($name, 8));
            return $this->getAllBy($args[0], $columnUnderscore, $args[1]);
        }
    }

    /**
     * Get a single row by id.
     * @param string $table The table.
     * @param int $id Value of id column.
     * @return array|null The result.
     */
    public function get($table, $id) {
        $sql = "SELECT * FROM `" . $table . "` WHERE id = :id";
        $ret = $this->fetch($sql, ["id" => $id]);
        return $ret ? $ret : null;
    }

    /**
     * Get a single row by a certain column.
     * @param string $table The table.
     * @param string $column The column.
     * @param mixed $value The value of the column.
     * @return array|null The result.
     */
    public function getBy($table, $column, $value) {
        $sql = "SELECT * FROM `" . $table . "` WHERE `" . $column . "` = :hash";
        $ret = $this->fetch($sql, ["hash" => $value]);
        return $ret ? $ret : null;
    }

    /**
     * Get all rows.
     * @param string $table The table.
     * @return array Array of results.
     */
    public function getAll($table) {
        $sql = "SELECT * FROM `" . $table . "`";
        return $this->fetchAll($sql);
    }

    /**
     * Get all rows by a certain column.
     * @param string $table The table.
     * @param string $column The column.
     * @param mixed $value The value of the column.
     * @return array Array of results.
     */
    public function getAllBy($table, $column, $value) {
        $sql = "SELECT * FROM `" . $table . "` WHERE `" . $column . "` = :value";
        return $this->fetchAll($sql, ["value" => $value]);
    }

    /**
     * Delete a cache entry by its prefix.
     * @param string $prefix The prefix.
     * @return int Number of affected rows.
     */
    public function deleteCacheByPrefix($prefix) {
        return $this->execute("DELETE FROM cache WHERE name LIKE :prefix", ["prefix" => $prefix . '%']);
    }


    /**
     * Select data, return first element.
     * @param string $query Query String with ? placeholders
     * @param array|null $vars Array of values for placeholders
     * @param bool|string $cache Whether to cache the result and load it from cache table if available. If it's a string, it's used as cache key prefix.
     * @param bool $all Whether to return all results with fetchAll
     * @return array The first result.
     */
    public function fetch($query, $vars = null, $cache = false, $all = false) {

        if ($cache) {
            // first 220 characters of query, sanitized, to identify the cache entry at least somewhat in the cache table
            $queryPart = strtolower(substr(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", $query)), 0, 220));
            $cacheKey = $queryPart . "-" . md5($query . json_encode($vars));

            if (is_string($cache)) {
                $cacheKey = $cache . "-" . $cacheKey;
            }

            $res = $this->getByName("cache", $cacheKey);
            if ($res) {
                return unserialize($res['value']);
            }
        }

        $st = $this->pdo->prepare($query);

        if (is_array($vars)) {
            foreach ($vars as $key => $val) {
                if (substr($key, 0, 1) != ':') {
                    $key = ':' . $key;
                }
                if (is_numeric($val)) {
                    $st->bindValue($key, (int)$val, PDO::PARAM_INT);
                } else if (is_bool($val)) {
                    $st->bindValue($key, (bool)$val, PDO::PARAM_BOOL);
                } else if (is_null($val)) {
                    $st->bindValue($key, null, PDO::PARAM_NULL);
                } else {
                    $st->bindValue($key, $val);
                }
            }
        }

        $st->execute();

        if ($all) {
            $ret = $st->fetchAll();
        } else {
            $ret = $st->fetch();
        }

        if ($cache) {
            $this->insert("cache", [
                'name' => $cacheKey,
                'value' => serialize($ret)
            ]);
        }

        return $ret;
    }

    /**
     * Select data, return array.
     * @param string $query Query String with ? placeholders.
     * @param array|null $vars Array of values for placeholders.
     * @param bool $cache Whether to cache the result and load it from cache table if available.
     * @return array Array of results.
     */
    public function fetchAll($query, $vars = null, $cache = false) {
        return $this->fetch($query, $vars, $cache, true);
    }


    /**
     * Execute a query, return affected rows.
     * @param string $query Query String with :named parameters
     * @param array|null $vars Array of values for placeholders
     * @return int Number of affected rows.
     */
    public function execute($query, $vars = null) {
        $st = $this->pdo->prepare($query);

        if (is_array($vars)) {
            foreach ($vars as $key => $val) {
                if (substr($key, 0, 1) != ':') {
                    $key = ':' . $key;
                }
                if (is_numeric($val)) {
                    $st->bindValue($key, (int)$val, PDO::PARAM_INT);
                } else if (is_bool($val)) {
                    $st->bindValue($key, (bool)$val, PDO::PARAM_BOOL);
                } else if (is_null($val)) {
                    $st->bindValue($key, null, PDO::PARAM_NULL);
                } else {
                    $st->bindValue($key, $val);
                }
            }
        }

        $st->execute();
        $res = $st->rowCount();
        return $res;
    }

    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }

    public function commit() {
        $this->pdo->commit();
    }

    public function getJSONData($table, $column, $id) {
        $data = null;
        $row = $this->getById($table, $id);

        if ($row && isset($row[$column])) {
            $column = $row[$column];
            try {
                $data = json_decode($column, true);
            } catch (Exception $e) {

            }
        }

        return $data;
    }

    public function saveJSONData($table, $column, $id, $data) {
        $data = json_encode($data);
        if ($data) {
            try {
                $this->update($table, [
                    $column => $data
                ], [
                    'id' => $id
                ]);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }



    /**
     * Update data.
     * @param string $table The table.
     * @param array $values key/value pairs of values to set.
     * @param array|null $where key/value pairs of WHERE clause, combined with AND.
     * @return int Number of affected rows (might be 0 if nothing was changed)
     */
    public function update($table, $values, $where = null) {
        $sql = "UPDATE `" . $table . "` SET ";

        $sqlValuesStringArr = [];
        $valuesArr = [];
        foreach ($values as $key => $value) {
            $sqlValuesStringArr[] = $key . ' = :' . $key;
            $valuesArr[$key] = $value;
        }
        $sql .= implode(", ", $sqlValuesStringArr) . " ";

        $whereArr = [];
        if ($where) {

            $sqlWhereStringArr = [];
            foreach ($where as $key => $value) {
                $sqlWhereStringArr[] = "`" . $key . "` = :" . $key;
                $whereArr[$key] = $value;
            }
            $sql .= 'WHERE ' . implode(" AND ", $sqlWhereStringArr) . " ";
        }

        $bindArr = array_merge($valuesArr, $whereArr);

        $res = $this->execute($sql, $bindArr);

        return $res;
    }

    /**
     * Insert data.
     * @param string $table The table.
     * @param array $values key/value pairs of values to set.
     * @return int The last inserted id.
     */
    public function insert($table, $values) {
        $sql = "INSERT INTO `" . $table . "` SET ";

        $sqlValuesStringArr = [];
        $valuesArr = [];
        foreach ($values as $key => $value) {
            $sqlValuesStringArr[] = "`" . $key . "` = ?";
            $valuesArr[] = $value;
        }
        $sql .= implode(", ", $sqlValuesStringArr) . " ";

        $st = $this->pdo->prepare($sql);
        $st->execute($valuesArr);
        return $this->pdo->lastInsertId();
    }



    /**
     * Insert multiple data items at once. Much faster
     * @param $table The table
     * @param $valuesArr Array of values [ ['id' => 1, 'name => 'bob'], ['id' => 2, 'name' => 'bobert'], ...]
     * @return bool|void
     */
    public function insertMulti($table, $valuesArr) {

        if (empty($valuesArr)) return;

        $columnNames = [];
        foreach ($valuesArr[0] as $key => $value) {
            $columnNames[] = "`" . $key . "`";;
        }

        $sql = "INSERT INTO `" . $table . "` (" . implode(",", $columnNames) . ") VALUES ";

        $pdoValuesArr = [];
        $valuesPlaceholderStrings = [];
        foreach ($valuesArr as $values) {

            $valuesPlaceHolderArr = [];
            foreach ($values as $key => $value) {
                $pdoValuesArr[] = $value;
                $valuesPlaceHolderArr[] = '?';
            }

            $valuesPlaceholderStrings[] = '(' . implode(",", $valuesPlaceHolderArr) . ')';
        }
        $valuesPlaceholderString = implode(",", $valuesPlaceholderStrings);

        $sql .= $valuesPlaceholderString;

        $st = $this->pdo->prepare($sql);

        return $st->execute($pdoValuesArr);
    }

    /**
     * Delete data.
     * @param string $table The table.
     * @param array $where key/value pairs of WHERE clause, combined with AND.
     * @return int Number of affected rows.
     */
    public function delete($table, $where) {
        $sql = "DELETE FROM `" . $table . "` ";

        $whereArr = [];
        $sqlWhereStringArr = [];
        foreach ($where as $key => $value) {
            $sqlWhereStringArr[] = "`" . $key . "` = :" . $key;
            $whereArr[$key] = $value;
        }
        $sql .= 'WHERE ' . implode(" AND ", $sqlWhereStringArr) . " ";

        return $this->execute($sql, $whereArr);
    }


    /**
     * Convert columnName to column_name
     * @param string $str cameCaseString
     * @return string underscore_string
     */
    public function camelCaseToUnderscores($str) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $str, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }


    public function log($message, $fileName = "default.log") {
        $message = is_string($message) ? $message : print_r($message, true);

        // cli mode?
        if (php_sapi_name() == 'cli') {
            $userIP = "cli";
        } else {
            $userIP = $_SERVER['REMOTE_ADDR'];
        }

        $fileName = "default.log";

        if (strpos($fileName, '..') !== false) {
            return;
        }

        $filePath = __SITE__ . "/logs/" . $fileName;
        $text = "=== " . date('Y-m-d H:i:s') . " (" . $userIP . ")\n" . $message . "\n\n";
        file_put_contents($filePath, $text, FILE_APPEND);

        print_r($text);

        // keep only the last x lines of logs
        $lines = file($filePath);
        if (count($lines) > 10000) {
            $lines = array_slice($lines, -10000);
            file_put_contents($filePath, implode('', $lines));
        }

    }

    /**
     * Validate a single field or an array of fields
     * @param $nameOrFields
     * @param $data
     * @param $rules
     * @return array of errors. Example: ['fieldname1' => ['error 1', 'error 2'], 'fieldname2' => ['error 1']]
     *
     * Usage:
     * // validate a single field
     * $errors = $this->validate('foo', $_POST['foo'], 'required|min:3|max:10');
     *
     * // validate multiple fields
     * $errors = $this->validate(['foo' => ['data' => $_POST['foo'], 'rules' => 'required|min:3|max:10'], 'bar' => ['data' => $_POST['bar'], 'rules' => 'required|datetime|regex:[a-z0-9\-]']]);
     */
    public function validate($nameOrFields, $data = null, $rules = null) {
        $errors = [];

        // if $nameOrFields is an array, loop through it
        if (is_array($nameOrFields)) {
            foreach ($nameOrFields as $name => $field) {
                $res = $this->validate($name, $field['data'], $field['rules']);
                if (!empty($res)) {
                    $errors = array_merge($errors, $res);
                }
            }
        } else {
            $rules = explode('|', $rules);
            $data = trim($data);
            $name = $nameOrFields;
            $errors[$name] = [];

            foreach ($rules as $rule) {
                $rule = explode(':', $rule);
                $ruleName = $rule[0];
                $ruleValue = isset($rule[1]) ? $rule[1] : null;

                if ($ruleName == 'required' && empty($data)) {
                    $errors[$name][] = $name . ' is required';
                }

                if ($ruleName == 'numeric' && !is_numeric($data) && !empty($data)) {
                    $errors[$name][] = $name . ' must be a number';
                }

                if ($ruleName == 'min' && (int) $data < (int) $ruleValue && !empty($data)) {
                    $errors[$name][] = $name . ' must be at least ' . $ruleValue . ' big';
                }

                if ($ruleName == 'max' && (int) $data > (int) $ruleValue && !empty($data)) {
                    $errors[$name][] = $name . ' can\'t be bigger than ' . $ruleValue;
                }

                if ($ruleName == 'datetime' && !strtotime($data) && !empty($data)) {
                    $errors[$name][] = $name . ' must be a valid date and time';
                }

                if ($ruleName == 'date' && !strtotime($data) && !empty($data)) {
                    $errors[$name][] = $name . ' must be a valid date';
                }

                if ($ruleName == 'regex' && !preg_match('/' . $ruleValue . '/', $data) && !empty($data)) {
                    $errors[$name][] = $name . ' must match the pattern ' . $ruleValue;
                }

            }

            if (empty($errors[$name])) {
                unset($errors[$name]);
            }

        }

        return $errors;
    }
}
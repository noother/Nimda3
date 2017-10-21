<?php

require_once 'DatabaseInterface.php';

class SQLite implements DatabaseInterface {

    protected $queryCount = 0;
    protected $sqliteFile;
    protected $sqliteFlags;
    protected $encryptionKey;

    protected static $fetchModes = array('both' => SQLITE3_BOTH, 'assoc' => SQLITE3_ASSOC, 'numeric' => SQLITE3_NUM);

    /** @var  SQLite3 */
    protected $Instance;

    function __construct($config) {
        $this->sqliteFile = $config['file'];
        $this->encryptionKey = array_key_exists('encryptionKey', $config) ? $config['encryptionKey'] : null;
        $this->sqliteFlags = 0;
        if (array_key_exists('readonly', $config)) {
            if ($config['readonly']) {
                $this->sqliteFlags |= SQLITE3_OPEN_READONLY;
            } else {
                $this->sqliteFlags |= SQLITE3_OPEN_READWRITE;
            }
        } else {
            $this->sqliteFlags |= SQLITE3_OPEN_READWRITE;
        }
        if (array_key_exists('create', $config)) {
            if ($config['create']) {
                $this->sqliteFlags |= SQLITE3_OPEN_CREATE;
            }
        } else {
            $this->sqliteFlags |= SQLITE3_OPEN_CREATE;
        }
    }

    function connect() {
        $errorMessage = '';
        try {
            $this->Instance = new SQLite3($this->sqliteFile, $this->sqliteFlags, $this->encryptionKey);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        $this->query('PRAGMA encoding = "UTF-8"');
    }

    public function query($sql, $mode = DatabaseInterface::FETCH_ASSOC) {
        $this->queryCount++;
        if (!$this->Instance) {
            $this->connect();
        }

        if (!array_key_exists($mode, self::$fetchModes)) {
            trigger_error("User error in SQLite.php: Unknown fetch mode $mode Fallback to mode=assoc", E_USER_WARNING);
            $mode = DatabaseInterface::FETCH_ASSOC;
        }

        /** @var SQLite3Result $result */
        $result = $this->Instance->query($sql);

        if ($result === false) {

            trigger_error("SQLite error: " . $this->Instance->lastErrorMsg(), E_USER_WARNING);
            return false;
        }
        $operation = $this->extractOperation($sql);
        switch ($operation) {
            case 'SELECT':
                $return = [];
                do {
                    $row = $result->fetchArray(self::$fetchModes[$mode]);
                    $return[] = $row;
                } while ($row !== false);
                array_pop($return); // remove that "false" from the end
                break;
            case 'INSERT':
                $return = $this->Instance->lastInsertRowid();
                break;
            case 'UPDATE':
                $return = $this->Instance->changes();
                break;
            default:
                $return = true;
                break;
        }

        return $return;
    }

    public function multiQuery($sql) {
        $queries = $this->splitQuery($sql);
        foreach ($queries as $query) {
            $single_result = $this->query($query);
            if ($single_result === false)
                return false;
        }
        return true;
    }

    public function fetchColumn($sql) {
        /** @var SQLite3Result $res */
        $res = $this->Instance->query($sql);
        if ($res === false) return false;
        return $res->fetchArray(SQLITE3_NUM)[0];
    }

    public function fetchRow($sql, $mode = DatabaseInterface::FETCH_ASSOC) {
        /** @var $res SQLiteResult $r */
        $res = $this->Instance->query($sql);
        if ($res === false) return false;
        if (!array_key_exists($mode, self::$fetchModes)) {
            trigger_error("User error in SQLite.php: Unknown fetch mode $mode Fallback to mode=assoc", E_USER_WARNING);
            $mode = DatabaseInterface::FETCH_ASSOC;
        }
        return $res->fetch($mode);
    }

    private function splitQuery($sql) {
        // let's just hope nobody submits an sql_updates file with a semicolon in a string
        // if that happens, we face a lot of failed queries and need more code in here.
        return explode(';', $sql);
    }

    public function showTablesLike($name) {
        return $this->query("SELECT * FROM sqlite_master WHERE type='table' AND name LIKE \"$name\"");
    }

    public function getPermanent($name, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("SELECT `value` FROM `memory` WHERE `type` = :type AND `target` = :target AND `name` = :name;");
        $statement->bindValue(':type', $type);
        $statement->bindValue(':target', $target);
        $statement->bindValue(':name', $name);
        $result = $statement->execute();
        if ($result === false)
            return false;
        return $result->fetchArray(SQLITE3_NUM)[0];
    }

    public function insertPermanent($name, $value, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("INSERT INTO `memory` (`name`, `type`, `target`, `value`, `created`, `modified`)
				VALUES (:name,:type,:target,:value,CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':type', $type);
        $statement->bindValue(':target', $target);
        $statement->bindValue(':value', $value);
        $result = $statement->execute();
        return $result !== false;
    }

    public function updatePermanent($name, $value, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("UPDATE `memory` SET `value` = :value, `modified` = CURRENT_TIMESTAMP WHERE `name`=:name AND `type`=:type AND `target`=:target;");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':type', $type);
        $statement->bindValue(':target', $target);
        $statement->bindValue(':value', $value);
        $result = $statement->execute();
        return $result !== false;
    }

    public function removePermanent($name, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("DELETE FROM `memory` WHERE`type` = :type AND `target` = :target AND `name` = :name");
        $statement->bindValue(':type', $type);
        $statement->bindValue(':target', $target);
        $statement->bindValue(':name', $name);
        $result = $statement->execute();
        return $result !== false;
    }

    public function closeConnection() {
        $this->Instance->close();
    }

    /**
     * Extracts the operation (SELECT, UPDATE, etc) from the SQL statement
     * @param $sql string
     * @return mixed
     */
    protected function extractOperation($sql) {
        preg_match('/^\s*(\w+)/', strtoupper($sql), $arr);
        return $arr[1];
    }
}

?>

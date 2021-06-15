<?php

/**
 * Interface DatabaseInterface
 * Provides functions to access databases
 */
interface DatabaseInterface {

    const FETCH_BOTH = 'both';
    const FETCH_ASSOC = 'assoc';
    const FETCH_NUMERIC = 'numeric';

    /**
     * Database constructor.
     * @param $config array
     */
    public function __construct($config);

    /**
     * Establishes the connection. Dies on error
     * @return void
     */
    function connect();

    /**
     * Runs a single SQL query
     * @see DatabaseInterface::multiQuery() for queries with more than one statement
     * @param string $sql
     * @param string $mode
     * @return mixed returns the result set or a boolean value indicating success
     */
    public function query($sql, $mode = DatabaseInterface::FETCH_ASSOC);

    /**
     * Runs a set of SQL queries seperated by a ;
     * @param $sql string
     * @return boolean true - everything went well; false - a single thing went wrong.
     * The statements after the error will not be executed.
     */
    public function multiQuery($sql);

    /**
     * Returns the value of a single column. The query must select exactly one column and not more than one row.
     * @param $sql string
     * @return mixed | false returns the value of the column or false on error
     */
    public function fetchColumn($sql);

    /**
     * Selects a single row with the given statement. If more than one row matches the $sql statement, only the first is returned.
     * @param $sql string
     * @param string $mode one of the Database::FETCH_* constants
     * @return mixed | false the row on success; false on failure
     */
    public function fetchRow($sql, $mode = DatabaseInterface::FETCH_ASSOC);

    /**
     * A wrapper for SHOW TABLES LIKE x as SQLite does not support the SHOW operation
     * @param $name string table name
     * @return mixed false on failure, other stuff on success (depends on database type, sorry for that)
     */
    public function showTablesLike($name);

    /**
     * Gets a permanent variable
     * @param string $name
     * @param string $type
     * @param string $target
     * @return mixed the value of the variable or false on failure
     */
    public function getPermanent($name, $type = 'bot', $target = 'me');

    /**
     * Inserts a new permanent variable
     * @param string $name
     * @param mixed $value
     * @param string $type
     * @param string $target
     * @return boolean true on success, false on failure
     */
    public function insertPermanent($name, $value, $type = 'bot', $target = 'me');

    /**
     * Updates a permanent variable
     * @param string $name
     * @param string $value
     * @param string $type
     * @param string $target
     * @return boolean true on success, false on failure
     */
    public function updatePermanent($name, $value, $type = 'bot', $target = 'me');

    /**
     * Removes a permanent variable
     * @param string $name
     * @param string $type
     * @param string $target
     * @return boolean true on success, false on failure
     */
    public function removePermanent($name, $type = 'bot', $target = 'me');

    /**
     * Closes the connection
     * @return void
     */
    public function closeConnection();
}
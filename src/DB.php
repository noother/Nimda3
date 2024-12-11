<?php

namespace Nimda;

use noother\Database\MySQL;

class DB {
	public static function getDB(): MySQL {
		return Nimda::getInstance()->getDB();
	}
	public static function query(string $sql, string $mode='assoc') {
		return static::getDB()->query($sql, $mode);
	}

	public static function fetchColumn(string $sql) {
		return static::getDB()->fetchColumn($sql);
	}

	public static function fetchRow(string $sql, string $mode='assoc') {
		return static::getDB()->fetchRow($sql, $mode);
	}

	/**
	 * @return string|array|null string if $columns is a string (and not *), array if $column is an array, null if there is no result
	 */
	public static function first(string|array $columns, string $table, int|array $conditions=[]): string|array|null {
		return static::getDB()->first($columns, $table, $conditions);
	}

	public static function select(string|array $columns, string $table, int|array $conditions=[], int $limit=null): array {
		return static::getDB()->select($columns, $table, $conditions, $limit);
	}

	/**
	 * @return int ID of record inserted
	 */
	public static function insert(string $table, array $data): int {
		return static::getDB()->insert($table, $data);
	}

	/**
	 * @return int Number of affected rows
	 */
	public static function update(string $table, array $data, int|array $conditions=[]): int {
		return static::getDB()->update($table, $data, $conditions);
	}

	/**
	 * @return int Last insert id on INSERT or id on UPDATE
	 *
	 * If ['id' => ..] is given in $data, it will be an update, otherwise an insert
	 */
	public static function save(string $table, array $data): int {
		return static::getDB()->save($table, $data);
	}

	/**
	 * @return int Number of affected rows
	 */
	public static function delete(string $table, int|array $conditions=[]): int {
		return static::getDB()->delete($table, $conditions);
	}

	public static function multiQuery(array $sqls): void {
		static::getDB()->multiQuery($sql);
	}
}

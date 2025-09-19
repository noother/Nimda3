<?php

namespace Nimda;

class Memory {
	private static $memory = [];

	public static function read(string $name, string $type='bot', string $target='me'): mixed {
		if(array_key_exists($name, self::$memory[$type][$target]??[])) {
			return self::$memory[$type][$target][$name];
		}

		$value = DB::first('value', 'memory', ['type' => $type, 'target' => $target, 'name' => $name]);
		if(!isset($value)) return self::$memory[$type][$target][$name] = null;

		return self::$memory[$type][$target][$name] = unserialize($value);
	}

	public static function write(string $name, mixed $value, string $type='bot', string $target='me'): void {
		if($value === null) {
			self::delete($name, $type, $target);
			return;
		}

		if((self::$memory[$type][$target][$name]??null) === $value) return;

		$sql_value = serialize($value);

		try {
			if(null !== self::read($name, $type, $target)) {
				DB::update('memory', ['value' => $sql_value], ['type' => $type, 'target' => $target, 'name' => $name]);
			} else {
				DB::insert('memory', ['name' => $name, 'type' => $type, 'target' => $target, 'value' => $sql_value]);
			}
		} catch(\Exception $e) {
			// TODO: memory should use json, not serialize(), which won't have this problem
			trigger_error("write() failed because utf8mb4 probably", E_USER_WARNING);
		}

		self::$memory[$type][$target][$name] = $value;
	}

	public static function delete($name, $type='bot', $target='me'): bool {
		if(array_key_exists($name, self::$memory[$type][$target]??[])) {
			unset(self::$memory[$type][$target][$name]);
		}

		return DB::delete('memory', ['type' => $type, 'target' => $target, 'name' => $name]);
	}
}

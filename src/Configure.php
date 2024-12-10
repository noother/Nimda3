<?php

namespace Nimda;

class Configure {
	private static $config;

	private static function init(): void {
		static::$config = json_decode(file_get_contents('config/config.json'), true);
	}

	public static function read(string $name=null, mixed $default=null): mixed {
		if(!isset(static::$config)) static::init();

		if(!isset($name)) return static::$config;

		$item = static::getConfigFromFlattened($name);

		return $item ?? $default;
	}

	private static function getConfigFromFlattened(string $name): mixed {
		$current = static::$config;

		$parts = explode('.', $name);
		foreach($parts as $part) {
			$current = $current[$part] ?? null;
			if(!isset($current)) return null;
		}

		return $current;
	}
}

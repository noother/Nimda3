<?php

namespace noother\Cache;

class FilesystemCache extends Cache {
	private $cacheDirectory;

	public function __construct(string $prefix=null, string $cache_dir=null) {
		parent::__construct($prefix);

		if(!isset($cache_dir)) $cache_dir = "{$_SERVER['HOME']}/.noothcache/";
		if(substr($cache_dir, -1) != '/') $cache_dir.= '/';
		if(!file_exists($cache_dir)) mkdir($cache_dir);
		$this->cacheDirectory = $cache_dir;
	}

	protected function getValue(string $key, int $max_age=null): ?array {
		$cache_file = $this->cacheDirectory.$this->getFilename($key);
		if(!file_exists($cache_file)) return null;

		$created = filemtime($cache_file);

		if(isset($max_age) && $created < time() - $max_age) return null;

		return ['created' => $created, 'value' => file_get_contents($cache_file)];
	}

	protected function putValue(string $key, $value): bool {
		$cache_file = $this->cacheDirectory.$this->getFilename($key);

		return file_put_contents($cache_file, $value);
	}

	protected function clearValue(string $key): bool {
		$cache_file = $this->cacheDirectory.$this->getFilename($key);

		if(file_exists($cache_file)) return unlink($cache_file);

	return false;
	}

	private function getFilename(string $key): string {
		$filename = substr($key, 0, 32);
		$filename.= '_'.md5($key);

	return $filename;
	}
}

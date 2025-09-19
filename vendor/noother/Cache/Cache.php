<?php

namespace noother\Cache;

abstract class Cache {
	private $prefix = '';
	private $cache = [];

	private $debug = false;
	private $logging = false;
	private $log = [];

	public function __construct(string $prefix=null) {
		if(isset($prefix)) $this->prefix = $prefix.'_';
	}

	protected function init() {}
	abstract protected function getValue(string $key, int $max_age): ?array;
	abstract protected function putValue(string $key, $value): bool;
	abstract protected function clearValue(string $key): bool;

	public function enableLogging(): void {
		$this->logging = true;
	}

	public function enableDebug(): void {
		$this->debug = true;
		$this->logging = true;
	}

	public function get(string $key, int $max_age=null) {
		if(isset($this->cache[$key])) {
			if(isset($max_age) && $this->cache[$key]['created'] < time() - $max_age) {
				unset($this->cache[$key]);
				return null;
			}

			return $this->cache[$key]['value'];
		}

		if($this->logging) $s = microtime(true);
		$res = $this->getValue($this->prefix.$key, $max_age);
		if($this->logging) $this->log('GET', $key, microtime(true)-$s, isset($res));

		if(!isset($res)) return null;

		$value = unserialize($res['value']);

		$this->cache[$key] = ['created' => $res['created'], 'value' => $value];

		return $value;
	}

	public function put(string $key, $value): bool {
		$this->cache[$key] = ['created' => time(), 'value' => $value];

		if($this->logging) $s = microtime(true);
		$status = $this->putValue($this->prefix.$key, serialize($value));
		if($this->logging) $this->log('PUT', $key, microtime(true)-$s, true);

		return $status;
	}

	public function clear(string $key): bool {
		unset($this->cache[$key]);

		if($this->logging) $s = microtime(true);
		$status = $this->clearValue($this->prefix.$key);
		if($this->logging) $this->log('CLEAR', $key, microtime(true)-$s, $status);

		return $status;
	}

	private function log(string $action, string $key, float $time, bool $status): void {
		$this->log[] = array($action, $key, $time, $status);
		if($this->debug) {
			echo "$action $key ".($status?'OK':'ERR')." ".number_format($time*1000, 2)."ms\n";
		}
	}

	public function printLog(): void {
		if(!$this->logging) echo "Sorry, logging is disabled.\n";

		if(php_sapi_name() == 'cli') $this->printTextLog();
		else $this->printHTMLLog();
	}

	private function printTextLog(): void {
		echo "Num\tQuery\tTime\n";
		$c = 0;
		$sum = 0;
		foreach($this->log as $item) {
			echo ++$c."\t".$item[0]."\t".$item[1].' '.($item[3]?'OK':'ERR')."\t".number_format($item[2]*1000, 2)."ms\n";
			$sum+= $item[2];
		}
		echo 'Total: '.number_format($sum*1000, 2)."ms\n";
	}

	private function printHTMLLog(): void {
		echo '<table cellspacing="5" width="990" style="border:1px solid gray;">';
		echo '<tr><th colspan="3" align="center">'.get_class($this).'</th></tr>';
		echo '<tr><th>Num</th><th width="800">Query</th><th>Time</th></tr>';

		$c = 0;
		$sum = 0;
		foreach($this->log as $item) {
			$sum+=$item[2];
			echo '<tr>';
			echo '<td align="center">'.++$c.'</td>';
			echo '<td>'.htmlspecialchars($item[0].' '.$item[1]).' '.($item[3]?'OK':'ERR').'</td>';
			echo '<td align="right">'.number_format($item[2]*1000, 2).' ms</td>';
			echo '</tr>';
		}
		echo '<tr><td colspan="3">Total query time: <strong>'.number_format($sum*1000, 2).' ms</strong></td></tr>';
		echo '</table>';
		echo '<br />';
	}
}

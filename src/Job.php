<?php

namespace Nimda;

use noother\Database\MySQL;

class Job {
	private $datafile;
	private $data;
	private $Plugin;

	public function __construct($datafile) {
		$this->datafile = $datafile;
		$this->loadData();
		$this->loadPlugin();
	}

	public function run() {
		$result = $this->processData();
		$this->writeJobDone($result);
		$this->removeDatafile();
	}

	private function loadData() {
		$this->data = json_decode(file_get_contents($this->datafile), true);
	}

	private function loadPlugin() {
		require_once(str_replace(['\\', 'Nimda/'], ['/', 'src/'], $this->data['classname']).'.php');

		$c = json_decode(file_get_contents('config/config.json'), true);
		$MySQL = new MySQL($c['mysql']['host'], $c['mysql']['user'], $c['mysql']['pass'], $c['mysql']['db'], $c['mysql']['port']);
		$this->Plugin = new $this->data['classname'](null, $MySQL);
		$this->Plugin->Bot = new \stdclass();
		$this->Plugin->Bot->CONFIG = $c;
	}

	private function processData() {
		$callback = $this->data['callback'];
		if(is_null($this->data['data'])) {
			$result = $this->Plugin->$callback();
		} else {
			$result = $this->Plugin->$callback($this->data['data']);
		}
		
	return $result;
	}
	
	private function writeJobDone($result) {
		$data = array('callback' => $this->data['callback'], 'origin' => $this->data['origin'], 'result' => $result);
		file_put_contents($this->data['job_done_filename'], json_encode($data));
	}
	
	private function removeDatafile() {
		unlink($this->datafile);
	}
	
}

?>

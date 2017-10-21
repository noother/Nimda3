<?php

require_once('core/defaults.php');
require_once('core/Plugin.php');



class Job {
	
	private $datafile;
	private $data;
	private $Plugin;
	
	public function __construct($datafile) {
		$this->datafile = $datafile;
		$this->loadData();
		$this->loadPlugin();
		$result = $this->processData();
		$this->writeJobDone($result);
		$this->removeDatafile();
	}
	
	private function loadData() {
		$this->data = unserialize(file_get_contents($this->datafile));
	}
	
	private function loadPlugin() {
		require_once($this->data['plugin_path']);

        $config = libFile::parseConfigFile('nimda.conf');
        $database = null;
        switch ($config['database']) {
            case 'mysql':
                $database = new MySQL([
                    'host' => $config['mysql_host'],
                    'user' => $config['mysql_user'],
                    'pass' => $config['mysql_pass'],
                    'db' => $config['mysql_db']
                ]);
                break;
            case 'sqlite':
                $database = new SQLite([
                    'file' => $config['sqlite_file'],
                    'readonly' => $config['sqlite_readonly'],
                    'create' => $config['sqlite_create'],
                    'encryptionKey' => $config['sqlite_encryptionKey'],
                ]);
                break;
            case 'mongodb':
                die('You changed the code to come this far, didn\'t you?');
                break;
            default:
                trigger_error("Unknown database backend type {$config['DatabaseInterface']}");
                return false;
        }
        $this->Plugin = new $this->data['classname'](null, $database);
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
		file_put_contents($this->data['job_done_filename'], serialize($data));
	}
	
	private function removeDatafile() {
		unlink($this->datafile);
	}
	
}

new Job($argv[1]);

?>

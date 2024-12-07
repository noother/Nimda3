<?php

namespace Nimda\Plugin\User\Omegle;

class OmegleListener extends Omegle {
	
	function __construct($chatId) {
		parent::__construct();
		
		$this->chatId = $chatId;
		
		while(true) {
			$res = $this->read();
			if($res === false) break;
			
			foreach($res as $item) {
				switch($item[0]) {
					case 'waiting':
						echo "waiting\n";
					break;
					case 'connected':
						echo "connected\n";
					break;
					case 'gotMessage':
						$message = $item[1];
						$safe_message = "";
						for($i=0;$i<strlen($message);$i++) {
							if(ord($message[$i]) > 31 && ord($message[$i]) < 127) $safe_message.= $message[$i];
						}
						echo "message\n".$safe_message."\n";
					break;
					case 'typing':
						echo "typing\n";
					break;
					case 'stoppedTyping':
						echo "stoppedTyping\n";
					break;
					case 'strangerDisconnected':
						echo "disconnected\n";
					break 3;
					case 'error':
						echo "error\n".$item[1]."\n";
					break;
					default:
						echo "unrecognized ".serialize($item);
					break;
				}
			}
		}
	}
	
}

new OmegleListener($argv[1]);

?>

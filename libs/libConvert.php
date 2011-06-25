<?php

class libConvert {
	
	static function mm2inch($mm,$decimals=2) {
		return number_format($mm*0.03937008,$decimals);
	}
	
}

?>

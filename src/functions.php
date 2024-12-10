<?php

function mb_strrev($text) {
	$length = mb_strlen($text);

	$output = '';
	for($i=$length-1;$i>=0;$i--) {
		$output.= mb_substr($text, $i, 1);
	}

	return $output;
}

function br2nl(string $text): string {
	return preg_replace('#<\s*?br\s*?/?\s*?>#', "\n", $text);
}

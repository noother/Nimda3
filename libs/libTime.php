<?php

class libTime {
	
	
	static function secondsToString($seconds) {
		$minutes = (int)($seconds/60);
		$seconds = $seconds-$minutes*60;
		$hours   = (int)($minutes/60);
		$minutes = $minutes-$hours*60;
		$days    = (int)($hours/24);
		$hours   = $hours-$days*24;
		
		if($seconds==1)  $output = "and ".$seconds." second";
		else             $output = "and ".$seconds." seconds";
		if($minutes==1)  $output = $minutes." minute ".$output;
		elseif($minutes) $output = $minutes." minutes ".$output;
		if($hours==1)    $output = $hours.  " hour, ".$output;
		elseif($hours)   $output = $hours.  " hours, ".$output;
		if($days==1)     $output = $days.   " day, ".$output;
		elseif($days)    $output = $days.   " days, ".$output;
		
		if(substr($output,0,4)=="and ") $output = substr($output,4);
	return $output;
	}
	
	static function secondsToStringGer($seconds) {
		$minutes = (int)($seconds/60);
		$seconds = $seconds-$minutes*60;
		$hours   = (int)($minutes/60);
		$minutes = $minutes-$hours*60;
		$days    = (int)($hours/24);
		$hours   = $hours-$days*24;
		
		$output = "und ".$seconds." Sek";
		$output = $minutes." Min ".$output;
		$output = $hours.  " Std, ".$output;
		
		if($days==1)     $output = $days.   " Tag, ".$output;
		elseif($days)    $output = $days.   " Tage, ".$output;
		
		if(substr($output,0,4)=="and ") $output = substr($output,4);
	return $output;
	}	
	
	static function daysLeft($date) {
		list($date, $time) = explode(' ',$date);
		$now = date("Y-m-d",time());
		
		if($now == $date) return 0;
		
		$now  = strtotime($now.' 00:00:00');
		$date = strtotime($date.' '.$time);
		
		$diff = $date-$now;
		$days = floor($diff/60/60/24);
		
	return $days;
	}
}


?>

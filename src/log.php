<?php

log::resetTimer(microtime(true));

abstract class log {
	static private $bToFirePhp = false;
	
	static private $startpoint;
	static private $checkpoint;
	public static function resetTimer($start_timestamp) {
		if ($start_timestamp) {
			log::$startpoint = $start_timestamp;
			log::$checkpoint = log::$startpoint;
		}
	}
	public static function enableFirePhp($value = true, $start_timestamp = null) {
		self::resetTimer($start_timestamp);		
		if ($value && !class_exists('FB',false) && isset($_SERVER['HTTP_USER_AGENT']) && @preg_match('/\sFirePHP\/([\.|\d]*)\s?/si',$_SERVER['HTTP_USER_AGENT'])) {						 		
			include 'FB.php';
		} else $value = false;
		self::$bToFirePhp = $value;
	}
	public static function timeInfo($label=null, $threshold = 0) {
		$elapsed2 = round(microtime(true) - self::$checkpoint, 3);
		self::$checkpoint = microtime(true);
		$elapsed = round(microtime(true) - self::$startpoint, 3);
		$label = "$label ($elapsed2)";
		if (self::$bToFirePhp) {
			if ($elapsed2>$threshold)
				FB::warn($label, $elapsed);
			else
				FB::info($label, $elapsed);
		}		
	}
	public static function info($object, $label=null) {
		if (self::$bToFirePhp) FB::info($object, $label);
	}
	public static function warn($object, $label=null) {
		if (self::$bToFirePhp) FB::warn($object, $label);
	}
	public static function error($object, $label=null) {
		if (self::$bToFirePhp) FB::error($object, $label);
	}
	public static function trace($label=null) {
		if (self::$bToFirePhp) FB::trace($label);
	}

	public static function group($label) {
		if (self::$bToFirePhp) FB::group($label);
	}
	public static function groupEnd() {
		if (self::$bToFirePhp) FB::groupEnd();
	}
}


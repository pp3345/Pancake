<?php

	/****************************************************************/
	/* Moody                                                        */
	/* configuration.class.php                                      */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	class Configuration {
		private static $configuration = array();
		
		public static function load($string) {
			
		}
		
		public static function set($path, $value) {
			return self::$configuration[$path] = $value;
		}
		
		public static function get($path, $defaultValue = null) {
			if(isset(self::$configuration[$path]))
				return self::$configuration[$path];
			return $defaultValue;
		}
	}
?>
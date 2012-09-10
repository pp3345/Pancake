<?php

	/****************************************************************/
	/* Moody                                                        */
	/* tokenVM.class.php                                            */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/

	namespace Moody;

	class ConstantContainer {
		private static $constants = array();
		
		public static function initialize() {
			foreach(get_defined_constants() as $constantName => $constantValue)
				if(!self::isDefined($constantName))
					self::define($constantName, $constantValue);
		}
		
		public static function getConstant($name) {
			$name = strtolower($name);
			if(isset(self::$constants[$name]))
				return self::$constants[$name];
		}
		
		public static function isDefined($name) {
			return isset(self::$constants[strtolower($name)]);
		}
		
		public static function define($name, $value) {
			self::$constants[strtolower($name)] = $value;
		}
		
		public static function undefine($name) {
			$name = strtolower($name);
			if(isset(self::$constants[$name]))
				unset(self::$constants[$name]);
		}
	}
?>
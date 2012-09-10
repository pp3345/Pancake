<?php

	/****************************************************************/
	/* Moody                                                        */
	/* moodyException.class.php                                     */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/

	namespace Moody;
	
	class MoodyException extends \Exception {
		public function __toString() {
			$string = 'Moody encountered an unexpected error and can not continue.' . "\r\n";
			$string .= 'Exception message: ' . $this->message . "\r\n";
			$string .= 'System backtrace: ' . "\r\n" . $this->getTraceAsString();
			
			return $string;
		}
	}
?>
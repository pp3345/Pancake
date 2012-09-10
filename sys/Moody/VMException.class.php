<?php

	/****************************************************************/
	/* Moody                                                        */
	/* VMException.class.php                                        */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;

	/**
	 * Exceptions thrown by the Moody Virtual Machine
	 */
	class VMException extends \Exception {
		private $token;
		private $originalToken;
		
		public function __construct($message, Token $token = null, Token $originalToken = null) {
			$this->message = $message;
			$this->token = $token;
			$this->originalToken = $originalToken;
		}
		
		public function __toString() {
			$string = 'The Moody Virtual Machine encountered an unexpected error and can not continue' . "\r\n"; // I am using Windows line breaks because there might be some poor developers that don't have the ability to develop on a Linux machine
			$string .= 'Exception message: ' . $this->message . "\r\n";
			$string .= 'System backtrace:' . "\r\n";
			$string .= $this->getTraceAsString();
			if($this->token instanceof Token) {
				$string .= "\r\n";
				$string .= 'Current token:' . "\r\n";
				$string .= (string) $this->token;
			}
			
			if($this->originalToken instanceof Token) {
				$string .= "\r\n";
				$string .= 'Current token before modification through token handlers:' . "\r\n";
				$string .= (string) $this->originalToken;
			}
			
			return $string;
		}
	}
?>
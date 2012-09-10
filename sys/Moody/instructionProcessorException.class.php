<?php

	/****************************************************************/
	/* Moody                                                        */
	/* configuration.class.php                                      */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	class InstructionProcessorException extends \Exception {
		private $token;
		
		public function __construct($message, Token $token) {
			$this->message = $message;
			$this->token = $token;
		}
		
		public function __toString() {
			$string = 'The Moody Instruction Processor encountered an unexpected error and can not continue' . "\r\n";
			$string .= 'Exception message: ' . $this->message . "\r\n";
			$string .= 'System backtrace:' . "\r\n";
			$string .= $this->getTraceAsString() . "\r\n";
			$string .= 'Current token:' . "\r\n";
			$string .= (string) $this->token;
			
			return $string;
		}
	}
?>
<?php
	
	/****************************************************************/
	/* Moody                                                        */
	/* instructionHandler.interface.php                             */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	interface InstructionHandler {
		public static function getInstance();
		public function execute(Token $token, $instructionName, TokenHandlers\InstructionProcessor $processor, TokenVM $vm);
	}
	
	interface InstructionHandlerWithRegister extends InstructionHandler {
		public function register(Token $token, $instructionName, TokenHandlers\InstructionProcessor $processor, TokenVM $vm);
	}
?>
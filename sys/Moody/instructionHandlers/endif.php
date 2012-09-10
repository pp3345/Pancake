<?php

	/****************************************************************/
	/* Moody                                                        */
	/* endif.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\InstructionHandlerWithRegister;
	use Moody\Token;
	use Moody\TokenVM;
	use Moody\IfInstruction;

	class EndIfHandler implements InstructionHandlerWithRegister {
		private static $instance;
		
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('endif', $this);
		}
		
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			return TokenVM::DELETE_TOKEN;
		}

		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
		
		public function register(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			IfInstruction::setEndToken($token);
		}
	}
?>
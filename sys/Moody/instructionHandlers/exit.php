<?php

	/****************************************************************/
	/* Moody                                                        */
	/* exit.php                                   					*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class ExitHandler implements InstructionHandler {
		private static $instance = null;
		
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('exit', $this);
			InstructionProcessor::getInstance()->registerHandler('halt', $this);
			InstructionProcessor::getInstance()->registerHandler('quit', $this);
		}
		
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}

		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			return TokenVM::QUIT | TokenVM::DELETE_TOKEN;
		}
	}
?>
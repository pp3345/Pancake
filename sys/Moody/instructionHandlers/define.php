<?php

	/****************************************************************/
	/* Moody                                                        */
	/* define.php                 					                 */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\ConstantContainer;
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class DefineHandler implements InstructionHandler {
		private static $instance = null;
		
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('define', $this);
			InstructionProcessor::getInstance()->registerHandler('def', $this);
			InstructionProcessor::getInstance()->registerHandler('d', $this);
		}
		
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}

		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 'sx');
			ConstantContainer::define($args[0], $args[1]);
			
			return TokenVM::DELETE_TOKEN;
		}
	}
?>
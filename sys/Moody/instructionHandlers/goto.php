<?php

	/****************************************************************/
	/* Moody                                                        */
	/* goto.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionProcessorException;
	use Moody\IfInstruction;
	use Moody\InstructionHandler;
	use Moody\ConstantContainer;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class GotoHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('goto', $this);
			InstructionProcessor::getInstance()->registerHandler('jump', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 's');
			
			$jumpToken = LabelHandler::getLabel($args[0]);
			
			if(!($jumpToken instanceof Token))
				throw new InstructionProcessorException('Jump to undefined label ' . $args[0], $token);
			
			$vm->jump($jumpToken);
			
			return TokenVM::JUMP | TokenVM::DELETE_TOKEN;
		}
	}
?>
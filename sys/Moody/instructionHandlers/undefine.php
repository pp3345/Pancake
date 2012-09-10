<?php

	/****************************************************************/
	/* Moody                                                        */
	/* undefine.php                 					            */
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
	
	class UndefineHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('undef', $this);
			InstructionProcessor::getInstance()->registerHandler('undefine', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 's');
	
			if(!ConstantContainer::isDefined($args[0]))
				throw new InstructionProcessorException($instructionName . ': Undefined constant: ' . $args[0], $token);
				
			ConstantContainer::undefine($args[0]);
				
			return TokenVM::DELETE_TOKEN;
		}
	}
?>
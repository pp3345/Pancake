<?php

	/****************************************************************/
	/* Moody                                                        */
	/* isdefined.php                 					            */
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
	
	class IsDefinedHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('isdefined', $this);
			InstructionProcessor::getInstance()->registerHandler('isdef', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm = null) {
			$args = $processor->parseArguments($token, $instructionName, 's');
				
			if(ConstantContainer::isDefined($args[0]))
				$token->content = Token::makeEvaluatable(true);
			else
				$token->content = Token::makeEvaluatable(false);
			
			return 0;
		}
		
		public function inlineExecute(Token $token, $instructionName, InstructionProcessor $processor) {
			$this->execute($token, $instructionName, $processor);
		}
	}
?>
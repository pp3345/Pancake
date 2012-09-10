<?php

	/****************************************************************/
	/* Moody                                                        */
	/* constant.php                 					            */
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
	
	class GetConstantHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('const', $this);
			InstructionProcessor::getInstance()->registerHandler('constant', $this);
			InstructionProcessor::getInstance()->registerHandler('getconstant', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm = null) {
			$args = $processor->parseArguments($token, $instructionName, 's');
				
			if(!ConstantContainer::isDefined($args[0]))
				throw new InstructionProcessorException($instructionName . ': Undefined constant: ' . $args[0], $token);
			
			$constValue = ConstantContainer::getConstant($args[0]);
			
			$token->content = Token::makeEvaluatable($constValue);
			
			return 0;
		}
		
		public function inlineExecute(Token $token, $instructionName, InstructionProcessor $processor) {
			$this->execute($token, $instructionName, $processor);
		}
	}
?>
<?php

	/****************************************************************/
	/* Moody                                                        */
	/* else.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\END_TOKEN_NO_EXECUTE;

	use Moody\InstructionProcessorException;
	use Moody\IfInstruction;
	use Moody\InstructionHandlerWithRegister;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class ElseHandler implements InstructionHandlerWithRegister {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('else', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			// Find end token
			foreach(IfInstruction::getAll() as $instruction) {
				if($instruction->getToken() == $token) {
					if(!($instruction->getEndToken() instanceof Token))
						throw new InstructionProcessorException('Invalid end token for ' . $instructionName . ' - Probably you forgot an endif?', $token);
						
					$endInstruction = $instruction;
				}
			}
			
			// Find start token
			foreach(IfInstruction::getAll() as $instruction) {
				if($instruction->getEndToken() == $token) {
					if($instruction->getEndTokenAction() == \Moody\END_TOKEN_NO_EXECUTE) {
						$endInstruction->setEndTokenAction(\Moody\END_TOKEN_NO_EXECUTE);
						$vm->jump($endInstruction->getEndToken());
						return TokenVM::DELETE_TOKEN | TokenVM::JUMP;
					}
				}
			}
			
			return TokenVM::DELETE_TOKEN;
		}
	
		public function register(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			IfInstruction::setEndToken($token);
			new IfInstruction($token);
		}
	}
?>
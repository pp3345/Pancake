<?php

	/****************************************************************/
	/* Moody                                                        */
	/* ifInstruction.class.php                 					    */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	const END_TOKEN_NO_EXECUTE = 1;
	const END_TOKEN_EXECUTE = 2;
	
	class IfInstruction {
		private static $instructionStack = array();
		private static $nestedStack = array();
		private $token;
		private $endToken;
		private $endTokenAction = END_TOKEN_EXECUTE;
		
		public function __construct(Token $token) {
			self::$instructionStack[] = $this;
			self::$nestedStack[] = $this;
			$this->token = $token;
		}
		
		public static function setEndToken(Token $token) {
			if(!self::$nestedStack)
				throw new InstructionProcessorException('Endif or Else while no if is active', $token);
			
			end(self::$nestedStack);
			
			self::$nestedStack[key(self::$nestedStack)]->endToken = $token;
			unset(self::$nestedStack[key(self::$nestedStack)]);
		}
		
		public function getToken() {
			return $this->token;
		}
		
		public function getEndToken() {
			return $this->endToken;
		}
		
		public function setEndTokenAction($action) {
			$this->endTokenAction = $action;
		}
		
		public function getEndTokenAction() {
			return $this->endTokenAction;
		}
		
		public static function getAll() {
			return self::$instructionStack;
		}
	}
?>
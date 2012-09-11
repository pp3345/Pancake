<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_VARIABLE.php                                     	*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\TokenHandlers;
	
	use Moody\TokenHandler;
	use Moody\TokenVM;
	use Moody\Token;
	use Moody\Configuration;

	/**
	 * Variable name compression handler
	 */
	class VariableHandler implements TokenHandler {
		private static $instance = null;
		private $variableMappings = array();
		private $nextLetter = "A";
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		private function __construct() {
			TokenVM::globalRegisterTokenHandler(T_VARIABLE, $this);
		}

		public function execute(Token $token, TokenVM $vm) {
			static $forbiddenVariables = array('$this', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_ENV', '$_SESSION');
			
			if(Configuration::get('compressvariables', false) && !in_array($token->content, $forbiddenVariables)) {
				if(!isset($this->variableMappings[$token->content])) {
					do {
						$this->mapVariable($token->content, is_int($this->nextLetter) ? '$i' . $this->nextLetter : '$' . $this->nextLetter);

						if($this->nextLetter === "Z")
							$this->nextLetter = "a";
						else if($this->nextLetter === "z")
							$this->nextLetter = 0;
						else if(is_int($this->nextLetter))
							$this->nextLetter++;
						else
							$this->nextLetter = chr(ord($this->nextLetter) + 1);
					} while(count(array_keys($this->variableMappings, $this->variableMappings[$token->content])) > 1);
				}

				$token->content = $this->variableMappings[$token->content];
			}

			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
		}

		public function mapVariable($originalVariable, $newName) {
			return $this->variableMappings[$originalVariable] = $newName;
		}
	}
?>
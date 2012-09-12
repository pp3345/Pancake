<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_VARIABLE.php                                     			*/
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
			TokenVM::globalRegisterTokenHandler(T_OBJECT_OPERATOR, $this);
		}

		public function execute(Token $token, TokenVM $vm) {
			static $forbiddenVariables = array('$this', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_ENV', '$_SESSION');
			
			if(Configuration::get('compressvariables', false) && !in_array($token->content, $forbiddenVariables)) {
				if($token->type == T_OBJECT_OPERATOR) {
					if(!Configuration::get('compressproperties', false))
						return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
					$tokenArray = $vm->getTokenArray();
					
					$varToken = current($tokenArray);
					if($varToken->type != T_STRING)
						goto end;
					while($tokenX = next($tokenArray)) {
						if($tokenX->type == T_WHITESPACE)
							continue;
						if($tokenX->type == T_ROUND_BRACKET_OPEN)
							goto end;
						break;
					}
					
					$localToken = $varToken;
					
					$localToken->content = '$' . $localToken->content;
				} else
					$localToken = $token;
				
				if(!isset($this->variableMappings[$localToken->content])) {
					if(!Configuration::get('compressproperties', false)) {
						$tokenArray = $vm->getTokenArray();
						prev($tokenArray);
						while($tokenX = prev($tokenArray)) {
							switch($tokenX->type) {
								case T_STATIC:
									$static = true;
									goto map;
								default:
									if(!isset($static))
										goto end;
									goto map;
								case T_PUBLIC:
								case T_PROTECTED:
								case T_PRIVATE:
								case T_WHITESPACE:
								case T_FORCED_WHITESPACE:
									continue 2;
							}
						}
					}
					
					map:
					
					do {
						$this->mapVariable($localToken->content, is_int($this->nextLetter) ? '$i' . $this->nextLetter : '$' . $this->nextLetter);

						if($this->nextLetter === "Z")
							$this->nextLetter = "a";
						else if($this->nextLetter === "z")
							$this->nextLetter = 0;
						else if(is_int($this->nextLetter))
							$this->nextLetter++;
						else
							$this->nextLetter = chr(ord($this->nextLetter) + 1);
					} while(count(array_keys($this->variableMappings, $this->variableMappings[$localToken->content])) > 1);
				}

				$localToken->content = isset($varToken) ? substr($this->variableMappings[$localToken->content], 1) : $this->variableMappings[$localToken->content];
			}

			end:
			
			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
		}

		public function mapVariable($originalVariable, $newName) {
			return $this->variableMappings[$originalVariable] = $newName;
		}
	}
?>
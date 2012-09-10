<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_WHITESPACE.php                                     		*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\TokenHandlers;
	
	use Moody\TokenHandler;
	use Moody\TokenVM;
	use Moody\Configuration;
	use Moody\Token;

	class WhitespaceHandler implements TokenHandler {
		private static $instance = null;
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		private function __construct() {
			TokenVM::globalRegisterTokenHandler(T_WHITESPACE, $this);
			TokenVM::globalRegisterTokenHandler(T_ECHO, $this);
			TokenVM::globalRegisterTokenHandler(T_VARIABLE, $this);
			TokenVM::globalRegisterTokenHandler(T_GOTO, $this);
			TokenVM::globalRegisterTokenHandler(T_ELSE, $this);
			TokenVM::globalRegisterTokenHandler(T_NAMESPACE, $this);
			TokenVM::globalRegisterTokenHandler(T_CONST, $this);
			TokenVM::globalRegisterTokenHandler(T_NEW, $this);
			TokenVM::globalRegisterTokenHandler(T_INSTANCEOF, $this);
			TokenVM::globalRegisterTokenHandler(T_INSTEADOF, $this);
			TokenVM::globalRegisterTokenHandler(T_STRING, $this);
			TokenVM::globalRegisterTokenHandler(T_CLASS, $this);
			TokenVM::globalRegisterTokenHandler(T_EXTENDS, $this);
			TokenVM::globalRegisterTokenHandler(T_PUBLIC, $this);
			TokenVM::globalRegisterTokenHandler(T_PROTECTED, $this);
			TokenVM::globalRegisterTokenHandler(T_PRIVATE, $this);
			TokenVM::globalRegisterTokenHandler(T_FINAL, $this);
			TokenVM::globalRegisterTokenHandler(T_STATIC, $this);
			TokenVM::globalRegisterTokenHandler(T_FUNCTION, $this);
			TokenVM::globalRegisterTokenHandler(T_RETURN, $this);
		}
	
		public function execute(Token $token, TokenVM $vm) {
			if(Configuration::get('deletewhitespaces', false)) {
				switch($token->type) {
					case T_WHITESPACE:
						return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN | TokenVM::DELETE_TOKEN;
					case T_ECHO:
					case T_RETURN:
					case T_PUBLIC:
					case T_PROTECTED:
					case T_PRIVATE:
					case T_STATIC:
					case T_FINAL:
						$tokenArray = $vm->getTokenArray();

						if($tokenX = current($tokenArray)) {
							if($tokenX->type != T_WHITESPACE)
								return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
							else if(($tokenX = next($tokenArray)) && $tokenX->type != T_CONSTANT_ENCAPSED_STRING && $tokenX->type != T_VARIABLE)
								$this->insertForcedWhitespace($vm);
						}
						break;
					case T_VARIABLE:
						$tokenArray = $vm->getTokenArray();
						
						if($tokenX = current($tokenArray)) {
							if($tokenX->type != T_WHITESPACE)
								return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
							else if(($tokenX = next($tokenArray)) && ($tokenX->type == T_AS || $tokenX->type == T_INSTANCEOF))
								$this->insertForcedWhitespace($vm);
						}
						break;
					case T_GOTO:
					case T_NAMESPACE:
					case T_CONST:
					case T_NEW:
					case T_INSTANCEOF:
					case T_INSTEADOF:
					case T_CLASS:
					case T_EXTENDS:
					case T_FUNCTION:
						$this->insertForcedWhitespace($vm);
						break;
					case T_ELSE:
						$tokenArray = $vm->getTokenArray();
						
						if($tokenX = current($tokenArray)) {
							if($tokenX->type != T_WHITESPACE)
								return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
							else if(($tokenX = next($tokenArray)) && $tokenX->type != T_CURLY_OPEN)
								$this->insertForcedWhitespace($vm);
						}
						break;
					case T_STRING:
						$tokenArray = $vm->getTokenArray();
						
						if($tokenX = current($tokenArray)) {
							if($tokenX->type != T_WHITESPACE)
								return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
							else if(($tokenX = next($tokenArray)) && ($tokenX->type == T_EXTENDS || $tokenX->type == T_INSTEADOF))
								$this->insertForcedWhitespace($vm);
						}
						break;
				}
			}

			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
		}

		private function insertForcedWhitespace(TokenVM $vm) {
			$token = new Token;
			$token->content = " ";
			$token->type = T_FORCED_WHITESPACE;
			$token->fileName = "Moody WhitespaceHandler";
			$vm->insertTokenArray(array($token));
		}
	}
?>
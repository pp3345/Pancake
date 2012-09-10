<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_COMMENT.php                                     			*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\TokenHandlers;
	
	use Moody\TokenHandlerWithRegister;
	use Moody\TokenVM;
	use Moody\Token;
	use Moody\InstructionProcessorException;
	use Moody\Configuration;
	use Moody\ConstantContainer;
	use Moody\InstructionHandler;

	/**
	 * Comment handler / Instruction processor
	 */
	class InstructionProcessor implements TokenHandlerWithRegister {
		private static $instance = null;
		private $handlerStack = array();
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		private function __construct() {
			TokenVM::globalRegisterTokenHandler(T_COMMENT, $this);
		}
	
		public function execute(Token $token, TokenVM $vm) {
			$content = str_replace(array("//", "/*", "*/", "#"), "", $token->content);
			
			$matches = array();
			$vmRetval = 0;
			
			if(preg_match('~^\s*(\.([A-Za-z]+))~', $content, $matches)) {
				$instruction = strtolower($matches[2]);

				if(isset($this->handlerStack[$instruction])) {
					if(!is_callable(array($this->handlerStack[$instruction], 'execute')))
						throw new InstructionProcessorException('Handler for instruction "' . $matches[1] . '" does not exist or is not callable', $token);
					$vmRetval = $this->handlerStack[$instruction]->execute($token, $matches[1], $this, $vm);
				} else if(!Configuration::get('ignoreunknowninstruction', false))
					throw new InstructionProcessorException('Unknown instruction "' . $matches[1] . '"', $token);
			} else if(Configuration::get('deletecomments', false))
				$vmRetval = TokenVM::DELETE_TOKEN;
	
			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN | $vmRetval;
		}
		
		public function register(Token $token, TokenVM $vm) {
			$content = str_replace(array("//", "/*", "*/", "#"), "", $token->content);
				
			$matches = array();
			
			if(preg_match('~^\s*(\.([A-Za-z]+))~', $content, $matches)) {
				$instruction = strtolower($matches[2]);

				if(isset($this->handlerStack[$instruction])) {
					if(is_callable(array($this->handlerStack[$instruction], 'register')))
						$this->handlerStack[$instruction]->register($token, $matches[1], $this, $vm);
				}
			}
		}
		
		private function inlineExecute(Token $token) {
			$content = str_replace(array("//", "/*", "*/", "#"), "", $token->content);
				
			$matches = array();
				
			if(preg_match('~^\s*(\.([A-Za-z]+))~', $content, $matches)) {
				$instruction = strtolower($matches[2]);
			
				if(isset($this->handlerStack[$instruction])) {
					if(!is_callable(array($this->handlerStack[$instruction], 'inlineExecute')))
						throw new InstructionProcessorException('Handler for instruction "' . $matches[1] . '" does not support inline execution or is not callable', $token);
					$this->handlerStack[$instruction]->inlineExecute($token, $matches[1], $this);
				} else if(!Configuration::get('ignoreunknowninstruction', false))
					throw new InstructionProcessorException('Unknown instruction "' . $matches[1] . '"', $token);
			}
		}
		
		public function registerHandler($instruction, InstructionHandler $handler) {
			$this->handlerStack[$instruction] = $handler;
		}
		
		public function parseArguments(Token $origToken, $instructionName, $optionsStr) {
			if($optionsStr)
				$options = str_split($optionsStr);
			else
				$options = array();
			
			if(!strpos($origToken->content, $instructionName))
				throw new InstructionProcessorException('Token corrupted', $origToken);
			
			if(substr($origToken->content, 0, 2) == '/*')
				$content = substr($origToken->content, 2, strrpos($origToken->content, '*/') - 2);
			else if(substr($origToken->content, 0, 1) == '#')
				$content = substr($origToken->content, 1);
			else
				$content = substr($origToken->content, 2);
			$instructionArgs = substr($content, strpos($content, $instructionName) + strlen($instructionName));

			// Tokenize
			$tokens = Token::tokenize('<?php ' . $instructionArgs . ' ?>');
			
			$argNum = 0;
			$optionsOffset = 0;
			$args = $ignoreTokens = array();
			
			foreach($tokens as $token) {
				if($token->type == T_OPEN_TAG
				|| $token->type == T_CLOSE_TAG
				|| $token->type == T_ROUND_BRACKET_OPEN
				|| $token->type == T_ROUND_BRACKET_CLOSE
				|| $token->type == T_COMMA
				|| $token->type == T_WHITESPACE
				|| in_array($token, $ignoreTokens))
					continue;
				
				switch($token->type) {
					case T_STRING:
						if(ConstantContainer::isDefined($token->content))
							$tokenValue = ConstantContainer::getConstant($token->content);
						else
							$tokenValue = $token->content;
						break;
					case T_CONSTANT_ENCAPSED_STRING:
						$tokenValue = eval('return ' . $token->content . ';');
						break;
					case T_TRUE:
						$tokenValue = true;
						break;
					case T_FALSE:
						$tokenValue = false;
						break;
					case T_LNUMBER:
						$tokenValue = (int) $token->content;
						break;
					case T_DNUMBER:
						$tokenValue = (float) $token->content;
						break;
					case T_NULL:
						$tokenValue = null;
						break;
					case T_NS_SEPARATOR:
						$totalString = "";
						$pos = key($tokens) - 1;
						prev($tokens);
						// Resolve previous parts
						while($prev = prev($tokens)) {
							if($prev->type != T_STRING)
								break;
							unset($args[key($args)]);
							$totalString = $prev->content . $totalString;
						}
						
						while(key($tokens) != $pos)
							next($tokens);
						
						// Insert current token
						$totalString .= $token->content;
						
						// Resolve next parts
						while($next = next($tokens)) {
							if($next->type != T_NS_SEPARATOR && $next->type != T_STRING)
								break;
							$totalString .= $next->content;
							
							// The doc states
							// "As foreach relies on the internal array pointer changing it within the loop may lead to unexpected behavior."
							// This is not true. Therefore I have to use this workaround. Silly PHP.
							$ignoreTokens[] = $next;
						}
						
						if(ConstantContainer::isDefined($totalString))
							$tokenValue = ConstantContainer::getConstant($totalString);
						else
							$tokenValue = $totalString;
						break;
					case T_COMMENT:
						$this->inlineExecute($token);
						/* fallthrough */
					default:
						$tokenValue = $token->content;
				}
				
				parseArg:
				
				if(!isset($options[$argNum + $optionsOffset]) || !$options[$argNum + $optionsOffset]) {
					$args[] = $tokenValue;
				} else if($options[$argNum + $optionsOffset] == '?') {
					$optionsOffset++;
					goto parseArg;
				} else {
					switch(strtolower($options[$argNum + $optionsOffset])) {
						default:
							throw new InstructionProcessorException('Illegal option for argument parser given: ' . $options[$argNum + $optionsOffset], $origToken);
						case 'n':
							if(is_numeric($tokenValue) && is_string($tokenValue))
								$args[] = (float) $tokenValue;
							else if(is_int($tokenValue) || is_float($tokenValue) || $tokenValue === null)
								$args[] = $tokenValue;
							else
								throw new InstructionProcessorException('Illegal argument ' . ($argNum + 1). ' for ' . $instructionName . ': ' . $token->content . ' given, number expected' , $origToken);
							break;
						case 's':
							if((is_string($tokenValue) && ($token->type == T_STRING || $token->type == T_CONSTANT_ENCAPSED_STRING)) || $tokenValue === null)
								$args[] = $tokenValue;
							else
								throw new InstructionProcessorException('Illegal argument ' . ($argNum + 1). ' for ' . $instructionName . ': ' . $token->content . ' given, string expected' , $origToken);
							break;
						case 'b':
							if(is_bool($tokenValue) || $tokenValue === null)
								$args[] = $tokenValue;
							else
								throw new InstructionProcessorException('Illegal argument ' . ($argNum + 1). ' for ' . $instructionName . ': ' . $token->content . ' given, bool expected' , $origToken);
							break;
						case 'x':
							$args[] = $tokenValue;
					}
				}
				
				$argNum++;
			}
			
			if((strpos($optionsStr, '?') !== false && $argNum < strpos($optionsStr, '?')) || ($argNum < count($options) && strpos($optionsStr, '?') === false))
				throw new InstructionProcessorException($instructionName . ' expects ' . count($options) . ' arguments, ' . $argNum . ' given', $origToken);
			
			return $args;
		}
	}
?>
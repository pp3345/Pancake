<?php

	/****************************************************************/
	/* Moody                                                        */
	/* token.class.php                                              */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	define('T_DOT', 16384);
	define('T_UNKNOWN', 16385);
	define('T_ROUND_BRACKET_OPEN', 16386);
	define('T_ROUND_BRACKET_CLOSE', 16387);
	define('T_COMMA', 16388);
	define('T_TRUE', 16389);
	define('T_FALSE', 16390);
	define('T_NULL', 16391);
	define('T_FORCED_WHITESPACE', 16392);
	define('T_SEMICOLON', 16393);
	
	class Token {
		public $id = 0;
		public $type = 0;
		public $fileID = 0;
		public $fileName = "Unknown";
		public $line = 0;
		public $content = "";
		private static $tokens = 0;
		private static $files = 0;
		private static $typeNames = array(
				T_ABSTRACT => "T_ABSTRACT",
				T_AND_EQUAL => "T_AND_EQUAL",
				T_ARRAY => "T_ARRAY",
				T_ARRAY_CAST => "T_ARRAY_CAST",
				T_AS => "T_AS",
				T_BAD_CHARACTER => "T_BAD_CHARACTER",
				T_BOOLEAN_AND => "T_BOOLEAN_AND",
				T_BOOLEAN_OR => "T_BOOLEAN_OR",
				T_BOOL_CAST => "T_BOOL_CAST",
				T_BREAK => "T_BREAK",
				T_CASE => "T_CASE",
				T_CATCH => "T_CATCH",
				T_CHARACTER => "T_CHARACTER",
				T_CLASS => "T_CLASS",
				T_CLASS_C => "T_CLASS_C",
				T_CLONE => "T_CLONE",
				T_CLOSE_TAG => "T_CLOSE_TAG",
				T_COMMA => "T_COMMA",
				T_COMMENT => "T_COMMENT",
				T_CONCAT_EQUAL => "T_CONCAT_EQUAL",
				T_CONST => "T_CONST",
				T_CONSTANT_ENCAPSED_STRING => "T_CONSTANT_ENCAPSED_STRING",
				T_CONTINUE => "T_CONTINUE",
				T_CURLY_OPEN => "T_CURLY_OPEN",
				T_DEC => "T_DEC",
				T_DECLARE => "T_DECLARE",
				T_DEFAULT => "T_DEFAULT",
				T_DIR => "T_DIR",
				T_DIV_EQUAL => "T_DIV_EQUAL",
				T_DNUMBER => "T_DNUMBER",
				T_DO => "T_DO",
				T_DOC_COMMENT => "T_DOC_COMMENT",
				T_DOLLAR_OPEN_CURLY_BRACES => "T_DOLLAR_OPEN_CURLY_BRACES",
				T_DOT => "T_DOT",
				T_DOUBLE_ARROW => "T_DOUBLE_ARROW",
				T_DOUBLE_CAST => "T_DOUBLE_CAST",
				T_DOUBLE_COLON => "T_DOUBLE_COLON",
				T_ECHO => "T_ECHO",
				T_ELSE => "T_ELSE",
				T_ELSEIF => "T_ELSEIF",
				T_EMPTY => "T_EMPTY",
				T_ENCAPSED_AND_WHITESPACE => "T_ENCAPSED_AND_WHITESPACE",
				T_ENDDECLARE => "T_ENDDECLARE",
				T_ENDFOR => "T_ENDFOR",
				T_ENDFOREACH => "T_ENDFOREACH",
				T_ENDIF => "T_ENDIF",
				T_ENDSWITCH => "T_ENDSWITCH",
				T_ENDWHILE => "T_ENDWHILE",
				T_END_HEREDOC => "T_END_HEREDOC",
				T_EVAL => "T_EVAL",
				T_EXIT => "T_EXIT",
				T_EXTENDS => "T_EXTENDS",
				T_FALSE => "T_FALSE",
				T_FILE => "T_FILE",
				T_FINAL => "T_FINAL",
				T_FOR => "T_FOR",
				T_FORCED_WHITESPACE => "T_FORCED_WHITESPACE",
				T_FOREACH => "T_FOREACH",
				T_FUNCTION => "T_FUNCTION",
				T_FUNC_C => "T_FUNC_C",
				T_GLOBAL => "T_GLOBAL",
				T_GOTO => "T_GOTO",
				T_HALT_COMPILER => "T_HALT_COMPILER",
				T_OPEN_TAG => "T_OPEN_TAG",
				T_ROUND_BRACKET_CLOSE => "T_ROUND_BRACKET_CLOSE",
				T_ROUND_BRACKET_OPEN => "T_ROUND_BRACKET_OPEN",
				T_SEMICOLON => "T_SEMICOLON",
				T_STRING => "T_STRING",
				T_TRUE => "T_TRUE",
				T_UNKNOWN => "T_UNKNOWN",
				T_VARIABLE => "T_VARIABLE",
				T_WHITESPACE => "T_WHITESPACE"
				/* To be continued */);
		
		public function __construct() {
			$this->id = self::$tokens++;
		}

		public static function tokenize($code, $file = null) {
			$tokens = token_get_all($code);

			if(!$tokens)
				throw new MoodyException('Token::tokenize() was called with a non-tokenizable code');
			
			$tokenObjects = array();
			self::$files++;
			
			foreach($tokens as $token) {
				$tokenObject = new Token;
				
				$tokenObject->fileID = self::$files;
				if($file)
					$tokenObject->fileName = $file;

				if(is_array($token)) {
					$tokenObject->type = $token[0];
					$tokenObject->content = $token[1];
					$tokenObject->line = $token[2];
					
					if(strtolower($tokenObject->content) == 'true')
						$tokenObject->type = T_TRUE;
					else if(strtolower($tokenObject->content) == 'false')
						$tokenObject->type = T_FALSE;
					else if(strtolower($tokenObject->content) == 'null')
						$tokenObject->type = T_NULL;
				} else  {
					$tokenObject->content = $token;
					$tokenObject->line = -1;

					switch($token) {
						case '.':
							$tokenObject->type = T_DOT;
							break;
						case '(':
							$tokenObject->type = T_ROUND_BRACKET_OPEN;
							break;
						case ')':
							$tokenObject->type = T_ROUND_BRACKET_CLOSE;
							break;
						case ',':
							$tokenObject->type = T_COMMA;
							break;
						case ';':
							$tokenObject->type = T_SEMICOLON;
							break;
						default:
							$tokenObject->type = T_UNKNOWN;
					}
				}

				$tokenObjects[] = $tokenObject;
			}
			
			return $tokenObjects;
		}
		
		public function __toString() {
			$string = 'Type: ' . (isset(self::$typeNames[$this->type]) ? self::$typeNames[$this->type] : $this->type) . "\r\n";
			$string .= 'Content: ' . $this->content . "\r\n";
			if($this->fileName != "Unknown") {
				$string .= 'Origin: ' . $this->fileName . "\r\n";
				$string .= 'Line: ' . $this->line . "\r\n";
			}
			
			return $string;
		}
		
		public static function makeEvaluatable($value) {
			if(is_string($value))
				return "'" . str_replace("'", "\'", $value) . "'";
			if(is_int($value) || is_float($value))
				return $value;
			if($value === true)
				return "true";
			if($value === false)
				return "false";
			if($value === null)
				return "null";
		}
	}
?>
<?php

	/****************************************************************/
	/* Moody                                                        */
	/* include.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionProcessorException;
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class IncludeHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('include', $this);
			InstructionProcessor::getInstance()->registerHandler('inc', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}

		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 's');

			if(!file_exists($args[0]))
				throw new InstructionProcessorException($args[0] . ' does not exist', $token);
			if(!is_readable($args[0]))
				throw new InstructionProcessorException($args[0] . ' is not readable - Make sure Moody has the rights to read it', $token);

			$file = file_get_contents($args[0]);

			$tokens = Token::tokenize($file, $args[0]);

			switch($tokens[0]->type) {
				case T_OPEN_TAG:
					unset($tokens[0]);
					break;
				case T_INLINE_HTML:
					$token = new Token;
					$token->type = T_CLOSE_TAG;
					$token->content = " ?>";
					$tokensN = array($token);
					foreach($tokens as $token)
						$tokensN[] = $token;
					$tokens = $tokensN;
			}

			end($tokens);

			switch(current($tokens)->type) {
				case T_CLOSE_TAG:
					unset($tokens[key($tokens)]);
					break;
				case T_INLINE_HTML:
					$token = new Token;
					$token->type = T_OPEN_TAG;
					$token->content = "<?php ";
					$tokens[] = $token;
			}

			$vm->insertTokenArray($tokens);

			return TokenVM::DELETE_TOKEN;
		}
	}
?>
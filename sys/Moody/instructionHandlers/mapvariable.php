<?php

	/****************************************************************/
	/* Moody                                                        */
	/* mapvariable.php                 					        	*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionProcessorException;
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenHandlers\VariableHandler;
	use Moody\TokenVM;
	
	class MapVariableHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('mapvariable', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 'ss');

			if(!class_exists('Moody\TokenHandlers\VariableHandler'))
				throw new InstructionProcessorException('Variable mapping is not available as the token handler for T_VARIABLE is not currently loaded', $token);

			VariableHandler::getInstance()->mapVariable($args[0], $args[1]);

			return TokenVM::DELETE_TOKEN;
		}
	}
?>
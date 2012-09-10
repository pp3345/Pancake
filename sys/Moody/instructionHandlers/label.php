<?php

	/****************************************************************/
	/* Moody                                                        */
	/* label.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionProcessorException;
	use Moody\IfInstruction;
	use Moody\InstructionHandlerWithRegister;
	use Moody\ConstantContainer;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class LabelHandler implements InstructionHandlerWithRegister {
		private static $instance = null;
		private static $labels = array();
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('label', $this);
			InstructionProcessor::getInstance()->registerHandler('jumplabel', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			return TokenVM::DELETE_TOKEN;
		}
	
		public function register(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm) {
			$args = $processor->parseArguments($token, $instructionName, 's');
			
			if(isset(self::$labels[$args[0]]))
				throw new InstructionProcessorException('Double definition of jump label "' . $args[0] . '" (first definition at ' . self::$labels[$args[0]]->fileName . ':' . self::$labels[$args[0]]->line, $token);
			
			self::$labels[$args[0]] = $token;
		}
		
		public static function getLabel($name) {
			if(isset(self::$labels[$name]))
				return self::$labels[$name];
		}
	}
?>
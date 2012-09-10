<?php

	/****************************************************************/
	/* Moody                                                        */
	/* raiseerror.php                 					        	*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers;
	
	use Moody\InstructionProcessorException;
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	
	class RaiseErrorHandler implements InstructionHandler {
		private static $instance = null;
	
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('raiseerror', $this);
			InstructionProcessor::getInstance()->registerHandler('error', $this);
		}
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm = null) {
			$args = $processor->parseArguments($token, $instructionName, 's');

			throw new InstructionProcessorException($args[0], $token);
		}

		public function inlineExecute(Token $token, $instructionName, InstructionProcessor $processor) {
			$this->execute($token, $instructionName, $processor);
		}
	}
?>
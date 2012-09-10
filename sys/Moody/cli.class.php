<?php

	/****************************************************************/
	/* Moody                                                        */
	/* moodyException.class.php                                     */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	/**
	 * Command line interface for Moody
	 */
	class CLI  {
		public function __construct() {
			enter:
			echo 'Please enter file to process: ';
			
			$fileName = str_replace(array("\r\n", "\n", "\r"), "", fread(STDIN, 128));
			if(!file_exists($fileName)) {
				echo "\r\nInvalid filename\r\n";
				goto enter;
			}
			
			ConstantContainer::initialize();
			$tokenArray = Token::tokenize(file_get_contents($fileName), $fileName);
			$vm = new TokenVM();
			try {
				$tokenArray = $vm->execute($tokenArray);
			} catch(\Exception $e) {
				echo (string) $e . "\r\n";
				exit;
			}
			
			$newCode = "";
			
			foreach($tokenArray as $token) {
				$newCode .= $token->content;
			}
			
			echo "\r\nNew code:\r\n";
			echo $newCode;
			echo "\r\n";
		}
	}
	
	require_once 'moodyException.class.php';
	require_once 'VMException.class.php';
	require_once 'token.class.php';
	require_once 'tokenVM.class.php';
	require_once 'tokenHandler.interface.php';
	require_once 'configuration.class.php';
	require_once 'instructionProcessorException.class.php';
	require_once 'instructionHandler.interface.php';
	require_once 'constantContainer.class.php';
	ConstantContainer::initialize();
	
	require_once 'ifInstruction.class.php';
	
	require_once 'tokenHandlers/T_OPEN_TAG.php';
	TokenHandlers\OpenTagHandler::getInstance();
	
	require_once 'tokenHandlers/T_WHITESPACE.php';
	TokenHandlers\WhitespaceHandler::getInstance();

	require_once 'tokenHandlers/T_VARIABLE.php';
	TokenHandlers\VariableHandler::getInstance();
	
	require_once 'tokenHandlers/T_COMMENT.php';
	TokenHandlers\InstructionProcessor::getInstance();
	
	require_once 'instructionHandlers/exit.php';
	InstructionHandlers\ExitHandler::getInstance();
	
	require_once 'instructionHandlers/define.php';
	InstructionHandlers\DefineHandler::getInstance();
	
	require_once 'instructionHandlers/endif.php';
	InstructionHandlers\EndIfHandler::getInstance();
	
	require_once 'instructionHandlers/ifdef.php';
	InstructionHandlers\IfDefHandler::getInstance();
	
	require_once 'instructionHandlers/else.php';
	InstructionHandlers\ElseHandler::getInstance();
	
	require_once 'instructionHandlers/constant.php';
	InstructionHandlers\GetConstantHandler::getInstance();
	
	require_once 'instructionHandlers/undefine.php';
	InstructionHandlers\UndefineHandler::getInstance();
	
	require_once 'instructionHandlers/ifndef.php';
	InstructionHandlers\IfNotDefHandler::getInstance();
	
	require_once 'instructionHandlers/label.php';
	InstructionHandlers\LabelHandler::getInstance();
	
	require_once 'instructionHandlers/goto.php';
	InstructionHandlers\GotoHandler::getInstance();
	
	require_once 'instructionHandlers/if.php';
	InstructionHandlers\IfHandler::getInstance();
	
	require_once 'instructionHandlers/isdefined.php';
	InstructionHandlers\IsDefinedHandler::getInstance();
	
	require_once 'instructionHandlers/macro.php';
	InstructionHandlers\MacroHandler::getInstance();
	
	require_once 'instructionHandlers/elseif.php';
	InstructionHandlers\ElseIfHandler::getInstance();
	
	require_once 'instructionHandlers/eval.php';
	InstructionHandlers\EvalHandler::getInstance();
	
	require_once 'instructionHandlers/configuration.php';
	InstructionHandlers\ConfigurationHandler::getInstance();

	require_once 'instructionHandlers/include.php';
	InstructionHandlers\IncludeHandler::getInstance();

	require_once 'instructionHandlers/mapvariable.php';
	InstructionHandlers\MapVariableHandler::getInstance();

	require_once 'instructionHandlers/raiseerror.php';
	InstructionHandlers\RaiseErrorHandler::getInstance();
	
	new CLI;
?>
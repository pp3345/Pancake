<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* codeProcessor.thread.php                                     */
	/* 2012 Yussuf Khalil                                           */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/
	
	namespace Pancake;
	
	use Moody\ConstantContainer;
	use Moody\Configuration;
	use Moody\TokenVM;
	use Moody\Token;

	if(PANCAKE !== true)
		exit;
	
	/**
	 * @var CodeProcessor
	 */
	global $Pancake_currentThread;
	
	require_once 'Moody/configuration.class.php';
	require_once 'Moody/constantContainer.class.php';
	require_once 'Moody/ifInstruction.class.php';
	require_once 'Moody/instructionHandler.interface.php';
	require_once 'Moody/instructionProcessorException.class.php';
	require_once 'Moody/moodyException.class.php';
	require_once 'Moody/token.class.php';
	require_once 'Moody/tokenHandler.interface.php';
	require_once 'Moody/tokenVM.class.php';
	require_once 'Moody/VMException.class.php';
	
	$dir = scandir('Moody/tokenHandlers');
	
	foreach($dir as $file) {
		if(substr($file, -4, 4) != ".php")
			continue;
		
		require_once 'Moody/tokenHandlers/' . $file;
	}
	
	$dir = scandir('Moody/instructionHandlers');

	foreach($dir as $file) {
		if(substr($file, -4, 4) != ".php")
			continue;
		
		require_once 'Moody/instructionHandlers/' . $file;
	}
	
	foreach(get_declared_classes() as $class) {
		if(in_array('Moody\TokenHandler', class_implements($class)) || in_array('Moody\InstructionHandler', class_implements($class)))
			$class::getInstance();
	}
	
	ConstantContainer::initialize();
	//Configuration::set('deletewhitespaces', true);
	Configuration::set('deletecomments', true);
	Configuration::set('compressvariables', true);
	
	try {
		$tokens = Token::tokenize(file_get_contents($Pancake_currentThread->processFile), $Pancake_currentThread->processFile);
	
		$vm = new TokenVM;
		$result = $vm->execute($tokens);
	} catch(\Exception $e) {
		out((string) $e);
		IPC::send($Pancake_currentThread->returnThread, 0);
		exit;
	}
	
	foreach($result as $token)
		$code .= $token->content;
	
	file_put_contents($Pancake_currentThread->outputFile, $code);
	
	IPC::send($Pancake_currentThread->returnThread, 1);
?>
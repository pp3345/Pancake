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

	require_once 'moody.cphp';

	foreach(get_declared_classes() as $class) {
		if(in_array('Moody\TokenHandler', class_implements($class)) || in_array('Moody\InstructionHandler', class_implements($class)))
			$class::getInstance();
	}
	
	ConstantContainer::initialize();
	
	foreach((array) Config::get('moody') as $name => $value) {
		Configuration::set($name, $value);
	}
	
	try {
		$tokens = Token::tokenize(file_get_contents($Pancake_currentThread->processFile), $Pancake_currentThread->processFile);
		
		$vm = new TokenVM;
		$result = $vm->execute($tokens);
	} catch(\Exception $e) {
		out((string) $e);
		IPC::send($Pancake_currentThread->returnThread, 0);
		exit;
	}
	
	$code = "";
	
	foreach($result as $token)
		$code .= $token->content;
	
	file_put_contents($Pancake_currentThread->outputFile, $code);
	
	IPC::send($Pancake_currentThread->returnThread, 1);
?>
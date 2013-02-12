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

	setThread($Pancake_currentThread);

	if(isset($Pancake_currentThread->vHost)) {
		out("Compiling PHPWorker for vHost \"" . $Pancake_currentThread->vHost->name . "\" - Please wait...");
	} else {
		out("Compiling RequestWorker - Please wait...");
	}

	require_once 'moody_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.cphp';

	foreach(get_declared_classes() as $class) {
		if(in_array('Moody\TokenHandler', class_implements($class)))
			$class::getInstance();
	}

	ConstantContainer::initialize();

	foreach((array) Config::get('moody') as $name => $value) {
		Configuration::set($name, $value);
	}

	if(!Configuration::get('deletewhitespaces', false))
		Configuration::set('supportwhitespacedeletion', false);

	try {
		$tokens = Token::tokenize(file_get_contents($Pancake_currentThread->processFile), $Pancake_currentThread->processFile);

		$vm = new TokenVM;
		$result = $vm->execute($tokens);
	} catch(\Exception $e) {
		out((string) $e);
		exit(0);
	}

	$code = "";

	foreach($result as $token)
		$code .= $token->content;

	file_put_contents($Pancake_currentThread->outputFile, $code);

	exit(1);
?>
<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* codeProcessor.class.php                                      */
	/* 2012 - 2013 Yussuf Khalil                                    */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/

	namespace Pancake;

	if(PANCAKE !== true)
		exit;

	/**
	 * A code processor runs Moody for Pancake code.
	 */
	class CodeProcessor extends Thread {
		public $processFile = "";
		public $outputFile = "";
		public $returnThread = 0;

		/**
		 * Creates a new CodeProcessor
		 *
		 * @return CodeProcessor
		 */
		public function __construct($processFile, $outputFile) {
			global $Pancake_currentThread;

			$this->codeFile = 'threads/single/codeProcessor.thread.php';
			$this->friendlyName = 'CodeProcessor';
			$this->processFile = $processFile;
			$this->outputFile = $outputFile;
		}

		public function run() {
			$this->start();

			pcntl_sigprocmask(\SIG_BLOCK, array(\SIGCHLD));
			pcntl_sigwaitinfo(array(\SIGCHLD), $info);
			pcntl_wait($x, \WNOHANG);

			$this->running = false;

			if($info["status"] !== 1)
				abort();
		}
	}
?>
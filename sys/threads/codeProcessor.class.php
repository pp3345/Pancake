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

			SigProcMask(\SIG_BLOCK, array(\SIGCHLD));
			SigWaitInfo(array(\SIGCHLD), $info);
			Wait($x, \WNOHANG);

            $this->running = false;

            if($info["pid"] != $this->pid) {
                trigger_error("Thread " . $info["pid"] . " exited while in compilation, please report this error to the Pancake support", \E_USER_ERROR);
                $this->kill();
                abort();
            }

			if($info["status"] !== 1) {
			    trigger_error("Failed to compile sources, please report this error to the Pancake support", \E_USER_ERROR);
				abort();
            }
		}
	}
?>
<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.class.php                                      */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    namespace Pancake;

    if(PANCAKE !== true)
        exit;

    /**
    * A request worker processes requests.
    */
    class RequestWorker extends Thread {
        static private $instances = 0;
        static private $codeProcessed = false;
        public $id = 0;
        public $socket = null;
        public $socketName = "";
        public $localSocket = null;

        /**
        * Creates a new RequestWorker
        *
        * @return RequestWorker
        */
        public function __construct() {
        	if(!self::$codeProcessed) {
        		$hash = md5(serialize(Config::get('main'))
        				. serialize((array) Config::get('moody'))
        				. serialize(Config::get('vhosts'))
        				. serialize(Config::get('fastcgi'))
        				. serialize(Config::get('ajp13'))
                        . serialize(Config::get('tls'))
        				. md5_file('vHostInterface.class.php')
        				. md5_file('threads/single/requestWorker.thread.php')
        				. md5_file('natives/Moody/' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.cphp')
        				. md5_file('TLSConnection.class.php')
        				. md5_file('FastCGI.class.php')
        				. md5_file('workerFunctions.php')
        				. md5_file('authenticationFile.class.php')
        				. md5_file('AJP13.class.php')
        				. \PHP_MINOR_VERSION
        				. \PHP_RELEASE_VERSION
        				. VERSION
						. DEBUG_MODE);
        		if(!(file_exists('compilecache/requestWorker.thread.hash')
        		&& file_get_contents('compilecache/requestWorker.thread.hash') == $hash)) {
        			require_once 'threads/codeProcessor.class.php';

        			$codeProcessor = new CodeProcessor('threads/single/requestWorker.thread.php', 'compilecache/requestWorker.thread.cphp');
	        		$codeProcessor->run();
	        		file_put_contents('compilecache/requestWorker.thread.hash', $hash);
					unset($codeProcessor);
        		}
        		self::$codeProcessed = true;
        		unset($hash);
        	}

            // Add instance
            $this->id = self::$instances++;

            $this->doGracefulExit = true;

            $this->socketName = Config::get('main.tmppath') . mt_rand() . "_rworker_local";
            $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($this->socket, $this->socketName);
            socket_listen($this->socket);
            $this->localSocket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_connect($this->localSocket, $this->socketName);
			socket_set_nonblock($this->localSocket);
            $this->socket = socket_accept($this->socket);

            $this->codeFile = 'compilecache/requestWorker.thread.cphp';
            $this->friendlyName = 'RequestWorker #' . ($this->id + 1);
        }
    }
?>

<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.class.php                                          */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

    namespace Pancake;

    if(PANCAKE !== true)
        exit;

    /**
    * PHPWorker that runs PHP-scripts
    */
    class PHPWorker extends Thread {
        static private $instances = 0;
        static private $codeProcessed = array();
        public $id = 0;
        public $socket = null;
        public $socketName = "";
        public $localSocket = null;

        /**
         *
         * @var vHost
         */
        public $vHost = null;

        /**
        * Creates a new PHPWorker
        *
        * @param vHost $vHost
        * @return PHPWorker
        */
        public function __construct(vHost $vHost) {
            $this->vHost = $vHost;

            if(!isset(self::$codeProcessed[$vHost->name])) {
            	$hash = md5(serialize(Config::get('vhosts.' . $vHost->name))
            			. serialize(Config::get('main'))
            			. serialize((array) Config::get('moody'))
            			. md5_file('vHostInterface.class.php')
            			. md5_file('threads/single/phpWorker.thread.php')
            			. md5_file('php/sapi.php')
            			. md5_file('php/util.php')
            			. md5_file('natives/Moody/' . \PHP_MAJOR_VERSION . \PHP_MINOR_VERSION . '.cphp')
            			. md5_file('workerFunctions.php')
            			. \PHP_MINOR_VERSION
            			. \PHP_RELEASE_VERSION
            			. VERSION
						. DEBUG_MODE
                        . extension_loaded("filter"));
            	if(!(file_exists('compilecache/phpWorker.thread.' . $vHost->name . '.hash')
            	&& file_get_contents('compilecache/phpWorker.thread.' . $vHost->name . '.hash') == $hash)) {
            		require_once 'threads/codeProcessor.class.php';

	            	$codeProcessor = new CodeProcessor('threads/single/phpWorker.thread.php', 'compilecache/phpWorker.thread.' . $vHost->name . '.cphp');
	            	$codeProcessor->vHost = $vHost;
	            	$codeProcessor->run();
	            	file_put_contents('compilecache/phpWorker.thread.' . $vHost->name . '.hash', $hash);
            	}
            	self::$codeProcessed[$vHost->name] = true;
            	unset($hash);
            }

            // Save instance
            $this->id = self::$instances++;

            $this->codeFile = 'compilecache/phpWorker.thread.' . $vHost->name . '.cphp';
            $this->friendlyName = 'PHPWorker #' . ($this->id + 1) . ' ("' . $this->vHost->name . '")';

            $this->socketName = Config::get('main.tmppath') . mt_rand() . "_phpworker_local";
            if(strlen($this->socketName) > 107) {
                $this->socketName = '/tmp/' . mt_rand() . "_phpworker_panso";
            }
            
            $this->socket = Socket(\AF_UNIX, \SOCK_STREAM, 0);
            Bind($this->socket, \AF_UNIX, $this->socketName);
            Listen($this->socket);
            $this->localSocket = Socket(\AF_UNIX, \SOCK_STREAM, 0);
            Connect($this->localSocket, \AF_UNIX, $this->socketName);
			SetBlocking($this->localSocket, false);
            $socket = Accept($this->socket);
            Close($this->socket);
            $this->socket = $socket;

            // Start worker
            $this->start(false);
        }

        public function __destruct() {
            if(!class_exists('Pancake\vars') || vars::$Pancake_currentThread != $this) {
                Close($this->localSocket);
                Close($this->socket);
            }
        }
    }
?>

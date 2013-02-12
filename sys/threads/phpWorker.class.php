<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.class.php                                          */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    namespace Pancake;

    if(PANCAKE !== true)
        exit;

    /**
    * PHPWorker that runs PHP-scripts
    */
    class PHPWorker extends Thread {
        static private $instances = array();
        static private $codeProcessed = array();
        public $id = 0;
        public $IPCid = 0;
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
            			. md5_file('HTTPRequest.class.php')
            			. md5_file('php/sapi.php')
            			. md5_file('php/util.php')
            			. md5_file('mime.class.php')
            			. md5_file('moody_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.cphp')
            			. md5_file('workerFunctions.php')
            			. \PHP_MINOR_VERSION
            			. \PHP_RELEASE_VERSION
            			. VERSION
						. DEBUG_MODE);
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
            self::$instances[] = $this;

            // Set address for IPC based on vHost-ID
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PHP_WORKER_TYPE.$this->vHost->id;

            $this->codeFile = 'compilecache/phpWorker.thread.' . $vHost->name . '.cphp';
            $this->friendlyName = 'PHPWorker #'.($this->id+1).' ("'.$this->vHost->name.'")';

            $this->socketName = Config::get('main.tmppath') . mt_rand() . "_phpworker_local";
            $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($this->socket, $this->socketName);
            socket_listen($this->socket);
            $this->localSocket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_connect($this->localSocket, $this->socketName);
            $this->socket = socket_accept($this->socket);

            // Start worker
            $this->start(false);
        }
    }
?>

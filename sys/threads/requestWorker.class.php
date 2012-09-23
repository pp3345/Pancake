<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.class.php                                      */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    /**
    * A request worker processes requests.
    */
    class RequestWorker extends Thread {
        static private $instances = array();
        static private $codeProcessed = false;
        public $id = 0;
        public $IPCid = 0;
        
        /**
        * Creates a new RequestWorker
        * 
        * @return RequestWorker
        */
        public function __construct() {
        	if(!self::$codeProcessed) {
        		$hash = md5(serialize(Config::get('main')) 
        				. serialize((array) Config::get('moody')) 
        				. md5_file('threads/single/requestWorker.thread.php') 
        				. md5_file('HTTPRequest.class.php')
        				. md5_file('invalidHTTPRequest.exception.php')
        				. md5_file('mime.class.php')
        				. md5_file('moody.cphp')
        				. md5_file('TLSConnection.class.php')
        				. md5_file('FastCGI.class.php'));
        		if(!(file_exists('threads/single/requestWorker.thread.hash')
        		&& file_get_contents('threads/single/requestWorker.thread.hash') == $hash)) {
        			require_once 'threads/codeProcessor.class.php';
        			
	        		$codeProcessor = new CodeProcessor('threads/single/requestWorker.thread.php', 'threads/single/requestWorker.thread.cphp');
	        		$codeProcessor->run();
	        		file_put_contents('threads/single/requestWorker.thread.hash', $hash);
        		}
        		self::$codeProcessed = true;
        		unset($hash);
        	}
        	
            // Add instance
            self::$instances[] = $this;
            
            // Set id and address for IPC
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = REQUEST_WORKER_TYPE . $this->id;
            
            $this->codeFile = 'threads/single/requestWorker.thread.cphp';
            $this->friendlyName = 'RequestWorker #' . ($this->id+1);
        }
    }
?>

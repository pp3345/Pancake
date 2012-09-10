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
        	if(!$this->codeProcessed) {
        		$codeProcessor = new CodeProcessor('threads/single/requestWorker.thread.php', 'threads/single/requestWorker.thread.cphp');
        		$codeProcessor->run();
        		$this->codeProcessed = true;
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

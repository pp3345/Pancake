<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.class.php                                      */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * RequestWorker which retrieves requests from SocketWorkers    
    */
    class Pancake_RequestWorker extends Pancake_Thread {
        static private $instances = array();
        public $id = 0;
        public $IPCid = 0;
        
        /**
        * Creates a new RequestWorker
        * 
        * @return RequestWorker
        */
        public function __construct() {
            // Add instance
            self::$instances[] = $this;
            
            // Set RequestWorker-ID and address for IPC
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PANCAKE_REQUEST_WORKER_TYPE.$this->id;
            
            $this->codeFile = 'threads/single/requestWorker.thread.php';
            $this->friendlyName = 'RequestWorker #'.($this->id+1);
            
            // Start worker
            $this->start();
        }
    }
?>

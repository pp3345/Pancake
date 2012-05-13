<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.class.php                                      */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    /**
    * RequestWorker which retrieves requests from SocketWorkers    
    */
    class RequestWorker extends Thread {
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
            $this->IPCid = REQUEST_WORKER_TYPE.$this->id;
            
            $this->codeFile = 'threads/single/requestWorker.thread.php';
            $this->friendlyName = 'RequestWorker #'.($this->id+1);
            
            // Start worker
            $this->start();
        }
    }
?>

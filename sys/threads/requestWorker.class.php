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
        private $id = 0;
        public $IPCid = 0;
        public $isAvailable = true;
        
        
        /**
        * Creates a new RequestWorker
        * 
        * @return RequestWorker
        */
        public function __construct() {
            // Update local data from Shared Memory and add instance
            if(self::$instances) self::getSHMEM();
            self::$instances[] = $this;
            
            // Set RequestWorker-ID and address for IPC
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PANCAKE_REQUEST_WORKER_TYPE.$this->id;
            
            $this->codeFile = 'threads/single/requestWorker.thread.php';
            $this->friendlyName = 'RequestWorker #'.$this->id;
            
            // Start worker
            $this->start();
            
            // Update Shared Memory
            self::updateSHMEM();
        }
        
        /**
        * Returns an available worker
        * 
        * @return RequestWorker
        */
        static public function findAvailable() {
            // Update local data from Shared Memory
            while(!self::getSHMEM())
                continue;
            
            // Search for available workers
            while(true)
                foreach(self::$instances as $worker) {
                    if($worker->isAvailable)
                        return $worker;
                }
        }
        
        protected function setAvailable() {
            // Update local data from Shared Memory
            self::getSHMEM();
            
            // Set availability
            $this->isAvailable = ($this->isAvailable) ? false : true;
            self::$instances[$this->id] = $this;
            
            // Update Shared Memory
            self::updateSHMEM();
        }
        
        /**
        * Let a request-worker handle a request
        * 
        * @param int $port Port with incoming request
        * @return bool Whether sending the request was successful or not
        */
        public function handleRequest($port) {
            if(!$this->isAvailable)
                return false;
            if(!Pancake_IPC::send($this->IPCid, $port))
                return false;
            return true; 
        }
        
        /**
        * Updates local instance data
        * 
        */
        static protected function getSHMEM() {
            if(!(self::$instances = Pancake_SharedMemory::get(PANCAKE_REQUEST_WORKER_TYPE.'0001')))
                return false;
            return true;
        }
        
        /**
        * Updates global instance data
        * 
        */
        static protected function updateSHMEM() {
            if(!Pancake_SharedMemory::put(self::$instances, PANCAKE_REQUEST_WORKER_TYPE.'0001'))
                return false;
            return true;
        }
    }
?>

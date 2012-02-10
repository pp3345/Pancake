<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHostWorker.class.php                                        */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    class Pancake_vHostWorker extends Pancake_Thread {
        static private $instances = array();
        public $id = 0;
        public $IPCid = 0;
        public $isAvailable = true;
        public $name = null;
        
        /**
        * Creates a new vHostWorker
        * 
        * @param string $name Name of the vHost
        * @return vHostWorker
        */
        public function __construct($name) {
            // Update local data from Shared Memory and add instance
            if(self::$instances) self::getSHMEM();
            self::$instances[] = $this;
            
            // Set vHostWorker-ID and address for IPC
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PANCAKE_VHOST_WORKER_TYPE.$this->id;
            
            $this->name = $name;
            $this->codeFile = 'threads/single/vHostWorker.thread.php';
            $this->friendlyName = 'vHostWorker #'.$this->id.' for "'.$this->name.'"';
            
            // Start worker
            $this->start();
            
            // Update Shared Memory
            $this->updateSHMEM();
            usleep(10000);
        }
        
        /**
        * Returns an available worker
        * 
        * @param string $vHost Listen-address of the vHost
        * @return vHostWorker
        */
        static public function findAvailable($listen) {
            // Search for available workers
            while(true) {
                self::getSHMEM();
                foreach(self::$instances as $worker) {
                    if($worker->isAvailable)
                        foreach($worker->listen as $address)
                            if($address == $listen)
                                return $worker;
                }
            }
        }
        
        /**
        * Sets the availability of a vHostWorker
        * 
        */
        protected function setAvailable() {
            // Set availability
            $this->isAvailable = ($this->isAvailable) ? false : true;
            
            // Update Shared Memory
            $this->updateSHMEM();
        }
        
        /**
        * Let a vHostWorker handle a request
        * 
        * @param Pancake_RequestWorker $worker RequestWorker handling this request
        * @param string $request Request-data
        * @return bool Whether sending the request was successful or not
        */
        public function handleRequest(Pancake_RequestWorker $worker, $request) {
            if(!$this->isAvailable)
                return false;
            if(!Pancake_IPC::send($this->IPCid, $worker->IPCid."\n".$request))
                return false;
            return true; 
        }
        
        /**
        * Updates local instance data
        * 
        */
        static protected function getSHMEM() {
            if(!(self::$instances = Pancake_SharedMemory::get(PANCAKE_VHOST_WORKER_TYPE.'0001')))
                return false;
            return true;
        }
        
        /**
        * Updates global instance data
        * 
        */
        protected function updateSHMEM() {
            /*if(!Pancake_SharedMemory::put(self::$instances, PANCAKE_REQUEST_WORKER_TYPE.'0001'))
                return false;*/
            Pancake_IPC::send(PANCAKE_VHOST_WORKER_CONTROLLER_TYPE, $this);
            return true;
        }
    }
?>

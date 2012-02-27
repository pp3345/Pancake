<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.class.php                                          */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * PHPWorker that runs PHP-scripts    
    */
    class Pancake_PHPWorker extends Pancake_Thread {
        static private $instances = array();
        public $id = 0;
        public $IPCid = 0;
        public $vHost = null;
        
        /**
        * Creates a new PHPWorker
        * 
        * @param Pancake_vHost $vHost
        * @return Pancake_PHPWorker
        */
        public function __construct(Pancake_vHost $vHost) {
            $this->vHost = $vHost;
            
            // Save instance
            self::$instances[] = $this;
            
            // Set address for IPC based on vHost-ID
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PANCAKE_PHP_WORKER_TYPE.$this->vHost->getID();
            
            $this->codeFile = 'threads/single/phpWorker.thread.php';
            $this->friendlyName = 'PHPWorker #'.($this->id+1).' ("'.$this->vHost->getName().'")';
            
            // Start worker
            $this->startManually();
            
            usleep(10000);
        }
        
        /**
        * Let a PHPWorker handle a request
        *
        * @param Pancake_HTTPRequest $request
        * @return int SharedMem-key
        */
        static public function handleRequest(Pancake_HTTPRequest $request) {
            if(!($key = Pancake_SharedMemory::put($request)))
                return false;
            if(!Pancake_IPC::send(PANCAKE_PHP_WORKER_TYPE.$request->getvHost()->getID(), $key))
                return false;
            return $key;
        }
    }
?>

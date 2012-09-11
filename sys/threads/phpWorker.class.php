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
            
            if(!isset(self::$codeProcessed[$vHost->getName()])) {
            	$codeProcessor = new CodeProcessor('threads/single/phpWorker.thread.php', 'threads/single/phpWorker.thread.' . $vHost->getName() . '.cphp');
            	$codeProcessor->vHost = $vHost;
            	$codeProcessor->run();
            	self::$codeProcessed[$vHost->getName()] = true;
            }
            
            // Save instance
            self::$instances[] = $this;
            
            // Set address for IPC based on vHost-ID
            $this->id = max(array_keys(self::$instances));
            $this->IPCid = PHP_WORKER_TYPE.$this->vHost->getID();
            
            $this->codeFile = 'threads/single/phpWorker.thread.' . $vHost->getName() . '.cphp';
            $this->friendlyName = 'PHPWorker #'.($this->id+1).' ("'.$this->vHost->getName().'")';
            
            // Start worker
            $this->start(false);
        }
    }
?>

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
            	$hash = md5(serialize(Config::get('vhosts.' . $vHost->getName())) 
            			. serialize(Config::get('main')) 
            			. serialize((array) Config::get('moody'))
            			. md5_file('threads/single/phpWorker.thread.php')
            			. md5_file('HTTPRequest.class.php')
            			. md5_file('php/sapi.php')
            			. md5_file('php/util.php')
            			. md5_file('mime.class.php'));
            	if(!(file_exists('threads/single/phpWorker.thread.' . $vHost->getName() . '.hash') 
            	&& file_get_contents('threads/single/phpWorker.thread.' . $vHost->getName() . '.hash') == $hash)) {
            		require_once 'threads/codeProcessor.class.php';
            		
	            	$codeProcessor = new CodeProcessor('threads/single/phpWorker.thread.php', 'threads/single/phpWorker.thread.' . $vHost->getName() . '.cphp');
	            	$codeProcessor->vHost = $vHost;
	            	$codeProcessor->run();
	            	file_put_contents('threads/single/phpWorker.thread.' . $vHost->getName() . '.hash', $hash);
            	}
            	self::$codeProcessed[$vHost->getName()] = true;
            	unset($hash);
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

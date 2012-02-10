<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHostWorkerController.class.php                              */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    class Pancake_vHostWorkerController extends Pancake_Thread {
        private static $instance = null;
        public $IPCid = 0;
        
        public static function getInstance() {
            if(!$instance)
                self::$instance = new Pancake_vHostWorkerController();
            return $instance;
        }
        
        public function __construct() {
            $this->IPCid = PANCAKE_VHOST_WORKER_CONTROLLER_TYPE;
            
            $this->codeFile = 'threads/single/vHostWorkerController.thread.php';
            $this->friendlyName = 'vHostWorkerController';
            $this->start();
        }
    }
?>

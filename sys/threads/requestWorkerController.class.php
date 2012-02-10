<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorkerController.class.php                            */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    class Pancake_RequestWorkerController extends Pancake_Thread {
        private static $instance = null;
        public $IPCid = 0;
        
        public static function getInstance() {
            if(!$instance)
                self::$instance = new Pancake_RequestWorkerController();
            return $instance;
        }
        
        public function __construct() {
            $this->IPCid = PANCAKE_REQUEST_WORKER_CONTROLLER_TYPE;
            
            $this->codeFile = 'threads/single/requestWorkerController.thread.php';
            $this->friendlyName = 'RequestWorkerController';
            $this->start();
        }
    }
?>

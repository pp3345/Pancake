<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* socketWorker.class.php                                       */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * SocketWorker which listens on a single socket and handles requests to available RequestWorkers
    */
    class Pancake_SocketWorker extends Pancake_Thread {
        protected $port = 80;
        public $IPCid = 0;
        
        /**
        * Creates a new SocketWorker
        * 
        * @param int $port The port, on which the SocketWorker will listen
        * @return Pancake_SocketWorker
        */
        public function __construct($port = 80) {
            $this->port = (int) $port;
            
            // Check if port is out of range
            if($this->port < 1 || $this->port > 65535) {
                trigger_error('Port out of range: '.$port, E_WARNING);
                return;
            }
            
            $this->IPCid = PANCAKE_SOCKET_WORKER_TYPE.$port;
            
            $this->codeFile = 'threads/single/socketWorker.thread.php';
            $this->friendlyName = 'SocketWorker for port '.$port;
            $this->start();
        }
    }
?>

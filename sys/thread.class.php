<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* thread.class.php                                             */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
  
    if(PANCAKE_HTTP !== true)
        exit;
  
    /**
    * Multi-Threading in PHP.
    */
    class Pancake_Thread {
        protected $codeFile = null;
        public $pid = 0;
        public $ppid = 0;
        public $running = false;
        public $friendlyName;
        
        /**
        * Create new Thread
        * 
        * @param string $codeFile Path to the file to be executed by the new thread
        * @param string $friendlyName Friendly name for the thread
        * @return Thread
        */
        public function __construct($codeFile, $friendlyName = null) {
            $this->codeFile = $codeFile;    
            $this->friendlyName = $friendlyName;
        }
        
        /**
        * Starts the Thread
        */
        public function start() {
            if(!file_exists($this->codeFile) || !is_readable($this->codeFile)) {
                trigger_error('Thread can\'t be created because the specified codeFile isn\'t available', E_USER_WARNING);
                return false;
            }
            $this->ppid = posix_getpid();
            $this->pid = pcntl_fork();
           
            if($this->pid == -1)                // On error
                return false;
            else if($this->pid) {               // Parent 
                $this->running = true;
                return true;
            } else {                            // Child
                $this->running = true;
                $this->pid = posix_getpid();
                global $currentThread;
                $currentThread = $this;
                if(PANCAKE_PROCTITLE === true)
                    setproctitle('Pancake '.$this->friendlyName);
                require $this->codeFile;
                exit;
            }
        }
        
        /**
        * Stops the Thread with SIGTERM, may stay alive in some cases
        */
        public function stop() {
            if(!posix_kill($this->pid, SIGTERM))
                return false;
            $this->running = false;
            return true;
        }
        
        /**
        * Kills the Thread with SIGKILL, Thread can't resist being killed
        */
        public final function kill() {
            if(!posix_kill($this->pid, SIGKILL))
                return false;
            $this->running = false;
            return true;
        }
        
        /**
        * Sends a signal to a thread, but not SIGTERM or SIGKILL - use stop() or kill() for sending such signals
        * 
        * @param int $signal The signal to be sent
        */
        public final function signal($signal) {
            if($signal == SIGKILL || $signal == SIGTERM)
                return false;
            if(!posix_kill($this->pid, $signal))
                return false;
            return true;
        }
        
        /**
        * Sends a signal to the parent of the thread
        * 
        * @param int $signal The signal to be sent
        */
        public final function parentSignal($signal) {
            if(!posix_kill($this->ppid, $signal))
                return false;
            return true;
        }
    }
?>

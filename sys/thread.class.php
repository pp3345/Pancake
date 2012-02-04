<?php
  
    /****************************************************************/
    /* dreamServ                                                    */
    /* thread.class.php                                             */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
  
    /**
    * Multi-Threading in PHP.
    */
    class Thread {
        private $codeFile = null;
        public $pid = 0;
        public $ppid = 0;
        public $running = false;
        public $pipe = null;
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
            if(!file_exists($this->codeFile))
                return false;
            $this->ppid = posix_getpid();
            $this->pid = pcntl_fork();
           
            if($this->pid == -1)                // On error
                return false;
            else if($this->pid) {               // Parent 
                //$this->pipe = new CommunicationPipe($this);
                return true;
            } else {                            // Child
                $this->pid = posix_getpid();
                //$this->pipe = new CommunicationPipe($this);
                include $this->codeFile;
                exit;
            }
        }
        
        /**
        * Stops the Thread with SIGTERM, may stay alive in some cases
        */
        public final function stop() {
            if(!posix_kill($this->pid, SIGTERM))
                return false;
            unset($this->pipe);
            return true;
        }
        
        /**
        * Kills the Thread with SIGKILL, Thread can't resist being killed
        */
        public final function kill() {
            if(!posix_kill($this->pid, SIGKILL))
                return false;
            unset($this->pipe);
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

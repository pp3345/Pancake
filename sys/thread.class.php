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
        public $codeFile = null;
        public $pid = 0;
        public $ppid = 0;
        public $running = false;
        public $friendlyName = null;
        public $startedManually = false;
        private static $threadCache = array();
        
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
        * Checks if the codeFile is valid
        * 
        */
        private function checkCodeFile() {
            if(!file_exists($this->codeFile) || !is_readable($this->codeFile)) {
                trigger_error('Thread can\'t be created because the specified codeFile isn\'t available', E_USER_WARNING);
                return false;
            }
            return true;
        }
        
        /**
        * Starts the Thread
        */
        public function start() {
            if(!$this->checkCodeFile())
                return false;
            
            $this->ppid = posix_getpid();
            $this->pid = pcntl_fork();
           
            if($this->pid == -1)                // On error
                return false;
            else if($this->pid) {               // Parent 
                if($key = array_search($this, self::$threadCache))
                    unset(self::$threadCache[$key]);
                self::$threadCache[$this->pid] = $this;
                $this->running = true;
                return true;
            } else {                            // Child
                $this->running = true;
                $this->pid = posix_getpid();
                global $Pancake_currentThread;
                $Pancake_currentThread = $this;
                dt_set_proctitle('Pancake '.$this->friendlyName);
                require $this->codeFile;
                exit;
            }
        }
        
        /**
        * Start the thread manually - A bit dirty, but works
        */
        public function startManually() {
            if(!$this->checkCodeFile())
                return false;
            
            $this->ppid = posix_getpid();
            $this->pid = pcntl_fork();
           
            if($this->pid == -1)                // On error
                return false;
            else if($this->pid) {               // Parent 
                if($key = array_search($this, self::$threadCache))
                    unset(self::$threadCache[$key]);
                self::$threadCache[$this->pid] = $this;
                $this->running = true;
                return true;
            } else {                            // Child
                $this->running = true;
                $this->pid = posix_getpid();
                global $Pancake_currentThread;
                $Pancake_currentThread = $this;
                dt_set_proctitle('Pancake '.$this->friendlyName);
                $this->startedManually = true;
                return true;
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
        
        /**
        * Waits until the Thread exited and kills it if it does not respond in time
        * 
        */
        public final function waitForExit() {
            if(pcntl_waitpid($this->pid, $x, WNOHANG) === 0) {
                Pancake_out('Waiting for ' . $this->friendlyName . ' to stop');
                // Sleep maximum 1 second
                for($i = 0;$i < 200 && pcntl_waitpid($this->pid, $x, WNOHANG) === 0; $i++)
                    usleep(10000);
                if(pcntl_waitpid($this->pid, $x, WNOHANG) === 0) {
                    Pancake_out('Killing worker');
                    $this->kill();
                }
            }
        }       
        
        public final static function get($pid) {
            return self::$threadCache[$pid];
        }
        
        public final static function getAll() {
            return self::$threadCache;
        }
    }
?>

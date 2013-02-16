<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* thread.class.php                                             */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
  
    namespace Pancake;
  
    if(PANCAKE !== true)
        exit;
  
    /**
    * Multi-Threading in PHP.
    */
    class Thread {
        public $codeFile = null;
        public $pid = 0;
        public $ppid = 0;
        public $running = false;
        public $friendlyName = null;
        public $doGracefulExit = false;
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
        * 
        * @return bool
        */
        public function start($loadCodeFile = true) {
            if($loadCodeFile && !$this->checkCodeFile())
                return false;
            
            $this->ppid = posix_getpid();
            $this->pid = pcntl_fork();
           
            if($this->pid == -1)                // On error
                return false;
            else if($this->pid) {               // Parent 
                if(($key = array_search($this, self::$threadCache)) !== false)
                    unset(self::$threadCache[$key]);
                self::$threadCache[$this->pid] = $this;
                $this->running = true;
                return true;
            } else {                            // Child
                pcntl_sigprocmask(\SIG_SETMASK, array());
                $this->running = true;
                $this->pid = posix_getpid();
                global $Pancake_currentThread;
                $Pancake_currentThread = $this;
                dt_set_proctitle('Pancake '.$this->friendlyName);
                dt_remove_function('dt_set_proctitle');
                if(!$loadCodeFile)
                    return true;
                require $this->codeFile;

                if($this->doGracefulExit)
                	// I know that this is very ugly but Zend won't show memory leak info when using exit;
                	return "THREAD_EXIT";
                else
                	exit;
            }
        }

        /**
        * Stops the Thread with SIGTERM, may stay alive in some cases
        * 
        * @return bool
        */
        public function stop() {
            if(!posix_kill($this->pid, \SIGTERM))
                return false;
            $this->running = false;
            return true;
        }
        
        /**
        * Kills the Thread with SIGKILL, Thread can't resist being killed
        * 
        * @return bool
        */
        public final function kill() {
            if(!posix_kill($this->pid, \SIGKILL))
                return false;
            $this->running = false;
            return true;
        }
        
        /**
        * Sends a signal to a thread, but not SIGTERM or SIGKILL - use stop() or kill() for sending such signals
        * 
        * @param int $signal The signal that should be sent
        * @return bool
        */
        public final function signal($signal) {
            if($signal == \SIGKILL || $signal == \SIGTERM)
                return false;
            if(!posix_kill($this->pid, $signal))
                return false;
            return true;
        }
        
        /**
        * Send a signal to the parent of the thread
        * 
        * @param int $signal The signal to send
        * @return bool
        */
        public final function parentSignal($signal) {
            return posix_kill($this->ppid, $signal);
        }
        
        /**
        * Waits until the Thread exited and kills it if it does not respond in time
        * 
        */
        public final function waitForExit() {
            if(pcntl_waitpid($this->pid, $x, \WNOHANG) === 0) {
                out('Waiting for ' . $this->friendlyName . ' to stop');
                // Sleep up to 2 seconds
                for($i = 0;$i < 200 && pcntl_waitpid($this->pid, $x, \WNOHANG) === 0; $i++)
                    usleep(10000);
                if(pcntl_waitpid($this->pid, $x, \WNOHANG) === 0) {
                    out('Killing worker');
                    $this->kill();
                }
            }
        }       
        
        /**
         * Clears the thread-cache
         * 
         */
        public final static function clearCache() {
        	self::$threadCache = array();
        }
        
        /**
        * Return the worker with the given pid
        * 
        * @param int $pid
        * @return Thread
        */
        public final static function get($pid) {
            return self::$threadCache[$pid];
        }
        
        /**
        * Returns all workers
        * 
        * @return array
        */
        public final static function getAll() {
            return self::$threadCache;
        }
    }
?>

<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* thread.class.php                                             */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
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
                        
        public function start($loadCodeFile = true) {            
            $this->ppid = posix_getpid();
            $this->pid = Fork();
           
            if($this->pid == -1)                // Error
                return false;
            else if($this->pid) {               // Parent 
                if(($key = array_search($this, self::$threadCache)) !== false)
                    unset(self::$threadCache[$key]);
                
                self::$threadCache[$this->pid] = $this;
                $this->running = true;
                
                out('PID of ' . $this->friendlyName . ': ' . $this->pid, OUTPUT_SYSTEM | OUTPUT_DEBUG);
                return true;
            } else {                            // Child
                global $Pancake_currentThread;
                $Pancake_currentThread = $this;

                SigProcMask(\SIG_SETMASK, array());
                $this->running = true;
                $this->pid = posix_getpid();

                if(PHP_MINOR_VERSION == 5)
                    cli_set_process_title('Pancake ' . $this->friendlyName); 
                else {
                    dt_set_proctitle('Pancake ' . $this->friendlyName);
                    dt_remove_function('dt_set_proctitle');
                }

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
        
        public final function kill() {
            if(!posix_kill($this->pid, \SIGKILL))
                return false;
            $this->running = false;
            return true;
        }

        public final function parentSignal($signal) {
            return posix_kill($this->ppid, $signal);
        }
        
        public final function waitForExit() {
            if(WaitPID($this->pid, $x, \WNOHANG) === 0) {
                out('Waiting for ' . $this->friendlyName . ' to stop');
                
                // Sleep up to 2 seconds
                for($i = 0;$i < 200 && WaitPID($this->pid, $x, \WNOHANG) === 0; $i++)
                    usleep(10000);
                
                if(WaitPID($this->pid, $x, \WNOHANG) === 0) {
                    out('Killing worker');
                    $this->kill();
                }
            }
        }       
        
        public final static function clearCache() {
        	self::$threadCache = array();
        }
        
        public final static function get($pid) {
            return self::$threadCache[$pid];
        }
        
        public final static function getAll() {
            return self::$threadCache;
        }
    }
?>

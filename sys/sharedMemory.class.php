<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* sharedMemory.class.php                                       */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * Shared Memory   
    */
    class Pancake_SharedMemory {
        static private $sharedMemory = null;
        
        /**
        * Creates SharedMemory-resource
        * 
        */
        static public function create() {
            // Create temporary file
            $tempFile = tempnam(Pancake_Config::get('main.sysvpath'), 'SHMEM');
            
            // Get filetoken for temporary file and attach Shared Memory
            self::$sharedMemory = shm_attach(ftok($tempFile, 'p'), 10000000);
        }
        
        /**
        * Destroys the SharedMemory-resource
        * 
        */
        static public function destroy() {
            return shm_remove(self::$sharedMemory);
        }
        
        /**
        * Adds a variable to Shared Memory
        * 
        * @param mixed $variable
        * @param int $key Key for variable in Shared Memory
        */
        static public function put($variable, $key = null) {
            // Set key if not given - Limit mt_rand() to 99 999 999 in order not to exhaust PHP_INT_MAX
            if(!$key)
                $key = mt_rand(0, 99999999) . time();
            $key = (int) $key;      
            
            // Add variable to Shared Memory
            if(!shm_put_var(self::$sharedMemory, $key, $variable)) {
                trigger_error('Failed to put variable in Shared Memory', E_USER_WARNING);
                return false;
            }
            return $key;
        }
        
        /**
        * Gets a variable from Shared Memory
        * 
        * @param int $key Key of the variable
        */
        static public function get($key) {
            return shm_get_var(self::$sharedMemory, (int) $key);
        }
        
        /**
        * Deleted a variable from Shared Memory
        * 
        * @param int $key Key of the variable
        */
        static public function delete($key) {
            return shm_remove_var(self::$sharedMemory, (int) $key);
        }
    }
?>

<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* sharedMemory.class.php                                       */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    /**
    * Shared Memory   
    */
    class SharedMemory {
        static private $sharedMemory = null;
        static private $tempFile = null;
        
        /**
        * Creates SharedMemory-resource
        * 
        */
        static public function create() {
            // Create temporary file
            self::$tempFile = tempnam(Config::get('main.tmppath'), 'SHMEM');
            
            // Get filetoken for temporary file and attach Shared Memory
            self::$sharedMemory = shm_attach(ftok(self::$tempFile, 'p'), Config::get('main.sharedmemory'));
        }
        
        /**
        * Destroys the SharedMemory-resource
        * 
        */
        static public function destroy() {
            unlink(self::$tempFile);
            return shm_remove(self::$sharedMemory);
        }
        
        /**
        * Adds a variable to Shared Memory
        * 
        * @param mixed $variable
        * @param int $key Key for variable in Shared Memory
        */
        static public function put($variable, $key = null) {
            if(!$key)
                $key = uniqid();
            $key = (int) $key;      
            
            // Add variable to Shared Memory
            if(!shm_put_var(self::$sharedMemory, $key, $variable)) {
                trigger_error('Failed to put variable in Shared Memory', E_USER_WARNING);
                return false;
            }
            return $key;
        }
        
        // I'm setting the return value to HTTPRequest only to comfort my IDE
        /**
        * Gets a variable from Shared Memory
        * 
        * @param int $key Key of the variable
        * 
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

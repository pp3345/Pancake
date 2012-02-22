<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* IPC.class.php                                                */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
  
    /**
    * InterProcess Communication for Pancake
    */
    class Pancake_IPC {
        static private $IPC = null;
        
        /**
        * Create IPC-resource
        * 
        */
        static public function create() {
            // Create temporary file 
            $tempFile = tempnam(Pancake_Config::get('main.tmppath'), 'IPC');
            
            // Create resource
            self::$IPC = msg_get_queue(ftok($tempFile, 'p'));
        }
        
        /**
        * Destroys the IPC-resource
        * 
        */
        static public function destroy() {
            return msg_remove_queue(self::$IPC);
        }
        
        /**
        * Send information to a Pancake-Thread
        * 
        * @param int $to ID of the recipient
        * @param mixed $message The message to send
        */
        static public function send($to, $message) {
            return msg_send(self::$IPC, $to, $message);
        }
        
        /**
        * Gets a message from the IPC as soon as available
        * 
        */
        static public function get() {
            global $currentThread;
            if(!$currentThread)
                return false;
            if(!msg_receive(self::$IPC, $currentThread->IPCid, $msgtype, 10000, $message))
                return false;
            return $message;
        }
    }
?>

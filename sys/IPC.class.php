<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* IPC.class.php                                                */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
  
    /**
    * InterProcess Communication for Pancake
    */
    class IPC {
        static private $IPC = null;
        
        /**
        * Create IPC-resource
        * 
        */
        static public function create() {
            // Create temporary file 
            $tempFile = tempnam(Config::get('main.tmppath'), 'IPC');
            
            // Create resource
            self::$IPC = msg_get_queue(ftok($tempFile, 'p'));
        }
        
        static public function getResource() {
            return self::$IPC;
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
        * Returns the status of the IPC
        * 
        */
        static public function status() {
            return msg_stat_queue(self::$IPC);
        }
        
        /**
        * Gets a message from the IPC as soon as available
        * 
        */
        static public function get($flags = null, $to = null) {
            global $Pancake_currentThread;
            if(!$Pancake_currentThread && !$to)
                return false;
            if(!msg_receive(self::$IPC, $to ? $to : $Pancake_currentThread->IPCid, $msgtype, 1000000, $message, true, $flags))
                return false;
            return $message;
        }
    }
?>

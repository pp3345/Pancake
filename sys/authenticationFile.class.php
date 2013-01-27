<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* authenticationFile.class.php                                 */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
	#.if 0
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    #.endif
        
    /**
    * Represents a file with authentication infos
    */
    class authenticationFile {
        private $filePath = null;
        private $users = array();
        private static $files = array();
        
        /**
        * Loads an authentication file
        * 
        * @param string $filePath Path to the file
        * @return authenticationFile
        */
        private function __construct($filePath) {
            $this->filePath = $filePath;
            
            if(!is_readable($this->filePath)) {
                trigger_error('Cannot access authentication file "'.$this->filePath.'"', /* .constant 'E_USER_WARNING' */);
                return;
            }
            
            // Read data from file
            $data = file_get_contents($filePath);
            
            // Split lines
            $lines = explode("\n", str_replace("\r\n", "\n", $data));
            
            foreach($lines as $line) {
                $line = trim($line);
                $user = explode(':', $line, 2);
                $this->users[$user[0]] = $user[1];
            }
        } 
        
        /**
        * Checks whether a combination of a username and a password is valid
        * 
        * @param string $user Username
        * @param string $password Password
        * @return bool
        */
        public function isValid($user, $password) {
            return isset($this->users[$user]) && $this->users[$user] == $password;
        }
        
        /*public function isValidDigest($user, $clientResponse, $nonce, $nonceCount, $uri, $realm) {
            
        }*/
        
        /**
        * Returns the instance of an authentication file for a given filepath
        * 
        * @param string $fileName Path to the authentication file
        * @return authenticationFile
        */
        static public function get($fileName) {
            if(!isset(self::$files[$fileName]))
                self::$files[$fileName] = new authenticationFile($fileName);
            return self::$files[$fileName];
        }
    } 
?>

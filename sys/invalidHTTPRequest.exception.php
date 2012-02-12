<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* invalidHTTPRequest.exception.php                             */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    class Pancake_InvalidHTTPRequestException extends Exception {
        private $header = null;
        
        public function __construct($message, $header) {
            $this->message = $message;
            $this->header = $header;
        }
        
        public function getHeader() {
            return $this->header;
        }
    }
?>

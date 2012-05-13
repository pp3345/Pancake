<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* invalidHTTPRequest.exception.php                             */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
        
    class invalidHTTPRequestException extends \Exception {
        private $header = null;
        
        public function __construct($message, $code, $header = null) {
            $this->message = $message;
            $this->code = $code;
            $this->header = $header;
        }
        
        public function getHeader() {
            return $this->header;
        }
    }
?>

<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* invalidHTTPRequest.exception.php                             */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
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

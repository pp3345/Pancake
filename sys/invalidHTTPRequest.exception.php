<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* invalidHTTPRequest.exception.php                             */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
	#.if 0
    namespace Pancake;

    if(PANCAKE !== true)
        exit;
    #.endif
        
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

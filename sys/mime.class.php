<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* mime.class.php                                               */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    class Pancake_MIME {
        static private $mimeByExt = array();
        static private $mime = array();
        const DEFAULT_MIMETYPE = 'text/plain';
        
        /**
        * Returns the MIME-type of a file
        * 
        * @param string $filePath Path to the file
        */
        public static function typeOf($filePath) {
            $fileName = basename($filePath);
            $ext = explode('.', $fileName);
            if(!isset($ext[1]))
                return self::DEFAULT_MIMETYPE;
            $ext = strtolower($ext[count($ext)-1]);
            if(!isset(self::$mimeByExt[$ext]))
                return self::DEFAULT_MIMETYPE;
            return self::$mimeByExt[$ext];
        }
        
        public static function load() {
            $mime = Pancake_Config::get('mime');
                
            foreach($mime as $mimeConf) {
                foreach($mimeConf as $index => $value) {
                    foreach($value as $ext)
                        self::$mimeByExt[$ext] = $index;
                }
            }
        }
    }
?>

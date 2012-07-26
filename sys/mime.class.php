<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* mime.class.php                                               */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
        
    class MIME {
        static private $mimeByExt = array();
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
            $mime = Config::get('mime');
                
            foreach($mime as $type => $exts)
                foreach($exts as $ext)
                    self::$mimeByExt[$ext] = $type;
        }
    }
?>

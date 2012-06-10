<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* util.php                                                     */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    function PHPExitHandler($exitmsg = null) {
        echo $exitmsg;
        return !defined('PANCAKE_PHP');
    }
    
    function PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0) {
        if(!(error_reporting() & $errtype) || !error_reporting())
            return true;
        
        $typeNames = array( \E_ERROR => 'Fatal error',
                            \E_WARNING => 'Warning',
                            \E_PARSE => 'Parse error',
                            \E_NOTICE => 'Notice',
                            \E_CORE_ERROR => 'PHP Fatal error', 
                            \E_CORE_WARNING => 'PHP Warning',
                            \E_COMPILE_ERROR => 'PHP Fatal error',
                            \E_COMPILE_WARNING => 'PHP Warning',
                            \E_USER_ERROR => 'Fatal error',
                            \E_USER_WARNING => 'Warning',
                            \E_USER_NOTICE => 'Notice',
                            \E_STRICT => 'Strict Standards',
                            \E_RECOVERABLE_ERROR => 'Catchable fatal error',
                            \E_DEPRECATED => 'Deprecated',
                            \E_USER_DEPRECATED => 'Deprecated');
        
        $message = $typeNames[$errtype].':  '.$errstr.' in '.$errfile .' on line '.$errline."\n";
        if(ini_get('display_errors'))
            echo $message;
        
        return true;
    }
    
    /**
    * Recursive CodeCache-build
    * 
    * @param vHost $vHost
    * @param string $fileName Filename, relative to the vHosts DocumentRoot
    */
    function cacheFile(vHost $vHost, $fileName) {
        global $Pancake_cacheFiles;
        if($vHost->isExcludedFile($fileName))
            return;
        if(is_dir($vHost->getDocumentRoot() . $fileName)) {
            //out('Scanning directory ' . $vHost->getDocumentRoot() . '/' . $fileName);
            $directory = scandir($vHost->getDocumentRoot() . $fileName);
            if(substr($fileName, -1, 1) != '/')
                $fileName .= '/';
            foreach($directory as $file)
                if($file != '..' && $file != '.')
                    cacheFile($vHost, $fileName . $file);
        } else {
            if(MIME::typeOf($vHost->getDocumentRoot() . '/' . $fileName) != 'text/x-php')
                return;
            //out('Caching file ' . $vHost->getDocumentRoot() . '/' . $fileName);
            $Pancake_cacheFiles[] = $vHost->getDocumentRoot() . $fileName;
        }
    }
    
    /**
    * A function that does the work on debug_backtrace()
    * 
    * @param array $backtrace Backtrace as returned by PHP's debug_backtrace()
    * @return array Modified Backtrace
    */
    function workBacktrace($backtrace) {
        unset($backtrace[count($backtrace)-1]);
        unset($backtrace[count($backtrace)-1]);
        unset($backtrace[0]);
        foreach($backtrace as $index => $tracePart)
            $newBacktrace[] = $tracePart;
        return $newBacktrace;
    }
?>

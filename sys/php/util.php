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
    
    function PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0, $errcontext = array()) {
    	if(vars::$errorHandler && vars::$errorHandler['for'] & $errtype && is_callable(vars::$errorHandler['call']) && call_user_func(vars::$errorHandler['call'], $errtype, $errstr, $errfile, $errline, $errcontext) !== false)
    		return true;
    	
    	vars::$lastError = array('type' => $errtype, 'message' => $errstr, 'file' => $errfile, 'line' => $errline);
    	
        if(!(error_reporting() & $errtype) || !error_reporting() || !ini_get('display_errors'))
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
        
        if(vars::$Pancake_currentThread->vHost->useHTMLErrors())
       		echo "<br />" . "\r\n" . "<b>" . $typeNames[$errtype] . "</b>:  " . $errstr . " in <b>" . $errfile . "</b> on line <b>" . $errline . "</b><br />" . "\r\n";
        else
        	echo $typeNames[$errtype].': '.$errstr.' in '.$errfile .' on line '.$errline."\n";
        
        return true;
    }
    
    function PHPShutdownHandler() {
    	if(!defined('PANCAKE_PHP'))
    		return;
    	
    	// Execute registered shutdown callbacks
    	foreach((array) vars::$Pancake_shutdownCalls as $shutdownCall) {
    		unset($args);
    		$call = 'call_user_func($shutdownCall["callback"]';
    		foreach((array) @$shutdownCall['args'] as $arg) {
    			if($args)
    				$call .= ',';
    			$args[$i++] = $arg;
    			$call .= '$args['.$i.']';
    		}
    		$call .= ');';
    		eval($call);
    	}
    	
    	while(PHPFunctions\OutputBuffering\getLevel() > 1)
    		PHPFunctions\OutputBuffering\endFlush();
    	vars::$Pancake_request->setAnswerBody(ob_get_contents());
    	
    	$data = serialize(vars::$Pancake_request);
    	$len = dechex(strlen($data));
    	while(strlen($len) < 8)
    		$len = "0" . $len;
    	
    	socket_write(vars::$requestSocket, $len);
    	socket_write(vars::$requestSocket, $data);
    	
    	PHPFunctions\OutputBuffering\endClean();
    	
    	IPC::send(9999, 1);
    }
    
    function PHPDisabledFunction($functionName) {
    	if(PHP_MINOR_VERSION == 3 && PHP_RELEASE_VERSION < 6)
    		$backtrace = debug_backtrace();
    	else
    		$backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    	
    	PHPErrorHandler(\E_WARNING, $functionName . '() has been disabled for security reasons', $backtrace[1]["file"], $backtrace[1]["line"]);
    	
    	return null;
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
            $directory = scandir($vHost->getDocumentRoot() . $fileName);
            if(substr($fileName, -1, 1) != '/')
                $fileName .= '/';
            foreach($directory as $file)
                if($file != '..' && $file != '.')
                    cacheFile($vHost, $fileName . $file);
        } else {
            if(MIME::typeOf($vHost->getDocumentRoot() . '/' . $fileName) != 'text/x-php')
                return;
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
    
    /**
    * All Pancake PHP executor variables will be stored in this class
    */
    class vars {
    	/**
    	 * 
    	 * @var HTTPRequest
    	 */
        public static $Pancake_request = null;
        /**
         * 
         * @var PHPWorker
         */
        public static $Pancake_currentThread = null;
        public static $Pancake_constsPre = array();
        public static $Pancake_funcsPre = array();
        public static $Pancake_includesPre = array();
        public static $Pancake_classesPre = array();
        public static $Pancake_interfacesPre = array();
        public static $Pancake_traitsPre = array();
        public static $Pancake_exclude = array();
        public static $Pancake_vHosts = array();
        public static $Pancake_processedRequests = 0;
        public static $Pancake_headerCallbacks = array();
        public static $Pancake_shutdownCalls = array();
        public static $errorHandler = null;
        public static $errorHandlerHistory = array();
        public static $workerExit = false;
        public static $requestSocket = null;
        public static $lastError = null;
        public static $invalidRequest = false;
        public static $executedShutdown = false;
    }
?>

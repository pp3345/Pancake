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
    	if(!is_int($exitmsg))
        	echo $exitmsg;
        return !defined('PANCAKE_PHP');
    }
    
    function PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0, $errcontext = array()) {
    	$nonUserHandlableErrors = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING | \E_COMPILE_ERROR | \E_COMPILE_WARNING;
    	
    	vars::$executingErrorHandler = true;
    	if(vars::$errorHandler && vars::$errorHandler['for'] & $errtype && !($errtype & $nonUserHandlableErrors) && is_callable(vars::$errorHandler['call']) && call_user_func(vars::$errorHandler['call'], $errtype, $errstr, $errfile, $errline, $errcontext) !== false)
    		return true;
    	
    	vars::$executingErrorHandler = false;
    	
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
    		
    		$i = 0;
    		
    		foreach((array) @$shutdownCall['args'] as $arg) {
    			if(isset($args))
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
    	
    	if(session_id() || vars::$sessionID) {
    		vars::$Pancake_request->setCookie(session_name(), session_id() ? session_id() : vars::$sessionID, time() + ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
    		session_write_close();
    		 
    		switch(session_cache_limiter()) {
    			case 'nocache':
    				vars::$Pancake_request->setHeader('Expires', 'Thu, 19 Nov 1981 08:52:00 GMT');
    				vars::$Pancake_request->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    				vars::$Pancake_request->setHeader('Pragma', 'no-cache');
    				break;
    			case 'private':
    				vars::$Pancake_request->setHeader('Expires', 'Thu, 19 Nov 1981 08:52:00 GMT');
    				vars::$Pancake_request->setHeader('Cache-Control', 'private, max-age=' . ini_get('session.cache_expire') . ', pre-check=' . ini_get('session.cache_expire'));
    				vars::$Pancake_request->setHeader('Last-Modified', date('r'));
    				break;
    			case 'private_no_expire':
    				vars::$Pancake_request->setHeader('Cache-Control', 'private, max-age=' . ini_get('session.cache_expire') . ', pre-check=' . ini_get('session.cache_expire'));
    				vars::$Pancake_request->setHeader('Last-Modified', date('r'));
    				break;
    			case 'public':
    				vars::$Pancake_request->setHeader('Expires', date('r', time() + ini_get('session.cache_expire')));
    				vars::$Pancake_request->setHeader('Cache-Control', 'public, max-age=' . ini_get('session.cache_expire'));
    				vars::$Pancake_request->setHeader('Last-Modified', date('r'));
    				break;
    		}
    	}
    	
    	$data = serialize(vars::$Pancake_request);
    	
    	$packages = array();
        
      	if(strlen($data) > (socket_get_option(vars::$requestSocket, \SOL_SOCKET, \SO_SNDBUF) - 1024)
      	&& (socket_set_option(vars::$requestSocket, \SOL_SOCKET, \SO_SNDBUF, strlen($data) + 1024) + 1)
        && strlen($data) > (socket_get_option(vars::$requestSocket, \SOL_SOCKET, \SO_SNDBUF) - 1024)) {
      		$packageSize = socket_get_option(vars::$requestSocket, \SOL_SOCKET, \SO_SNDBUF) - 1024;
      		
      		for($i = 0;$i < ceil($data / $packageSize);$i++)
      			$packages[] = substr($data, $i * $packageSize, $i * $packageSize + $packageSize);
      	} else
      		$packages[] = $data;
        
        // First transmit the length of the serialized object, then the object itself
        socket_write(vars::$requestSocket, dechex(count($packages)));
        socket_write(vars::$requestSocket, dechex(strlen($packages[0])));
        foreach($packages as $data)
        	socket_write(vars::$requestSocket, $data);
        
    	IPC::send(9999, 1);
    }
    
    function PHPDisabledFunction($functionName) {
    	if(\PHP_MINOR_VERSION == 3 && \PHP_RELEASE_VERSION < 6)
    		$backtrace = debug_backtrace();
    	else
    		$backtrace = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    	
    	PHPErrorHandler(\E_WARNING, $functionName . '() has been disabled for security reasons', $backtrace[1]["file"], $backtrace[1]["line"]);
    	
    	return null;
    }
    
    /**
    * Recursive CodeCache-build
    * 
    * @param vHost $vHost
    * @param string $fileName Filename, relative to the vHosts document root
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
            if(MIME::typeOf($vHost->getDocumentRoot() . $fileName) != 'text/x-php')
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
        
        foreach($backtrace as $index => $tracePart) {
			if(vars::$executingErrorHandler && ((isset($tracePart['file']) && strpos($tracePart['file'], '/sys/php/util.php')) || $tracePart['function'] == 'Pancake\PHPErrorHandler'))
				continue;
        	$newBacktrace[] = $tracePart;
        }
        return $newBacktrace;
    }
    
    /**
     * Removes all objects stored in an array
     * 
     * @param array $data
     * @return array
     */
    function recursiveClearObjects($data, $objects = array()) {
    	if(is_object($data)) {
    		$reflect = new \ReflectionObject($data);
    		$objects[] = $data;
    		
    		foreach($reflect->getProperties() as $property) {
    			$property->setAccessible(true);
    			
	    		if((is_object($property->getValue($data)) && !in_array($property->getValue($data), $objects, true)) || is_array($data))
			    	// Search for objects in the object's properties
			    	$property->setValue($data, recursiveClearObjects($property->getValue($data), $objects));
    		}
    		
    		// Destroy object
    		$data = null;

    		gc_collect_cycles();
    	} else if(is_array($data)) {
	    	foreach($data as $index => &$val) {
	    		if((is_array($val) || is_object($val)) && !($val = recursiveClearObjects($val)))
	    			unset($data[$index]);
	    	}
    	}
    	
    	return $data;
    }
    
    /**
    * All Pancake PHP executor variables are stored in this class
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
        public static $classes = array();
        public static $executingErrorHandler = false;
        public static $sessionID = null;
        public static $resetSessionSaveHandler = false;
    }
    
?>

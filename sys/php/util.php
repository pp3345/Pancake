<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* util.php                                                     */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
	#.if 0
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    #.endif
    
    function PHPExitHandler($exitmsg = null) {
    	if(!is_int($exitmsg))
        	echo $exitmsg;
        return !defined('PANCAKE_PHP');
    }
    
    function PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0, $errcontext = array()) {
    	if(vars::$errorHandler 
    	&& vars::$errorHandler['for'] & $errtype 
    	&& !($errtype & /* .eval 'return \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING | \E_COMPILE_ERROR | \E_COMPILE_WARNING;' */) 
    	&& is_callable(vars::$errorHandler['call']) 
    	&& vars::$executingErrorHandler = true 
    	&& call_user_func(vars::$errorHandler['call'], $errtype, $errstr, $errfile, $errline, $errcontext) !== false) {
    		vars::$executingErrorHandler = false;
    		vars::$lastError = array('type' => $errtype, 'message' => $errstr, 'file' => $errfile, 'line' => $errline);
    		return true;
    	}
    	
    	vars::$executingErrorHandler = false;
    	
    	vars::$lastError = array('type' => $errtype, 'message' => $errstr, 'file' => $errfile, 'line' => $errline);
    	
        if(!(error_reporting() & $errtype) || !error_reporting() || !ini_get('display_errors'))
            return true;
        
        $typeNames = array( /* .constant 'E_ERROR' */ => 'Fatal error',
                            /* .constant 'E_WARNING' */ => 'Warning',
                            /* .constant 'E_PARSE' */ => 'Parse error',
                            /* .constant 'E_NOTICE' */ => 'Notice',
                            /* .constant 'E_CORE_ERROR' */ => 'PHP Fatal error', 
                            /* .constant 'E_CORE_WARNING' */ => 'PHP Warning',
                            /* .constant 'E_COMPILE_ERROR' */ => 'PHP Fatal error',
                            /* .constant 'E_COMPILE_WARNING' */ => 'PHP Warning',
                            /* .constant 'E_USER_ERROR' */ => 'Fatal error',
                            /* .constant 'E_USER_WARNING' */ => 'Warning',
                            /* .constant 'E_USER_NOTICE' */ => 'Notice',
                            /* .constant 'E_STRICT' */ => 'Strict Standards',
                            /* .constant 'E_RECOVERABLE_ERROR' */ => 'Catchable fatal error',
                            /* .constant 'E_DEPRECATED' */ => 'Deprecated',
                            /* .constant 'E_USER_DEPRECATED' */ => 'Deprecated');
        
        #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpHTMLErrors;' */
       		echo "<br />" . "\r\n" . "<b>" . $typeNames[$errtype] . "</b>:  " . $errstr . " in <b>" . $errfile . "</b> on line <b>" . $errline . "</b><br />" . "\r\n";
        #.else
        	echo $typeNames[$errtype].': '.$errstr.' in '.$errfile .' on line '.$errline."\n";
       	#.endif
        
        return true;
    }
    
    function PHPShutdownHandler() {
    	if(!defined('PANCAKE_PHP'))
    		return;
    	
    	// Execute registered shutdown callbacks
    	foreach((array) vars::$Pancake_shutdownCalls as $shutdownCall)
    		call_user_func_array($shutdownCall["callback"], $shutdownCall["args"]);
    	
    	while(PHPFunctions\OutputBuffering\getLevel() > 1)
    		PHPFunctions\OutputBuffering\endFlush();
    	vars::$Pancake_request->answerBody = ob_get_contents();
    	
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
        
      	if(strlen($data) > (socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024)
      	&& (socket_set_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */, strlen($data) + 1024) + 1)
        && strlen($data) > (socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024)) {
      		$packageSize = socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024;
      		
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
    	#.if PHP_MINOR_VERSION == 3 && PHP_RELEASE_VERSION < 6
    		$backtrace = debug_backtrace();
    	#.else
    		$backtrace = debug_backtrace(/* .constant 'DEBUG_BACKTRACE_PROVIDE_OBJECT' */, 2);
    	#.endif
    	
    	PHPErrorHandler(/* .constant 'E_WARNING' */, $functionName . '() has been disabled for security reasons', $backtrace[1]["file"], $backtrace[1]["line"]);
    	
    	return null;
    }
    
    /**
    * Recursive CodeCache-build
    * 
    * @param string $fileName Filename, relative to the vHosts document root
    */
    function cacheFile($fileName) {
        global $Pancake_cacheFiles;
        #.if /* .eval 'global $Pancake_vHost; return (bool) $Pancake_vHosts->phpCodeCacheExcludes;' */
        if(isset(vars::$Pancake_currentThread->vHost->phpCodeCacheExcludes[$fileName]))
            return;
        #.endif
        if(is_dir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . $fileName)) {
            $directory = scandir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . $fileName);
            if(substr($fileName, -1, 1) != '/')
                $fileName .= '/';
            foreach($directory as $file)
                if($file != '..' && $file != '.')
                    cacheFile($fileName . $file);
        } else {
            if(MIME::typeOf(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . $fileName) != 'text/x-php')
                return;
            $Pancake_cacheFiles[] = /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . $fileName;
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
        
        $newBacktrace = array();
        
        foreach($backtrace as $tracePart) {
			if(vars::$executingErrorHandler && ((isset($tracePart['file']) && strpos($tracePart['file'], '/sys/threads/single/phpWorker.thread')) || (isset($tracePart['function']) && $tracePart['function'] == 'Pancake\PHPErrorHandler')))
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
    		
    		#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetObjectsDestroyDestructor;' */
    			if($reflect->hasMethod('__destruct')) {
	    			global $destroyedDestructors;
	
	    			$name = 'Pancake_DestroyedDestructor' . mt_rand();
	    			dt_rename_method($reflect->getName(), '__destruct', $name);
	    			$destroyedDestructors[$reflect->getName()] = $name;
	    		}
	    	#.endif
    		
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
        #.if PHP_MINOR_VERSION >= 4
        public static $Pancake_traitsPre = array();
        #.endif
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
        public static $functions = array();
        public static $executingErrorHandler = false;
        public static $sessionID = null;
        public static $resetSessionSaveHandler = false;
    }
    
?>

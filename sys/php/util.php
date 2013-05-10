<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* util.php                                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

	#.if 0
    namespace Pancake;

    if(PANCAKE !== true)
        exit;
    #.endif

    function PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0, $errcontext = array()) {
    	if(vars::$errorHandler
    	&& vars::$errorHandler['for'] & $errtype
    	&& !($errtype & /* .eval 'return \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING | \E_COMPILE_ERROR | \E_COMPILE_WARNING;' */)
    	&& is_callable(vars::$errorHandler['call'])
    	&& (vars::$executingErrorHandler = true)
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

       	// Abort script execution on E_USER_ERROR
       	if($errtype == /* .constant 'E_USER_ERROR' */)
            exit(255);

        return true;
    }

    #.ifdef 'SUPPORT_CODECACHE'
    /**
    * Recursive CodeCache-build
    *
    * @param string $fileName Filename, relative to the vHosts document root
    */
    function cacheFile($fileName) {
        global $Pancake_cacheFiles;
        #.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpCodeCacheExcludes;'
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
    #.endif

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
			if(vars::$executingErrorHandler && ((isset($tracePart['file']) && strpos($tracePart['file'], '/sys/compilecache/phpWorker.thread')) || (isset($tracePart['function']) && $tracePart['function'] == 'Pancake\PHPErrorHandler')))
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
    function recursiveClearObjects($data) {
    	if(is_object($data)) {
    		#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetObjectsDestroyDestructor;'
    			if(method_exists($data, '__destruct')) {
	    			global $destroyedDestructors;

	    			$name = 'Pancake_DestroyedDestructor' . mt_rand();
	    			dt_rename_method(get_class($data), '__destruct', $name);
	    			$destroyedDestructors[get_class($data)] = $name;
	    		}
	    	#.endif

    		$data = null;
    	} else if(is_array($data)) {
	    	foreach($data as $index => &$val) {
	    	    if(is_object($val))
                    unset($data[$index]);
                else if(is_array($val) && !($val = recursiveClearObjects($val)))
	    			unset($data[$index]);
	    	}
            
            if(!$data)
                $data = null;
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
        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CONSTANTS'
        public static $Pancake_constsPre = array();
        #.endif
        #.if Pancake\DEBUG_MODE
        public static $Pancake_funcsPre = array();
        #.endif
        #.if Pancake\DEBUG_MODE
        public static $Pancake_includesPre = array();
        #.endif
        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CLASSES'
        public static $Pancake_classesPre = array();
        #.endif
        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_INTERFACES'
        public static $Pancake_interfacesPre = array();
        #.endif
    	#.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_TRAITS'
    	public static $Pancake_traitsPre = array();
    	#.endif
        #.ifdef 'SUPPORT_CODECACHE'
        public static $Pancake_exclude = array();
        #.endif
        #.ifdef 'HAVE_LIMIT'
        public static $Pancake_processedRequests = 0;
        #.endif
        public static $workerExit = false;
        public static $requestSocket = null;
        public static $invalidRequest = false;
        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
        public static $classes = array();
        #.endif
        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
        public static $functions = array();
        #.endif
        public static $executingErrorHandler = false;
        public static $tickFunctions = array();
        public static $listenArray = array();
        public static $listenArrayOrig = array();
    }

?>

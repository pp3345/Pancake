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
        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
        public static $classes = array();
        #.endif
        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
        public static $functions = array();
        #.endif
    }

?>

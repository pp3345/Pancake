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
    }

?>

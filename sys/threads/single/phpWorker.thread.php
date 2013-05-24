<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.thread.php                                         */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

	#.define 'PHPWORKER' true

	#.config 'autosubstitutesymbols' false
	#.config 'compressvariables' false
	#.config 'compressproperties' false

	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpCodeCache;' false
		#.define 'SUPPORT_CODECACHE' true
	#.endif

	#.if #.extension_loaded 'filter'
	   #.HAVE_FILTER_EXTENSION = true
	#.endif
		
	#.SAPI_ERROR_REPORTING = #.Pancake\ORIGINAL_ERROR_REPORTING
	
	#.longDefine 'EVAL_CODE'
	global $Pancake_currentThread;
	if(isset($Pancake_currentThread->vHost->phpINISettings["session.name"]))
		ini_set("session.name", $Pancake_currentThread->vHost->phpINISettings["session.name"]);
    if(isset($Pancake_currentThread->vHost->phpINISettings["error_reporting"]))
        ini_set("error_reporting", defined($Pancake_currentThread->vHost->phpINISettings["error_reporting"])
                                                        ? constant($Pancake_currentThread->vHost->phpINISettings["error_reporting"])
                                                        : $Pancake_currentThread->vHost->phpINISettings["error_reporting"]);
    if(isset($Pancake_currentThread->vHost->phpINISettings["expose_php"]))
        ini_set("expose_php", $Pancake_currentThread->vHost->phpINISettings["expose_php"]);

	return (bool) $Pancake_currentThread->vHost->phpINISettings;
	#.endLongDefine

	#.if #.eval EVAL_CODE false
		#.HAVE_INI_SETTINGS = true
	#.endif
	
	#.longDefine 'EVAL_CODE'
	global $Pancake_currentThread;
    if(isset($Pancake_currentThread->vHost->phpINISettings["error_reporting"]))
        return ini_get('error_reporting');
    return \Pancake\ORIGINAL_ERROR_REPORTING;
	#.endLongDefine
	
	#.SAPI_ERROR_REPORTING = #.eval EVAL_CODE false
	
	#.longDefine 'EVAL_CODE'
	global $Pancake_currentThread;
    return (bool) $Pancake_currentThread->vHost->phpModules;
	#.endLongDefine
	
	#.if #.eval EVAL_CODE false
	   #.HAVE_PHP_MODULES = true
	   
	   #.longDefine 'EVAL_CODE'
	   global $Pancake_currentThread;
       
       if(in_array("filter", $Pancake_currentThread->vHost->phpModules))
            return true;
       return false;
	   #.endLongDefine
	   
	   #.if #.eval EVAL_CODE false
	       #.HAVE_FILTER_EXTENSION = true
	   #.endif
	#.endif

    namespace {
    	#.include 'php/sapi.php'
    }

    namespace Pancake {
    	#.include 'php/util.php'

    	#.include 'workerFunctions.php'
    	#.include 'vHostInterface.class.php'

    	// Clear thread cache
    	Thread::clearCache();

    	$Pancake_currentThread->vHost = new vHostInterface($Pancake_currentThread->vHost);
        Close($Pancake_currentThread->localSocket);
    	setThread($Pancake_currentThread);
	    vars::$Pancake_currentThread = $Pancake_currentThread;
	    unset($Pancake_currentThread);

        foreach($Pancake_sockets as $socket) {
            Close($socket);
        }
	    unset($Pancake_sockets);

	    #.ifdef 'SUPPORT_CODECACHE'
		    // MIME types are only needed for CodeCache
		    MIME::load();
		#.endif

		Config::workerDestroy();

	    // Don't allow scripts to get information about other vHosts
	    foreach($Pancake_vHosts as $vHost) {
	        if($vHost->phpSocket && $vHost->id != vars::$Pancake_currentThread->vHost->id) {
	           Close($vHost->phpSocket);
               $vHost->phpSocket = 0;
            }
        }
	    unset($Pancake_vHosts);

	    // Clean
	    cleanGlobals();

        foreach(get_included_files() as $file) {
            dt_remove_include($file);
        }
        
        unset($file);

	    // Predefine constants
	    #.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->predefinedConstants;' false
		    foreach(vars::$Pancake_currentThread->vHost->predefinedConstants as $name => $value)
		    	define($name, $value, true);
		   	unset($name);
		   	unset($value);
		#.endif

        #.ifdef 'HAVE_PHP_MODULES'
            foreach(vars::$Pancake_currentThread->vHost->phpModules as $name) {
                loadModule($name);
            }
            
            unset($name);
        #.endif

        #.ifdef 'HAVE_INI_SETTINGS'
            // Set ini settings

            foreach(vars::$Pancake_currentThread->vHost->phpINISettings as $name => $value) {
                ini_set($name, $value);
            }
            
            unset($name);
            unset($value);
        #.endif

        LoadModule('sapi', true);
        
        disableModuleLoader();
        
        // Set exit handler so that Pancake won't die when a script calls exit() oder die()
        dt_exit_mode(/* .constant 'DT_EXIT_EXCEPTION' */, "Pancake\SAPIExitHandler", 'Pancake\ExitException');

        chdir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' false */);

	    #.ifdef 'SUPPORT_CODECACHE'
		    // Get a list of files to cache
		    foreach(vars::$Pancake_currentThread->vHost->phpCodeCache as $cacheFile)
		        cacheFile($cacheFile);

		    SAPICodeCacheJIT();

		    // Load CodeCache
		    foreach($Pancake_cacheFiles as $cacheFile) {
		    	#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' false
	        		set_time_limit(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' false */);
	        	#.endif
		        require_once $cacheFile;
		        set_time_limit(0);
		    }

		    unset($cacheFile);
		    unset($Pancake_cacheFiles);

		    // Delete predefined constants, if wanted
		    #.if #.eval 'global $Pancake_currentThread; return ((bool) $Pancake_currentThread->vHost->predefinedConstants) && $Pancake_currentThread->vHost->deletePredefinedConstantsAfterCodeCacheLoad;' false
		    	foreach((array) vars::$Pancake_currentThread->vHost->predefinedConstants as $name => $value)
		    		dt_remove_constant($name);

		    	unset($name);
		    	unset($value);
		   	#.endif
		   	
		   	SAPICodeCachePrepare();
	   	#.endif

	    // Seed random number generators
	    mt_srand();
        srand();

	    // Ready
	    vars::$Pancake_currentThread->parentSignal(/* .constant 'SIGUSR1' */);

        // Set blocking for signals
        SigProcMask(/* .constant 'SIG_BLOCK' */, array(/* .constant 'SIGINT' */, /* .constant 'SIGHUP' */));

        #.ifdef 'STDOUT'
            dt_remove_constant('STDOUT');
        #.endif
        #.ifdef 'STDIN'
            dt_remove_constant('STDIN');
        #.endif
        #.ifdef 'STDERR'
            dt_remove_constant('STDERR');
        #.endif
        
        // Further prepare the Pancake SAPI module
        SAPIPrepare();

	    // Set user and group
	    setUser();
        
	    // Wait for requests
	    while(vars::$Pancake_request = SAPIWait()) {
	        // Execute script and protect Pancake from exit()
	        try {
	        	include /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' false */ . vars::$Pancake_request->requestFilePath;
	        } catch(ExitException $e) {
	        	unset($e);
	        }
	        
            SAPIFinishRequest();
            
            /* We should set this to null in order to keep the Zend objects store alignment as it is across requests */            
            vars::$Pancake_request = null;
        }
    }
?>
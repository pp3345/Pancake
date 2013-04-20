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

	#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpInfoConfig;' false
		#.define 'EXPOSE_PANCAKE_IN_PHPINFO' true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpInfovHosts;' false
		#.define 'EXPOSE_VHOSTS_IN_PHPINFO' true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpCodeCache;' false
		#.define 'SUPPORT_CODECACHE' true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["functions"]) ? $Pancake_currentThread->vHost->autoDelete["functions"] : true);' false
		#.AUTODELETE_FUNCTIONS = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["classes"]) ? $Pancake_currentThread->vHost->autoDelete["classes"] : true);' false
		#.AUTODELETE_CLASSES = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["traits"]) ? $Pancake_currentThread->vHost->autoDelete["traits"] : true);' false
		#.AUTODELETE_TRAITS = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["interfaces"]) ? $Pancake_currentThread->vHost->autoDelete["interfaces"] : true);' false
		#.AUTODELETE_INTERFACES = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["constants"]) ? $Pancake_currentThread->vHost->autoDelete["constants"] : true);' false
		#.AUTODELETE_CONSTANTS = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["includes"]) ? $Pancake_currentThread->vHost->autoDelete["includes"] : true);' false
		#.AUTODELETE_INCLUDES = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->forceDeletes;' false
		#.HAVE_FORCED_DELETES = true
	#.endif

	#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpWorkerLimit;' false
		#.HAVE_LIMIT = true
	#.endif

	#.if #.extension_loaded 'session'
	   #.HAVE_SESSION_EXTENSION = true
	#.endif

	#.if #.extension_loaded 'filter'
	   #.HAVE_FILTER_EXTENSION = true
	#.endif
		
	#.SAPI_ERROR_REPORTING = #.Pancake\ORIGINAL_ERROR_REPORTING
	
	#.longDefine 'EVAL_CODE'
	global $Pancake_currentThread;
	if(isset($Pancake_currentThread->vHost->phpINISettings["session.name"]))
		\Pancake\PHPFunctions\setINI("session.name", $Pancake_currentThread->vHost->phpINISettings["session.name"]);
    if(isset($Pancake_currentThread->vHost->phpINISettings["error_reporting"]))
        \Pancake\PHPFunctions\setINI("error_reporting", defined($Pancake_currentThread->vHost->phpINISettings["error_reporting"])
                                                        ? constant($Pancake_currentThread->vHost->phpINISettings["error_reporting"])
                                                        : $Pancake_currentThread->vHost->phpINISettings["error_reporting"]);
    if(isset($Pancake_currentThread->vHost->phpINISettings["expose_php"]))
        \Pancake\PHPFunctions\setINI("expose_php", $Pancake_currentThread->vHost->phpINISettings["expose_php"]);

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

	#.if Pancake\DEBUG_MODE === true
		#.define 'BENCHMARK' false
	#.else
		#.define 'BENCHMARK' false
	#.endif

	#.longDefine 'MACRO_CODE'
	$backtrace = debug_backtrace(/* .DEBUG_BACKTRACE_PROVIDE_OBJECT */, 2);

	\Pancake\PHPErrorHandler($errorType, $errorMessage, $backtrace[0]["file"], $backtrace[0]["line"]);
	#.endLongDefine

	#.macro 'PHP_ERROR_WITH_BACKTRACE' MACRO_CODE '$errorType' '$errorMessage'

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
        
        LoadModule('sapi', true);

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

	    get_included_files(true);

	    PHPFunctions\registerShutdownFunction('Pancake\PHPShutdownHandler');

	    dt_remove_function('Pancake\PHPFunctions\registerShutdownFunction');

	    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
		dt_exit_mode(/* .constant 'DT_EXIT_EXCEPTION' */, "Pancake\PHPExitHandler", 'Pancake\ExitException');

	    #.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpDisabledFunctions;' false
		    foreach(vars::$Pancake_currentThread->vHost->phpDisabledFunctions as $function) {
		    	if(function_exists($function)) {
		    		dt_remove_function($function);
		    		eval('function ' . $function . '() { return Pancake\PHPDisabledFunction(__FUNCTION__); }');
		    	}
		    }

		    unset($function);
	    #.endif

	    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
	    	vars::$classes = get_declared_classes();
	    #.endif

	    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
	    	vars::$functions = get_defined_functions()['user'];
	    #.endif

	    chdir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' false */);

	    memory_get_usage(null, true);
	    memory_get_peak_usage(null, true);

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
        
        disableModuleLoader();

		#.ifdef 'HAVE_INI_SETTINGS'
			// Set ini settings

			foreach(vars::$Pancake_currentThread->vHost->phpINISettings as $name => $value) {
				PHPFunctions\setINI($name, $value);
			}
            
            unset($name);
            unset($value);
		#.endif

	    #.ifdef 'SUPPORT_CODECACHE'
		    // Get a list of files to cache
		    foreach(vars::$Pancake_currentThread->vHost->phpCodeCache as $cacheFile)
		        cacheFile($cacheFile);

		    CodeCacheJITGlobals();

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

		    // Get variables to exclude from deletion (probably set by cached files)
		    vars::$Pancake_exclude = cleanGlobals(array(), true);
	   	#.endif

	    // Get currently defined funcs, consts, classes, interfaces, traits and includes
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_FUNCTIONS'
	    vars::$Pancake_funcsPre = get_defined_functions();
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CONSTANTS'
	    vars::$Pancake_constsPre = get_defined_constants(true);
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_INCLUDES'
	    vars::$Pancake_includesPre = get_included_files();
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CLASSES'
	    vars::$Pancake_classesPre = get_declared_classes();
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_INTERFACES'
	    vars::$Pancake_interfacesPre = get_declared_interfaces();
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_TRAITS'
	    	vars::$Pancake_traitsPre = get_declared_traits();
	    #.endif

	    // Seed random number generators
	    mt_srand();
        srand();

	    // Ready
	    vars::$Pancake_currentThread->parentSignal(/* .constant 'SIGUSR1' */);

	    #.if BENCHMARK === true
	    	benchmarkFunction('gc_collect_cycles');
	    	benchmarkFunction('Pancake\cleanGlobals');
	    	benchmarkFunction('Pancake\recursiveClearObjects');
			benchmarkFunction('serialize');
	    #.endif

	    ExecuteJITGlobals();

        vars::$listenArray = vars::$listenArrayOrig = array(vars::$Pancake_currentThread->vHost->phpSocket, vars::$Pancake_currentThread->socket);

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

	    // Set user and group
	    setUser();

	    // Wait for requests
	    while(Select(vars::$listenArray) !== false) {
            if(isset(vars::$listenArray[vars::$Pancake_currentThread->socket])) {
                switch(Read(vars::$Pancake_currentThread->socket, 512)) {
                    case "GRACEFUL_SHUTDOWN":
                        break 2;
                    case "LOAD_FILE_POINTERS":
                        loadFilePointers();
                        goto cycle;
                }
            }

            vars::$requestSocket = Accept(vars::$listenArray[vars::$Pancake_currentThread->vHost->phpSocket]);

	    	// Get request object from RequestWorker
	    	$packages = hexdec(Read(vars::$requestSocket, 8));
	    	$length = hexdec(Read(vars::$requestSocket, 8));

	    	if($packages > 1) {
	    		$sockData = "";

	    		while($packages--)
	    			$sockData .= Read(vars::$requestSocket, $length);

	    		vars::$Pancake_request = unserialize($sockData);
	    		unset($sockData);
	    	} else
	    		vars::$Pancake_request = unserialize(Read(vars::$requestSocket, $length));

	    	unset($length);
	    	unset($packages);
            
            SAPIRequest(vars::$Pancake_request);

#.if #.call 'ini_get' 'expose_php'
            #.PHP_VERSION_STRING = ,"PHP/" PHP_VERSION
            vars::$Pancake_request->setHeader('X-Powered-By', /* .PHP_VERSION_STRING */);
#.endif

            define('PANCAKE_PHP', true);

#.if #.call 'ini_get' 'expose_php'
            // PHP UUIDs
            if(isset(vars::$Pancake_request->getGETParams()[""])) {
                chdir(/* .PANCAKE_PATH */);
                switch(vars::$Pancake_request->getGETParams()[""]) {
                    case 'PHPE9568F34-D428-11d2-A769-00AA001ACF42':
                        $contents = file_get_contents('logo/php.gif');
                        vars::$Pancake_request->setHeader('Content-Type', 'image/gif');
                        goto write;
                    case 'PHPE9568F35-D428-11d2-A769-00AA001ACF42':
                        $contents = file_get_contents('logo/zend.gif');
                        vars::$Pancake_request->setHeader('Content-Type', 'image/gif');
                        goto write;
                    case 'PHPE9568F36-D428-11d2-A769-00AA001ACF42':
                        $contents = file_get_contents('logo/php_egg.gif');
                        vars::$Pancake_request->setHeader('Content-Type', 'image/gif');
                        goto write;
                    case 'PHPB8B5F2A0-3C92-11d3-A3A9-4C7B08C10000':
                        ob_start();
                        phpcredits();
                        vars::$Pancake_request->setHeader('Content-Type', 'text/html');
                        $contents = ob_get_clean();
                        goto write;
#.if #.call 'Pancake\Config::get' 'main.exposepancake'
                    case 'PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B':
                        $contents = file_get_contents('logo/pancake.png');
                        vars::$Pancake_request->setHeader('Content-Type', 'image/png');
                        goto write;
#.endif
                }
            }
#.endif

	        // Change directory to document root of the vHost / requested file path
	        chdir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' false */ . dirname(vars::$Pancake_request->requestFilePath));

	        // Start output buffer
	        ob_start();

	        // Set error handling
	        error_reporting(/* .SAPI_ERROR_REPORTING */);
	        PHPFunctions\setErrorHandler('Pancake\PHPErrorHandler');

	        // Execute script and protect Pancake from exit() and Exceptions
	        try {
	        	#.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' false
	        		set_time_limit(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' false */);
	        	#.endif

	        	include /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' false */ . vars::$Pancake_request->requestFilePath;

	            runShutdown:

	            vars::$executedShutdown = true;

	            // Run header callbacks
	            foreach(vars::$Pancake_headerCallbacks as $callback)
	                call_user_func($callback);

	            // Run Registered Shutdown Functions
	            foreach(vars::$Pancake_shutdownCalls as $shutdownCall)
	            	call_user_func_array($shutdownCall["callback"], $shutdownCall["args"]);

	            goto postShutdown;
	        } catch(ExitException $e) {
	        	unset($e);
	        } catch(\Exception $exception) {
	        	$fatal = false;

	            if(($oldHandler = set_exception_handler('Pancake\dummy')) !== null) {
	            	try {
	                	call_user_func($oldHandler, $exception);
	            	} catch(ExitException $e) {
	            	} catch(\Exception $e) {
	            		$fatal = true;
	            	}
	            } else
	            	$fatal = true;

	            if($fatal) {
	                while(PHPFunctions\OutputBuffering\getLevel() > 1)
	                    PHPFunctions\OutputBuffering\endFlush();
	                if(ini_get('display_errors')) {
	                    $errorText = 'Uncaught exception \'' . get_class($exception) . '\'';
	                    if($exception->getMessage())
	                        $errorText .= ' with message \'' . $exception->getMessage() . '\'';
	                    $errorText .= ' in ' . $exception->getFile() . ':' . $exception->getLine();
	                    $errorText .= "\n";
	                    $errorText .= "Stack trace:";
	                    $errorText .= "\n";
	                    $errorText .= $exception->getTraceAsString();

	                    $errorText .= "\n";
	                    $errorText .= "  thrown";

	                    PHPErrorHandler(/* .constant 'E_ERROR' */, $errorText, $exception->getFile(), $exception->getLine());
	                // Send 500 if no content
	                } else if(!ob_get_contents())
	                    vars::$invalidRequest = true;
	            }
	        }

	        // If a shutdown function throws an exception, it will be executed again, so we must make sure, we only execute the shutdown functions once...
	        if(!vars::$executedShutdown)
	        	goto runShutdown;

	        postShutdown:

	        set_time_limit(0);

	        foreach(vars::$tickFunctions as $tickFunction)
	        	unregister_tick_function($tickFunction);

	        // After $invalidRequest is set to true it might still happen that the registered shutdown functions do some output
	        if(vars::$invalidRequest) {
	        	if(!ob_get_contents()) {
	        	    $requestObject = vars::$Pancake_request;
	        		vars::$Pancake_request->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
	        	} else
	        		vars::$invalidRequest = false;
	        }

	        // Destroy all output buffers
	        while(PHPFunctions\OutputBuffering\getLevel() > 1)
	            PHPFunctions\OutputBuffering\endFlush();

	        // Get contents from output buffer
	        $contents = ob_get_contents();

            #.ifdef 'HAVE_SESSION_EXTENSION'
	        if(session_id() || vars::$sessionID) {
	            vars::$Pancake_request->setCookie(session_name(), session_id() ? session_id() : vars::$sessionID, ini_get('session.cookie_lifetime') ? time() + ini_get('session.cookie_lifetime') : 0, ini_get('session.cookie_path'), ini_get('session.cookie_domain'), (int) ini_get('session.cookie_secure'), (int) ini_get('session.cookie_httponly'));
	            session_write_close();
	            PHPFunctions\sessionID("");

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

	        	// SID is not a user-defined constant and thus won't be auto-deleted
	        	dt_remove_constant('SID');
	        }
            #.endif

	        #.if Pancake\DEBUG_MODE === true
		        if(array_key_exists('pancakephpdebug', vars::$Pancake_request->getGETParams())) {
		            $body = 'Dump of RequestObject:' . "\r\n";
		            $body .= print_r(vars::$Pancake_request, true);
		            $body .= "\r\n";
		            $body .= 'New classes:' . "\r\n";
		            foreach(get_declared_classes() as $class)
		                if(!in_array($class, vars::$Pancake_classesPre))
		                    $body .= $class . "\r\n";
		            $body .= "\r\n";
		            $body .= 'New constants:' . "\r\n";
		            $consts = get_defined_constants(true);
		            foreach($consts['user'] as $const => $constValue)
		                if(!array_key_exists($const, vars::$Pancake_constsPre['user']))
		                    $body .= $const . " = " . $constValue . "\r\n";
		            $body .= "\r\n";
		            $body .= 'New interfaces:' . "\r\n";
		            foreach(get_declared_interfaces() as $interface)
		                if(!in_array($interface, vars::$Pancake_interfacesPre))
		                    $body .= $interface . "\r\n";
		            $body .= "\r\n";
		            $body .= 'New functions:' . "\r\n";
		            $funcs = get_defined_functions();
		            foreach($funcs['user'] as $func)
		                if(!in_array($func, vars::$Pancake_funcsPre['user']))
		                    $body .= $func . "\r\n";
		            $body .= "\r\n";
	                $body .= 'New traits:' . "\r\n";
	                foreach(get_declared_traits() as $trait)
	                    if(!in_array($trait, vars::$Pancake_traitsPre))
	                        $body .= $trait . "\r\n";
	                $body .= "\r\n";
		            $body .= 'New includes:' . "\r\n";
		            foreach(get_included_files() as $include)
		                if(!in_array($include, vars::$Pancake_includesPre))
		                    $body .= $include . "\r\n";

		            $body .= "\r\nContent Body: \r\n";
		            $body .= $contents;
		            $contents = $body;
		            vars::$Pancake_request->setHeader('Content-Type', 'text/plain');
		            vars::$Pancake_request->answerCode = 200;
		        }
	        #.endif

	        write:
	        
            SAPIFinishRequest();
            
	        $object = new \stdClass;
			$object->answerHeaders = vars::$Pancake_request->answerHeaders;
			$object->answerCode = vars::$Pancake_request->answerCode;
            $object->answerCodeString = vars::$Pancake_request->answerCodeString;

	        // Update request object and send it to RequestWorker
	        if(!vars::$invalidRequest) {
	            $object->answerBody = $contents;
            }

	        $data = serialize($object);
	        $packages = array();

	      	if($packageSize = AdjustSendBufferSize(vars::$requestSocket, strlen($data))) {
	      		for($i = 0;$i < ceil(strlen($data) / $packageSize);$i++)
	      			$packages[] = substr($data, $i * $packageSize, $packageSize);
	      	} else
	      		$packages[] = $data;

	        // First transmit the length of the serialized object, then the object itself
	        Write(vars::$requestSocket, dechex(count($packages)));
	        Write(vars::$requestSocket, dechex(strlen($packages[0])));
	        foreach($packages as $data)
	        	Write(vars::$requestSocket, $data);
            
            Close(vars::$requestSocket);

	        // Clean
	        PHPFunctions\OutputBuffering\endClean();

			unset($packages);
			unset($data);
			unset($contents);
			unset($object);

	        dt_remove_constant('PANCAKE_PHP');

	        // Reset error-handling
	        error_reporting(/* .constant 'Pancake\ERROR_REPORTING' */);
	        PHPFunctions\setErrorHandler('Pancake\errorHandler');
	        set_exception_handler(null);

	        // Reset ini-settings
	        ini_set(null, null, true);
	        stream_register_wrapper(null, null, null, true);
            
            // Clean uploaded files
            if(vars::$Pancake_request->uploadedFileTempNames) {
                foreach(vars::$Pancake_request->uploadedFileTempNames as $file)
                    @unlink($file);
                unset($file);
            }

	        vars::$errorHandler = null;
	        vars::$errorHandlerHistory = array();
	        vars::$lastError = null;
	        vars::$Pancake_shutdownCalls = array();
	        vars::$Pancake_headerCallbacks = array();
	        vars::$executedShutdown = false;
	        vars::$invalidRequest = false;
	        #.ifdef 'HAVE_LIMIT'
	        vars::$Pancake_processedRequests++;
	        #.endif
	        #.ifdef 'HAVE_SESSION_EXTENSION'
	        vars::$sessionID = null;
            #.endif
	        vars::$tickFunctions = array();
	        if(vars::$resetSessionSaveHandler) {
	        	session_set_save_handler('Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy');
	        	vars::$resetSessionSaveHandler = false;
	        }

			#.ifdef 'HAVE_SESSION_EXTENSION'
			session_name(/* .ini_get('session.name') */);
			#.endif

	        if(
	        #.ifdef 'HAVE_LIMIT'
	        (vars::$Pancake_processedRequests >= /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpWorkerLimit;' false */) ||
	        #.endif
	        vars::$workerExit) {
	        	Write(vars::$Pancake_currentThread->socket, "EXPECTED_SHUTDOWN");
	        	exit;
	        }

	        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
	        #.ifdef 'SUPPORT_CODECACHE'
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	        #.else
	        cleanGlobals(array(), false, true);
	        #.endif

	        spl_autoload_register(null, null, null, true);

            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                foreach(get_declared_classes() as $class) {
                    if(in_array($class, vars::$classes))
                        continue;

                    $reflect = new \ReflectionClass($class);

                    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetObjectsDestroyDestructor;' false
                        if($reflect->hasMethod('__destruct')) {
                            $name = 'Pancake_DestroyedDestructor' . mt_rand();
                            dt_rename_method($reflect->name, '__destruct', $name);
                            $destroyedDestructors[$reflect->name] = $name;
                        }
                    #.endif

                    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects;' false
                        foreach($reflect->getStaticProperties() as $name => $value) {
                            $prop = new \ReflectionProperty($class, $name);
                            $prop->setAccessible(true);

                            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassObjects;' false
                               if(is_array($value) || is_object($value)) {
                                    $prop->setValue(recursiveClearObjects($value));
                                }
                                #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects;' false
                                    else
                                #.endif
                            #.endif
                            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects;' false
                                if(!is_object($value)) {
                                    $prop->setValue(null);
                                }
                            #.endif

                            unset($name);
                            unset($value);
                            unset($prop);
                        }
                    #.endif

                    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                        foreach($reflect->getMethods() as $method) {
                            foreach($method->getStaticVariables() as $name => $value) {
                                #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects;' false
                                    if(is_array($value) || is_object($value)) {
                                        dt_set_static_method_variable($class, $method->getName(), $name, recursiveClearObjects($value));
                                    }
                                    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                                        else
                                    #.endif
                                #.endif
                                #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                                    if(!is_object($value)) {
                                        dt_set_static_method_variable($class, $method->getName(), $name, null);
                                    }
                                #.endif

                                unset($name);
                                unset($value);
                            }

                            unset($method);
                        }
                    #.endif

                    unset($reflect);
                }
            #.endif

            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                $functions = get_defined_functions();

                foreach($functions['user'] as $function) {
                    if(in_array($function, vars::$functions))
                        continue;

                    $reflect = new \ReflectionFunction($function);

                    foreach($reflect->getStaticVariables() as $name => $value) {
                        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects;' false
                            if(is_array($value) || is_object($value)) {
                                dt_set_static_function_variable($function, $name, recursiveClearObjects($value));
                            }
                            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                                else
                            #.endif
                        #.endif
                        #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
                            if(!is_object($value)) {
                                dt_set_static_function_variable($function, $name, null);
                            }
                        #.endif

                        unset($name);
                        unset($value);
                    }

                    unset($reflect);
                }
            #.endif

            gc_collect_cycles();
            
            #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetObjectsDestroyDestructor;'
            // Restore destroyed destructors
            if(isset($destroyedDestructors)) {
                foreach($destroyedDestructors as $class => $name)
                    if(!@dt_rename_method($class, $name, '__destruct'))
                        dt_remove_method($class, $name);
                 
                unset($class);
                unset($name);
            }
            #.endif

			#.ifdef 'AUTODELETE_FUNCTIONS'
	        $funcsPost = get_defined_functions();
			#.endif
			#.ifdef 'AUTODELETE_CONSTANTS'
	        $constsPost = get_defined_constants(true);
			#.endif

	        #.ifdef 'AUTODELETE_FUNCTIONS'
	            foreach($funcsPost['user'] as $func) {
	                if(!in_array($func, vars::$Pancake_funcsPre['user'])
						#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["functions"];' false
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['functions'][$func])
	                	#.endif
	                	#.if Pancake\DEBUG_MODE === true
	                	&& stripos($func, '__PANCAKE_BENCHMARK__') !== 0
	                	&& !in_array($func, benchmarkFunction(null, false, true))
	                	#.endif
	                	) {
	                    dt_destroy_function_data($func);
	                    $deleteFunctions[] = $func;
	                }
	            }

				unset($func);
				unset($funcsPost);
	        #.endif

	        #.ifdef 'AUTODELETE_CLASSES'
	            foreach(get_declared_classes() as $class) {
	                if(!in_array($class, vars::$Pancake_classesPre)
	                	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["classes"];' false
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['classes'][$class])
	                	#.endif
	                	) {
	                    dt_destroy_class_data($class);
	                    $deleteClasses[] = $class;
	                }
	            }

				unset($class);
	        #.endif

	        #.ifdef 'AUTODELETE_INTERFACES'
	            foreach(get_declared_interfaces() as $interface) {
	            	if(!in_array($interface, vars::$Pancake_interfacesPre)
	            		#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["interfaces"];' false
	            		&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['interfaces'][$interface])
	            		#.endif
	            		) {
	            		dt_destroy_class_data($interface);
	            		$deleteClasses[] = $interface;
	            	}
	            }

				unset($interface);
	        #.endif

	        #.ifdef 'AUTODELETE_TRAITS'
	            foreach(get_declared_traits() as $trait) {
	                if(!in_array($trait, vars::$Pancake_traitsPre)
	                	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["traits"];' false
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['traits'][$trait])
	                	#.endif
	                	) {
	                    dt_destroy_class_data($trait);
	                    $deleteClasses[] = $trait;
	                }
	            }

				unset($trait);
	        #.endif

	        #.ifdef 'HAVE_FORCED_DELETES'
		        foreach(vars::$Pancake_currentThread->vHost->forceDeletes as $delete) {
		            switch($delete['type']) {
		                case 'classes':
		                case 'interfaces':
		                case 'traits':
		                    dt_destroy_class_data($delete['name']);
		                    $deleteClasses[] = $delete['name'];
		                break;
		                case 'functions':
		                    dt_destroy_function_data($delete['name']);
		                    $deleteFunctions[] = $delete['name'];
		                break;
		                case 'includes':
		                    dt_remove_include($delete['name']);
		                break;
		                case 'constants':
		                    dt_remove_constant($delete['name']);
		                break;
		            }
		        }

				unset($delete);
		    #.endif
            
            gc_collect_cycles();
		    
		    if(isset($deleteClasses)) {
		        foreach($deleteClasses as $class) {
		        	dt_remove_class($class);
		        }

				unset($class);
				unset($deleteClasses);
			}

			if(isset($deleteFunctions)) {
		        foreach($deleteFunctions as $function) {
		        	dt_remove_function($function);
		        }

				unset($function);
				unset($deleteFunctions);
			}
            
            #.ifdef 'AUTODELETE_CONSTANTS'
                foreach($constsPost['user'] as $const => $constValue) {
                    if(!array_key_exists($const, vars::$Pancake_constsPre['user'])
                        #.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["constants"];' false
                        && !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['constants'][$const])
                        #.endif
                        ) {
                        dt_remove_constant($const);
                    }
                }

                unset($constsPost);
                unset($const);
                unset($constValue);
            #.endif

            #.ifdef 'AUTODELETE_INCLUDES'
                foreach(get_included_files() as $include) {
                    if(!in_array($include, vars::$Pancake_includesPre)
                        #.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["includes"];' false
                        && !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['includes'][$include])
                        #.endif
                        ) {
                        dt_remove_include($include);
                    }
                }

                unset($include);
            #.endif

		    #.ifdef 'SUPPORT_CODECACHE'
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	        #.else
	       	cleanGlobals(array(), false, true);
	       	#.endif

	        clearstatcache();

	        mt_srand();
	        srand();

	        // Get currently defined funcs, consts, classes, interfaces, traits and includes
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_FUNCTIONS'
	        vars::$Pancake_funcsPre = get_defined_functions();
	        #.endif
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CONSTANTS'
	        vars::$Pancake_constsPre = get_defined_constants(true);
	        #.endif
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_INCLUDES'
	        vars::$Pancake_includesPre = get_included_files();
	        #.endif
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CLASSES'
	        vars::$Pancake_classesPre = get_declared_classes();
	        #.endif
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_INTERFACES'
	        vars::$Pancake_interfacesPre = get_declared_interfaces();
	        #.endif
   			#.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_TRAITS'
        	vars::$Pancake_traitsPre = get_declared_traits();
        	#.endif

	        gc_collect_cycles();

	        #.if Pancake\DEBUG_MODE === true
		        if($results = benchmarkFunction(null, true)) {
		        	foreach($results as $function => $functionResults) {
		        		foreach((array) $functionResults as $result)
		        			$total += $result;

		        		out('Benchmark of function ' . $function . '(): ' . count($functionResults) . ' calls' . ( $functionResults ? ' - ' . (min($functionResults) * 1000) . ' ms min - ' . ($total / count($functionResults) * 1000) . ' ms ave - ' . (max($functionResults) * 1000) . ' ms max - ' . ($total * 1000) . ' ms total' : ""), OUTPUT_REQUEST | OUTPUT_LOG);
		        		unset($total);
		        	}

		        	unset($result);
		        	unset($functionResults);
		        }

				unset($results);
	        #.endif

	        cycle:

	        vars::$listenArray = vars::$listenArrayOrig;
	    }

		do_exit:
    }
?>
<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.thread.php                                         */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
	 
	#.mapVariable '$Pancake_currentThread' '$Pancake_currentThread'
	#.mapVariable '$Pancake_sockets' '$Pancake_sockets'
	#.mapVariable '$Pancake_vHosts' '$Pancake_vHosts'
	#.mapVariable '$shutdownCall' '$shutdownCall'
	#.mapVariable '$vHost' '$vHost'
	
	#.define 'PHPWORKER' true
	
	#.if /* .config 'compressvariables' */
		#.define 'COMPRESS_VARIABLES' true
	#.endif
	
	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpInfoConfig;' false */
		#.define 'EXPOSE_PANCAKE_IN_PHPINFO' true
	#.endif
	
	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpInfovHosts;' false */
		#.define 'EXPOSE_VHOSTS_IN_PHPINFO' true
	#.endif
    
    namespace {
    	#.include 'php/sapi.php'
    }
    
    namespace Pancake {
    	#.include 'php/util.php'

    	#.if /* .config 'compressproperties' */
    		#.config 'compressproperties' false
    	#.endif
    	#.include 'invalidHTTPRequest.exception.php'
    	#.include 'HTTPRequest.class.php'
    	#.include 'vHostInterface.class.php'
    	$Pancake_currentThread->vHost = new vHostInterface($Pancake_currentThread->vHost);
	    vars::$Pancake_currentThread = $Pancake_currentThread;
	    unset($Pancake_currentThread);
	    
	    unset($Pancake_sockets);
	    
	    // Don't allow scripts to get information about other vHosts
	    #.ifdef 'EXPOSE_VHOSTS_IN_PHPINFO'
	    	foreach($Pancake_vHosts as &$vHost) {
	    		if($vHost->id == vars::$Pancake_currentThread->vHost->id) {
	    			$vHost = vars::$Pancake_currentThread->vHost;
	    			continue;
	    		}
	    		
	    		$vHost = new vHostInterface($vHost);
	    	}
	    	vars::$Pancake_vHosts = $Pancake_vHosts;
	    #.endif
	    unset($vHost);
	    unset($Pancake_vHosts);
	    
	    // Clear thread cache
	    Thread::clearCache();
	    
	    // Clean
	    cleanGlobals();
	    
	    get_included_files(true);
	    
	    PHPFunctions\registerShutdownFunction('Pancake\PHPShutdownHandler');
	    
	    dt_remove_function('Pancake\PHPFunctions\registerShutdownFunction');
	    
	    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
	    dt_set_exit_handler('Pancake\PHPExitHandler');
	    dt_throw_exit_exception(true);
	    
	    #.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpDisabledFunctions;' */
		    foreach(vars::$Pancake_currentThread->vHost->phpDisabledFunctions as $function) {
		    	if(function_exists($function)) {
		    		dt_remove_function($function);
		    		eval('function ' . $function . '() { return Pancake\PHPDisabledFunction(__FUNCTION__); }');
		    	}
		    }
		    
		    unset($function);
	    #.endif
	    
	    #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
	    	vars::$classes = get_declared_classes();
	    #.endif
	    
	    #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
	    	$functions = get_defined_functions();
	    	vars::$functions = $functions['user'];
	    	unset($functions);
	    #.endif
	    
	    chdir(vars::$Pancake_currentThread->vHost->documentRoot);
	    
	    #.ifdef 'STDOUT'
	   		dt_remove_constant('STDOUT');
	    #.endif
	    #.ifdef 'STDIN'
	    	dt_remove_constant('STDIN');
	    #.endif
	    #.ifdef 'STDERR'
	    	dt_remove_constant('STDERR');
	    #.endif
	    
	    memory_get_usage(null, true);
	    memory_get_peak_usage(null, true);
	    
	    // Predefine constants
	    #.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->predefinedConstants;' */
		    foreach(vars::$Pancake_currentThread->vHost->predefinedConstants as $name => $value)
		    	define($name, $value, true);
		#.endif
	   	
	    #.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->phpCodeCache;' */
	    	// MIME types are only needed for CodeCache
	    	#.include 'mime.class.php'
	    	MIME::load();
		    
		    // Get a list of files to cache
		    foreach(vars::$Pancake_currentThread->vHost->phpCodeCache as $cacheFile)
		        cacheFile($cacheFile);
	    
		    // Load CodeCache
		    foreach($Pancake_cacheFiles as $cacheFile) {
		    	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' */
	        		set_time_limit(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' */);
	        	#.endif
		        require_once $cacheFile;
		        set_time_limit(0);
		    }
		    
		    unset($cacheFile);
		    unset($Pancake_cacheFiles);
		#.endif
	    
	    // Delete predefined constants, if wanted
	    #.if /* .eval 'global $Pancake_currentThread; return ((bool) $Pancake_currentThread->vHost->predefinedConstants) && $Pancake_currentThread->vHost->deletePredefinedConstantsAfterCodeCacheLoad;' */
	    	foreach((array) vars::$Pancake_currentThread->vHost->predefinedConstants as $name => $value)
	    		dt_remove_constant($name);
	    	
	    	unset($name);
	    	unset($value);
	   	#.endif
	    
	    // Get variables to exclude from deletion (probably set by cached files)
	    vars::$Pancake_exclude = cleanGlobals(array(), true);
	    
	    // Get currently defined funcs, consts, classes, interfaces, traits and includes
	    vars::$Pancake_funcsPre = get_defined_functions();
	    vars::$Pancake_constsPre = get_defined_constants(true);
	    vars::$Pancake_includesPre = get_included_files();
	    vars::$Pancake_classesPre = get_declared_classes();
	    vars::$Pancake_interfacesPre = get_declared_interfaces();
	    #.if PHP_MINOR_VERSION >= 4
	    	vars::$Pancake_traitsPre = get_declared_traits();
	    #.endif
	    
	    // Ready
	    vars::$Pancake_currentThread->parentSignal(/* .constant 'SIGUSR1' */);
	    
	    #.if Pancake\DEBUG_MODE === true
	    	benchmarkFunction('gc_collect_cycles');
	    	benchmarkFunction('socket_read');
	    	benchmarkFunction('socket_write');
	    	//benchmarkFunction('in_array'); !!! dt memory corruption.
	    #.endif
	    
	    // Set user and group
	    setUser();
	
	    // Wait for requests
	    while(vars::$requestSocket = socket_accept(vars::$Pancake_currentThread->vHost->phpSocket)) {
	    	socket_set_block(vars::$requestSocket);
	    	
	    	// Get request object from RequestWorker
	    	$packages = hexdec(socket_read(vars::$requestSocket, 8));
	    	$length = hexdec(socket_read(vars::$requestSocket, 8));
	    	
	    	if($packages > 1) {
	    		$sockData = "";
	    		 
	    		while($packages--)
	    			$sockData .= socket_read(vars::$requestSocket, $length);
	    			 
	    		vars::$Pancake_request = unserialize($sockData);
	    		unset($sockData);
	    	}
	    	else
	    		vars::$Pancake_request = unserialize(socket_read(vars::$requestSocket, $length));
	    	
	    	unset($length);
	    	unset($packages);
	    	
	        // Change directory to document root of the vHost / requested file path
	        chdir(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . dirname(vars::$Pancake_request->getRequestFilePath()));
	        
	        // Set environment vars
	        $_GET = vars::$Pancake_request->getGETParams();
	        $_POST = vars::$Pancake_request->getPOSTParams();
	        $_COOKIE = vars::$Pancake_request->getCookies();
	        $_REQUEST = $_COOKIE + $_POST + $_GET;
	        $_SERVER = vars::$Pancake_request->createSERVER();
	        $_FILES = vars::$Pancake_request->getUploadedFiles();
	        
	        #.if /* .eval 'return ini_get("expose_php");' */
	        	vars::$Pancake_request->setHeader('X-Powered-By', /* .eval 'return "PHP/" . PHP_VERSION;' */);
	        #.endif
	        
	        define('PANCAKE_PHP', true);
	        
	        // Start output buffer
	        ob_start();
	        
	        // Set error-handling
	        error_reporting(/* .eval 'return ini_get("error_reporting");' */);
	        PHPFunctions\setErrorHandler('Pancake\PHPErrorHandler');
	        
	        // Execute script and protect Pancake from exit() and Exceptions
	        try {
	        	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' */
	        		set_time_limit(/* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpMaxExecutionTime;' */);
	        	#.endif
	            include /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->documentRoot;' */ . vars::$Pancake_request->getRequestFilePath();
	            
	            runShutdown:
	            
	            vars::$executedShutdown = true;
	            
	            // Run header callbacks
	            foreach((array) vars::$Pancake_headerCallbacks as $callback)
	                call_user_func($callback);
	                        
	            // Run Registered Shutdown Functions
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
	            
	            goto postShutdown;
	        } catch(\DeepTraceExitException $e) {
	        } catch(\Exception $exception) {
	        	$fatal = false;
	        	
	            if(($oldHandler = set_exception_handler('Pancake\dummy')) !== null) {
	            	try {
	                	call_user_func($oldHandler, $exception);
	            	} catch(\DeepTraceExitException $e) {
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
	
	        // After $invalidRequest is set to true it might still happen that the registered shutdown functions do some output
	        if(vars::$invalidRequest) {
	        	if(!ob_get_contents())
	        		vars::$Pancake_request->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
	        	else
	        		vars::$invalidRequest = false;
	        }
	        
	        // Destroy all output buffers
	        while(PHPFunctions\OutputBuffering\getLevel() > 1)
	            PHPFunctions\OutputBuffering\endFlush();
	
	        // Get contents from output buffer
	        $contents = ob_get_contents();
	        
	        if(session_id() || vars::$sessionID) {
	            vars::$Pancake_request->setCookie(session_name(), session_id() ? session_id() : vars::$sessionID, ini_get('session.cookie_lifetime') ? time() + ini_get('session.cookie_lifetime') : 0, ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
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
		            foreach((array) $consts['user'] as $const => $constValue)
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
		            #.if PHP_MINOR_VERSION >= 4
		                $body .= 'New traits:' . "\r\n";
		                foreach(get_declared_traits() as $trait)
		                    if(!in_array($trait, vars::$Pancake_traitsPre))
		                        $body .= $trait . "\r\n";
		                $body .= "\r\n";
		            #.endif
		            $body .= 'New includes:' . "\r\n"; 
		            foreach(get_included_files() as $include)
		                if(!in_array($include, vars::$Pancake_includesPre))
		                    $body .= $include . "\r\n";
		            
		            $body .= "\r\nContent Body: \r\n";
		            $body .= $contents;
		            $contents = $body;
		            vars::$Pancake_request->setHeader('Content-Type', 'text/plain');
		            vars::$Pancake_request->setAnswerCode(200);
		        }
	        #.endif

	        // Update request object and send it to RequestWorker
	        if(!vars::$invalidRequest)
	            vars::$Pancake_request->setAnswerBody($contents);
	        
	        $data = serialize(vars::$Pancake_request);
	        
	        $packages = array();
	        
	      	if(strlen($data) > (socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024)
	      	&& (socket_set_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */, strlen($data) + 1024) + 1)
	        && strlen($data) > (socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024)) {
	      		$packageSize = socket_get_option(vars::$requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_SNDBUF' */) - 1024;
	      		
	      		for($i = 0;$i < ceil(strlen($data) / $packageSize);$i++)
	      			$packages[] = substr($data, $i * $packageSize, $packageSize);
	      	} else
	      		$packages[] = $data;
	      	
	        // First transmit the length of the serialized object, then the object itself
	        socket_write(vars::$requestSocket, dechex(count($packages)));
	        socket_write(vars::$requestSocket, dechex(strlen($packages[0])));
	        foreach($packages as $data)
	        	socket_write(vars::$requestSocket, $data);
	        
	        // Clean
	        PHPFunctions\OutputBuffering\endClean();
	        
	        dt_remove_constant('PANCAKE_PHP');
	        
	        // Reset error-handling
	        error_reporting(/* .constant 'Pancake\ERROR_REPORTING' */);
	        PHPFunctions\setErrorHandler('Pancake\errorHandler');
	        set_exception_handler(null);
	        
	        // Reset ini-settings
	        ini_set(null, null, true);
	        
	        vars::$errorHandler = null;
	        vars::$errorHandlerHistory = array();
	        vars::$lastError = null;
	        vars::$Pancake_shutdownCalls = array();
	        vars::$Pancake_headerCallbacks = array();
	        vars::$executedShutdown = false;
	        vars::$invalidRequest = false;
	        vars::$Pancake_processedRequests++;
	        vars::$sessionID = null;
	        if(vars::$resetSessionSaveHandler) {
	        	session_set_save_handler('Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy', 'Pancake\dummy');
	        	vars::$resetSessionSaveHandler = false;
	        }
	        
	        if(
	        #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpWorkerLimit;' */
	        (vars::$Pancake_processedRequests >= /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->phpWorkerLimit;' */) || 
	        #.endif
	        vars::$workerExit) {
	        	IPC::send(9999, 1);
	        	exit;
	        }
	        
	        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	
	        spl_autoload_register(null, null, null, true);
	
	        #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
	        	foreach(get_declared_classes() as $class) {
	        		if(in_array($class, vars::$classes))
	        			continue;
	        		
	        		$reflect = new \ReflectionClass($class);
	        		
	        		#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetObjectsDestroyDestructor;' */
		        		if($reflect->hasMethod('__destruct')) {
		        			$name = 'Pancake_DestroyedDestructor' . mt_rand();
		        			dt_rename_method($reflect->getName(), '__destruct', $name);
		        			$destroyedDestructors[$reflect->getName()] = $name;
		        		}
	        		#.endif
	        		
		        	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects;' */
		        		foreach($reflect->getStaticProperties() as $name => $value) {
		        			$prop = new \ReflectionProperty($class, $name);
		        			$prop->setAccessible(true);
		
		        			#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassObjects;' */
			        			if(is_array($value) || is_object($value)) {
			        				$value = recursiveClearObjects($value);
			        					
			        				if(!$value)
			        					$value = null;
			
			        				//gc_collect_cycles();
			
			        				$prop->setValue($value);
			        			}
			        			#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects;' */
			        				else
			        			#.endif
			        		#.endif
			        		#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects;' */
			        			if(!is_object($value)) {
			        				$prop->setValue(null);
			        			}
			        		#.endif
		        			
		        			unset($name);
		        			unset($value);
		        			unset($prop);
		        		}
		        	#.endif
	        		
		        	#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
		        		foreach($reflect->getMethods() as $method) {
		        			foreach($method->getStaticVariables() as $name => $value) {
		        				#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects;' */
			        				if(is_array($value) || is_object($value)) {
			        					$value = recursiveClearObjects($value);
			        					 
			        					if(!$value)
			        						$value = null;
			        					 
			        					//gc_collect_cycles();
			        					 
			        					dt_set_method_variable($class, $method->getName(), $name, $value);
			        				} 
			        				#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
			        					else 
			        				#.endif
			        			#.endif
			        			#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
			        				if(!is_object($value)) {
			        					dt_set_method_variable($class, $method->getName(), $name, null);
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
	        
	        #.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
	        	$functions = get_defined_functions();
	        	
	        	foreach($functions['user'] as $function) {
	        		if(in_array($function, vars::$functions))
	        			continue;
	        	
	        		$reflect = new \ReflectionFunction($function);
	        		 
	        		foreach($reflect->getStaticVariables() as $name => $value) {
	        			#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects;' */
		        			if(is_array($value) || is_object($value)) {
		        				$value = recursiveClearObjects($value);
		        				 
		        				if(!$value)
		        					$value = null;
		        	
		        				//gc_collect_cycles();
		        	
		        				dt_set_function_variable($function, $name, $value);
		        			} 
		        			#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
		        				else 
		        			#.endif
	        			#.endif
		        		#.if /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionNonObjects;' */
		        			if(!is_object($value)) {
		        				dt_set_function_variable($function, $name, null);
		        			}
		        		#.endif
	        			
	        			unset($name);
	        			unset($value);
	        		}
	        		
	        		unset($reflect);
	        	}
	        #.endif
	        
	        // Restore destroyed destructors
	        foreach((array) $destroyedDestructors as $class => $name)
	        	if(!@dt_rename_method($class, $name, '__destruct'))
	        		dt_remove_method($class, $name);
	        
	        $funcsPost = get_defined_functions();
	        $constsPost = get_defined_constants(true);
	        
	        gc_collect_cycles();
	
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["functions"]) ? $Pancake_currentThread->vHost->autoDelete["functions"] : true);' */
	            foreach($funcsPost['user'] as $func) {
	                if(!in_array($func, vars::$Pancake_funcsPre['user'])
						#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["functions"];' */
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['functions'][$func])
	                	#.endif
	                	#.if Pancake\DEBUG_MODE === true
	                	&& strpos($func, '__PANCAKE_BENCHMARK__') !== 0
	                	&& !in_array($func, benchmarkFunction(null, false, true))
	                	#.endif
	                	) {
	                    dt_destroy_function_data($func);
	                    //gc_collect_cycles();
	                    $deleteFunctions[] = $func;
	                }
	            }
	        #.endif
	            
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["classes"]) ? $Pancake_currentThread->vHost->autoDelete["classes"] : true);' */
	            foreach(get_declared_classes() as $class) {
	                if(!in_array($class, vars::$Pancake_classesPre)
	                	#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["classes"];' */
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['classes'][$class])
	                	#.endif
	                	) {
	                    dt_destroy_class_data($class);
	                    //gc_collect_cycles();
	                    $deleteClasses[] = $class;
	                }
	            }
	        #.endif
	            
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["interfaces"]) ? $Pancake_currentThread->vHost->autoDelete["interfaces"] : true);' */
	            foreach(get_declared_interfaces() as $interface) {
	            	if(!in_array($interface, vars::$Pancake_interfacesPre)
	            		#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["interfaces"];' */
	            		&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['interfaces'][$interface])
	            		#.endif
	            		) {
	            		dt_destroy_class_data($interface);
	            		//gc_collect_cycles();
	            		$deleteClasses[] = $interface;
	            	}
	            }
	        #.endif
	
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["constants"]) ? $Pancake_currentThread->vHost->autoDelete["constants"] : true);' */
	            foreach($constsPost['user'] as $const => $constValue) {
	                if(!array_key_exists($const, vars::$Pancake_constsPre['user'])
	                	#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["constants"];' */
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['constants'][$const])
	                	#.endif
	                	) {
	                    dt_remove_constant($const);
	                    //gc_collect_cycles();
	                }
	            }
	        #.endif
	            
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["includes"]) ? $Pancake_currentThread->vHost->autoDelete["includes"] : true);' */
	            foreach(get_included_files() as $include) {
	                if(!in_array($include, vars::$Pancake_includesPre)
	                	#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["includes"];' */
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['includes'][$include])
	                	#.endif
	                	) {
	                    dt_remove_include($include);
	                    //gc_collect_cycles();
	                }
	            }
	        #.endif
	        
	        #.if PHP_MINOR_VERSION >= 4 && /* .eval 'global $Pancake_currentThread; return (bool) (isset($Pancake_currentThread->vHost->autoDelete["traits"]) ? $Pancake_currentThread->vHost->autoDelete["traits"] : true);' */
	            foreach(get_declared_traits() as $trait) {
	                if(!in_array($trait, vars::$Pancake_traitsPre)
	                	#.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->autoDeleteExcludes["traits"];' */
	                	&& !isset(vars::$Pancake_currentThread->vHost->autoDeleteExcludes['traits'][$trait])
	                	#.endif
	                	) {
	                    dt_destroy_class_data($trait);
	                    //gc_collect_cycles();
	                    $deleteClasses[] = $trait;
	                }
	            }
	        #.endif
	        
	        #.if /* .eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->forceDeletes;' */
		        foreach(vars::$Pancake_currentThread->vHost->forceDeletes as $delete) {
		            switch($delete['type']) {
		                case 'classes':
		                case 'interfaces':
		                #.if PHP_MINOR_VERSION >= 4
		                case 'traits':
		                #.endif
		                    dt_destroy_class_data($delete['name']);
		                    //gc_collect_cycles();
		                    $deleteClasses[] = $delete['name'];
		                break;
		                case 'functions':
		                    dt_destroy_function_data($delete['name']);
		                    //gc_collect_cycles();
		                    $deleteFunctions[] = $delete['name'];
		                break;
		                case 'includes':
		                    dt_remove_include($delete['name']);
		                    //gc_collect_cycles();
		                break;
		                case 'constants':
		                    dt_remove_constant($delete['name']);
		                    //gc_collect_cycles();
		                break;
		            }
		        }
		    #.endif
	        
	        foreach((array) $deleteClasses as $class) {
	        	dt_remove_class($class);
	        	//gc_collect_cycles();
	        }
	        
	        foreach((array) $deleteFunctions as $function) {
	        	dt_remove_function($function);
	        	//gc_collect_cycles();
	        }
	        
	        // Do not activate static method call fixing if it does not make sense
	        #.if PHP_MINOR_VERSION >= 4 && /* .eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->fixStaticMethodCalls;' */
		        if($deleteClasses)
		        	dt_fix_static_method_calls(true);
		    #.endif
	        
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	        
	        clearstatcache();
	        
	        mt_srand();
	        srand();
	        
	        // Get currently defined funcs, consts, classes, interfaces, traits and includes
	        vars::$Pancake_funcsPre = get_defined_functions();
	        vars::$Pancake_constsPre = get_defined_constants(true);
	        vars::$Pancake_includesPre = get_included_files();
	        vars::$Pancake_classesPre = get_declared_classes();
	        vars::$Pancake_interfacesPre = get_declared_interfaces();
	        #.if PHP_MINOR_VERSION >= 4
	        	vars::$Pancake_traitsPre = get_declared_traits();
	        	dt_clear_cache();
	       	#.endif
	        
	        gc_collect_cycles();
	        
	        #.if Pancake\DEBUG_MODE === true
		        if($results = benchmarkFunction(null, true)) {
		        	foreach($results as $function => $functionResults) {
		        		foreach($functionResults as $result)
		        			$total += $result;
		        
		        		out('Benchmark of function ' . $function . '(): ' . count($functionResults) . ' calls - ' . (min($functionResults) * 1000) . ' ms min - ' . ($total / count($functionResults) * 1000) . ' ms ave - ' . (max($functionResults) * 1000) . ' ms max - ' . ($total * 1000) . ' ms total', /* .constant 'Pancake\REQUEST' */);
		        		unset($total);
		        	}
		        	
		        	unset($result);
		        	unset($functionResults);
		        	unset($results);
		        }
	        #.endif
	    }
    }
?>
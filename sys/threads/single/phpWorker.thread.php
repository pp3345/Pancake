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

	#.if #.eval 'global $Pancake_currentThread; return (bool) $Pancake_currentThread->vHost->forceDeletes;' false
		#.HAVE_FORCED_DELETES = true
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

	#.if Pancake\DEBUG_MODE === true
		#.define 'BENCHMARK' false
	#.else
		#.define 'BENCHMARK' false
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

	    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetClassNonObjects || $Pancake_currentThread->vHost->resetClassObjects || $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
	    	vars::$classes = get_declared_classes();
	    #.endif

	    #.if #.eval 'global $Pancake_currentThread; return $Pancake_currentThread->vHost->resetFunctionObjects || $Pancake_currentThread->vHost->resetFunctionNonObjects;' false
	    	vars::$functions = get_defined_functions()['user'];
	    #.endif

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
	    #.if Pancake\DEBUG_MODE
	    vars::$Pancake_funcsPre = get_defined_functions();
	    #.endif
	    #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CONSTANTS'
	    vars::$Pancake_constsPre = get_defined_constants(true);
	    #.endif
	    #.if Pancake\DEBUG_MODE
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
        SAPIPrepare(vars::$Pancake_currentThread->vHost->phpSocket, vars::$Pancake_currentThread->socket);

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
	        
            try {
                SAPIFinishRequest();
            } catch(ExitException $e) {
                unset($e);
            }
                        
            SAPIPostRequestCleanup();
            
            // Clean uploaded files
            if(vars::$Pancake_request->uploadedFileTempNames) {
                foreach(vars::$Pancake_request->uploadedFileTempNames as $file)
                    @unlink($file);
                unset($file);
            }

	        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
	        #.ifdef 'SUPPORT_CODECACHE'
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	        #.else
	        cleanGlobals(array(), false, true);
	        #.endif

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

			#.ifdef 'AUTODELETE_CONSTANTS'
	        $constsPost = get_defined_constants(true);
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

		    #.ifdef 'SUPPORT_CODECACHE'
	        cleanGlobals(vars::$Pancake_exclude, false, true);
	        #.else
	       	cleanGlobals(array(), false, true);
	       	#.endif

	        clearstatcache();

	        mt_srand();
	        srand();

	        // Get currently defined funcs, consts, classes, interfaces, traits and includes
	        #.if Pancake\DEBUG_MODE
	        vars::$Pancake_funcsPre = get_defined_functions();
	        #.endif
	        #.if Pancake\DEBUG_MODE || #.isDefined 'AUTODELETE_CONSTANTS'
	        vars::$Pancake_constsPre = get_defined_constants(true);
	        #.endif
	        #.if Pancake\DEBUG_MODE
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
	    }

		do_exit:
    }
?>
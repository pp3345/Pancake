<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.thread.php                                         */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    // Clean
    Pancake_cleanGlobals();
    
    // Load ScriptUtils
    require_once 'php/util.php';    
    
    // Set user and group
    Pancake_setUser(); 
    
    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
    dt_set_exit_handler('Pancake_PHPExitHandler');     
    dt_throw_exit_exception(true);
    dt_show_plain_info(false);
    
    // Load CodeCache
    foreach((array) $Pancake_currentThread->vHost->getCodeCacheFiles() as $cacheFile)
        Pancake_cacheFile($Pancake_currentThread->vHost, $cacheFile);
    
    // Wait for requests    
    while($Pancake_message = Pancake_IPC::get()) {        
        // Get request from Shared Memory
        $Pancake_request = Pancake_SharedMemory::get($Pancake_message);
        if($Pancake_request === false) {
            for($i = 0;$i < 10 && $Pancake_request === false;$i++) {
                usleep(1000);
                $Pancake_request = Pancake_SharedMemory::get($Pancake_message);
            }
            if($Pancake_request === false)
                continue;
        }
        
        // Change directory to DocumentRoot of the vHost / requested file path
        chdir($Pancake_currentThread->vHost->getDocumentRoot() . dirname($Pancake_request->getRequestFilePath()));
        
        // Get currently defined funcs, consts and vars
        $Pancake_funcsPre = get_defined_functions();
        $Pancake_constsPre = get_defined_constants(true);
        $Pancake_includesPre = get_included_files();
        $Pancake_classesPre = get_declared_classes();
        $Pancake_interfacesPre = get_declared_interfaces();
        
        // Set environment vars
        $_GET = $Pancake_request->getGETParams();
        $_POST = $Pancake_request->getPOSTParams();
        $_REQUEST = Pancake_array_merge($_GET, $_POST);
        $_COOKIE = $Pancake_request->getCookies();
        $_SERVER = $Pancake_request->createSERVER();
        $_FILES = $Pancake_request->getUploadedFiles();
        
        define('PANCAKE_PHP', true);
        
        // Start output buffer
        ob_start();
        
        // Set error-handling
        error_reporting(ini_get('error_reporting'));
        set_error_handler('Pancake_PHPErrorHandler');
        
        // Script will throw an exception when trying to exit, this way we can handle it easily
        try {
            include $Pancake_request->getvHost()->getDocumentRoot() . $Pancake_request->getRequestFilePath();
            // Run Registered Shutdown Functions
            foreach((array) $Pancake_shutdownCalls as $shutdownCall) {
                unset($args);
                $call = 'call_user_func($shutdownCall["callback"]';
                foreach((array) @$shutdownCall['args'] as $arg) {
                    $args[$i++] = $arg;
                    if($args)
                        $call .= ',';
                    $call .= '$args['.$i.']';
                }
                $call .= ');';
                eval($call);
            }
        } catch(DeepTraceExitException $e) {
        } catch(Exception $exception) {
            if(($oldHandler = set_exception_handler('Pancake_dummy')) !== null) {
                call_user_func($oldHandler, $exception); 
            }
        }
        
        // Reset error-handling
        error_reporting(PANCAKE_ERROR_REPORTING);
        set_error_handler('Pancake_errorHandler');
        set_exception_handler(null);
        
        // Destroy all output buffers
        while(ob_get_level() > 0)
            Pancake_ob_end_flush_orig();
        
        // Get contents from output buffer
        $contents = ob_get_contents();
        
        // Update request-object and send it to request-worker
        $Pancake_request->setAnswerBody($contents);
        Pancake_SharedMemory::put($Pancake_request, $Pancake_message);
        Pancake_IPC::send($Pancake_request->getRequestWorker()->IPCid, 1);
        
        // Clean
        Pancake_ob_end_clean_orig();
        
        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
        Pancake_cleanGlobals();
        
        $funcsPost = get_defined_functions();
        $constsPost = get_defined_constants(true);
        $includesPost = get_included_files();
        $classesPost = get_declared_classes();    
        $interfacesPost = get_declared_interfaces();
        
        foreach($interfacesPost as $interface) {
            if(!in_array($interface, $Pancake_interfacesPre))
                dt_remove_interface($interface);
        }
        
        foreach($funcsPost['user'] as $func) {
            if(!in_array($func, $Pancake_funcsPre['user'])) {
                dt_remove_function($func);
            }
        }
        
        foreach($classesPost as $class) {
            if(!in_array($class, $Pancake_classesPre))
                dt_remove_class($class);
        }
        
        if($constsPost['user'])
            foreach($constsPost['user'] as $const => $constValue) {
                if(!array_key_exists($const, $Pancake_constsPre['user'])) {
                    dt_remove_constant($const);
                }
            }
            
        foreach($includesPost as $include) {
            if(!in_array($include, $Pancake_includesPre))
                dt_remove_include($include);
        } 
        
        Pancake_cleanGlobals();
        
        unset($Pancake_classesPre);
        unset($Pancake_constsPre);
        unset($Pancake_funcsPre);
        unset($Pancake_includesPre);
        
        gc_collect_cycles();
    }
    
    dt_throw_exit_exception(false);      
?>

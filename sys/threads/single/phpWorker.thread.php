<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.thread.php                                         */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
        
    /**
    * @var PHPWorker
    */
    $Pancake_currentThread;
    
    // Clean
    cleanGlobals();
    
    // Load SAPI functions and PHP runtime utilities
    require_once 'php/sapi.php';
    require_once 'php/util.php';    
    
    // Set user and group
    setUser(); 
    
    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
    dt_set_exit_handler('Pancake\PHPExitHandler');     
    dt_throw_exit_exception(true);
    
    // Get a list of files to cache
    foreach((array) $Pancake_currentThread->vHost->getCodeCacheFiles() as $cacheFile)
        cacheFile($Pancake_currentThread->vHost, $cacheFile);
        
    foreach((array) $Pancake_cacheFiles as $cacheFile)
        require_once $cacheFile;
        
    unset($cacheFile);
    unset($Pancake_cacheFiles);
    
    // Get variables to exclude from deletion (probably set by cached files)
    $Pancake_exclude = cleanGlobals(null, true);
    $Pancake_exclude[] = 'Pancake_exclude';
    
    // Wait for requests    
    while($Pancake_message = IPC::get()) {        
        // Get request object from Shared Memory
        /**
        * @var HTTPRequest
        */
        $Pancake_request = SharedMemory::get($Pancake_message);
        if($Pancake_request === false) {
            for($i = 0;$i < 10 && $Pancake_request === false;$i++) {
                usleep(1000);
                $Pancake_request = SharedMemory::get($Pancake_message);
            }
            if($Pancake_request === false)
                continue;
        }
        
        // Delete key from SharedMemory in order to reduce the risk of overfilling the SharedMemory while processing
        SharedMemory::delete($Pancake_message);
        
        // Change directory to DocumentRoot of the vHost / requested file path
        chdir($Pancake_currentThread->vHost->getDocumentRoot() . dirname($Pancake_request->getRequestFilePath()));
        
        // Get currently defined funcs, consts and vars
        $Pancake_funcsPre = get_defined_functions();
        $Pancake_constsPre = get_defined_constants(true);
        $Pancake_includesPre = get_included_files();
        $Pancake_classesPre = get_declared_classes();
        $Pancake_interfacesPre = get_declared_interfaces();
        if(PHP_MINOR_VERSION >= 4)
            $Pancake_traitsPre = get_declared_traits();
        
        // Set environment vars
        $_GET = $Pancake_request->getGETParams();
        $_POST = $Pancake_request->getPOSTParams();
        $_COOKIE = $Pancake_request->getCookies();
        $_REQUEST = array_merge($_GET, $_POST);
        $_REQUEST = array_merge($_REQUEST, $_COOKIE);
        $_SERVER = $Pancake_request->createSERVER();
        $_FILES = $Pancake_request->getUploadedFiles();
        
        define('PANCAKE_PHP', true);
        
        // Start output buffer
        ob_start();
        
        // Set error-handling
        error_reporting(ini_get('error_reporting'));
        set_error_handler('Pancake\PHPErrorHandler');
        
        /*Pancake_lockVariable('Pancake_message');
        Pancake_lockVariable('Pancake_request');
        Pancake_lockVariable('Pancake_funcsPre');
        Pancake_lockVariable('Pancake_constsPre');
        Pancake_lockVariable('Pancake_includesPre');
        Pancake_lockVariable('Pancake_classesPre');
        Pancake_lockVariable('Pancake_interfacesPre');
        Pancake_lockVariable('Pancake_currentThread');
        Pancake_lockVariable('Pancake_processedRequests');*/
        
        // Script will throw an exception when trying to exit, this way we can handle it easily
        try {
            include $Pancake_request->getvHost()->getDocumentRoot() . $Pancake_request->getRequestFilePath();
            
            // Run header callback
            if(@$Pancake_headerCallback)
                call_user_func($Pancake_headerCallback);
            
            //Pancake_lockVariable('Pancake_shutdownCalls', true);
            // Run Registered Shutdown Functions
            foreach((array) $Pancake_shutdownCalls as $shutdownCall) {
                unset($args);
                $call = 'call_user_func($shutdownCall["callback"]';
                foreach((array) @$shutdownCall['args'] as $arg) {
                    if($args)
                        $call .= ',';
                    $args[$i++] = $arg;
                    $call .= '$args['.$i.']';
                }
                $call .= ');';
                eval($call);
            }
        } catch(\DeepTraceExitException $e) {
        } catch(\Exception $exception) {
            if(($oldHandler = set_exception_handler('Pancake\dummy')) !== null) {
                call_user_func($oldHandler, $exception); 
            }
        }
        
        // Reset error-handling
        error_reporting(ERROR_REPORTING);
        set_error_handler('Pancake\errorHandler');
        set_exception_handler(null);
        
        // Destroy all output buffers
        while(ob_get_level() > 0)
            Pancake_ob_end_flush_orig();
        
        // Get contents from output buffer
        $contents = ob_get_contents();
        
        /*Pancake_lockVariable('Pancake_request', true);
        Pancake_lockVariable('Pancake_message', true);*/
        
        if(session_id())
            $Pancake_request->setCookie(session_name(), session_id(), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
        
        $_GET = $Pancake_request->getGETParams();
        if(isset($_GET['pancakephpdebug']) && PANCAKE_DEBUG_MODE === true) {
            $body = 'Dump of RequestObject:' . "\r\n";
            $body .= print_r($Pancake_request, true);
            $body .= "\r\n";
            $body .= 'Newly declared classes:' . "\r\n";
            foreach(get_declared_classes() as $class)
                if(!in_array($class, $Pancake_classesPre))
                    $body .= $class . "\r\n";
            $body .= "\r\n";
            $body .= 'Newly defined constants:' . "\r\n";
            $consts = get_defined_constants(true);
            foreach((array) $consts['user'] as $const => $constValue)
                if(!array_key_exists($const, $Pancake_constsPre['user']))
                    $body .= $const . "\r\n";
            $body .= "\r\n";
            $body .= 'Newly declared interfaces:' . "\r\n";
            foreach(get_declared_interfaces() as $interface)
                if(!in_array($interface, $Pancake_interfacesPre))
                    $body .= $interface . "\r\n";
            $body .= "\r\n";
            $body .= 'Newly defined functions:' . "\r\n";
            $funcs = get_defined_functions();
            foreach($funcs['user'] as $func)
                if(!in_array($func, $Pancake_funcsPre['user']))
                    $body .= $func . "\r\n";
            $body .= "\r\n";
            if(PHP_MINOR_VERSION >= 4) {
                $body .= 'Newly declared traits:' . "\r\n";
                foreach(get_declared_traits() as $trait)
                    if(!in_array($trait, $Pancake_traitsPre))
                        $body .= $trait . "\r\n";
                $body .= "\r\n";
            }
            $body .= 'Newly included files:' . "\r\n"; 
            foreach(get_included_files() as $include)
                if(!in_array($include, $Pancake_includesPre))
                    $body .= $include . "\r\n";
            
            $contents = $body;
            $Pancake_request->setHeader('Content-Type', 'text/plain');
        }
        
        if(ini_get('expose_php') == true)
            $Pancake_request->setHeader('X-Powered-By', 'PHP/' . PHP_VERSION);
        
        // Update RequestObject and send it to RequestWorker
        $Pancake_request->setAnswerBody($contents);
        SharedMemory::put($Pancake_request, $Pancake_message);
        IPC::send($Pancake_request->getRequestWorker()->IPCid, 1);
        
        // Clean
        Pancake_ob_end_clean_orig();

        @session_write_close();
                                   
        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
        cleanGlobals($exclude);
        
        /*Pancake_lockVariable('Pancake_funcsPre', true);
        Pancake_lockVariable('Pancake_constsPre', true);
        Pancake_lockVariable('Pancake_includesPre', true);
        Pancake_lockVariable('Pancake_classesPre', true);
        Pancake_lockVariable('Pancake_interfacesPre', true);
        Pancake_lockVariable('Pancake_currentThread', true);
        Pancake_lockVariable('Pancake_processedRequests', true);*/
        
        $Pancake_processedRequests++;
        
        $funcsPost = get_defined_functions();
        $constsPost = get_defined_constants(true);
        
        foreach(get_declared_interfaces() as $interface) {
            if(!in_array($interface, $Pancake_interfacesPre))
                dt_remove_interface($interface);
        }
        
        foreach($funcsPost['user'] as $func) {
            if(!in_array($func, $Pancake_funcsPre['user']))
                dt_remove_function($func);
        }
        
        foreach(get_declared_classes() as $class) {
            if(!in_array($class, $Pancake_classesPre))
                dt_remove_class($class);
        }
        
        foreach($constsPost['user'] as $const => $constValue) {
            if(!array_key_exists($const, $Pancake_constsPre['user']))
                dt_remove_constant($const);
        }
            
        foreach(get_included_files() as $include) {
            if(!in_array($include, $Pancake_includesPre))
                dt_remove_include($include);
        } 
        
        if(PHP_MINOR_VERSION >= 4) {
            foreach(get_declared_traits() as $trait) {
                if(!in_array($trait, $Pancake_traitsPre))
                    dt_remove_trait($trait);
            }
            unset($Pancake_traitsPre);
        }
        
        cleanGlobals($Pancake_exclude);
        
        unset($Pancake_classesPre);
        unset($Pancake_constsPre);
        unset($Pancake_funcsPre);
        unset($Pancake_includesPre);
        unset($Pancake_interfacesPre);
        
        if($Pancake_currentThread->vHost->getPHPWorkerLimit() && $Pancake_processedRequests >= $Pancake_currentThread->vHost->getPHPWorkerLimit()) {
            IPC::send(9999, 1);
            exit;
        }
        
        gc_collect_cycles();
    }
    
    dt_throw_exit_exception(false);      
?>

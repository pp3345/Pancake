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
        
    // Load SAPI functions and PHP runtime utilities
    require_once 'php/sapi.php';
    require_once 'php/util.php';    
        
    /**
    * @var PHPWorker
    */
    vars::$Pancake_currentThread = $Pancake_currentThread;
    
    // Clean
    cleanGlobals();
    
    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
    dt_set_exit_handler('Pancake\PHPExitHandler');     
    dt_throw_exit_exception(true);
    
    // Get a list of files to cache
    foreach((array) vars::$Pancake_currentThread->vHost->getCodeCacheFiles() as $cacheFile)
        cacheFile(vars::$Pancake_currentThread->vHost, $cacheFile);
        
    foreach((array) $Pancake_cacheFiles as $cacheFile)
        require_once $cacheFile;
        
    unset($cacheFile);
    unset($Pancake_cacheFiles);
    
    // Don't allow scripts to get information about other vHosts
    if(vars::$Pancake_currentThread->vHost->exposePancakevHostsInPHPInfo())
        vars::$Pancake_vHosts = $Pancake_vHosts;
    unset($Pancake_vHosts);
    
    // Get variables to exclude from deletion (probably set by cached files)
    vars::$Pancake_exclude = cleanGlobals(array(), true);
    vars::$Pancake_exclude[] = 'Pancake_exclude';
    
    // Ready
    vars::$Pancake_currentThread->parentSignal(\SIGUSR1);
    
    // Set user and group
    setUser();
    
    // Wait for requests    
    while(vars::$Pancake_message = IPC::get()) {        
        // Get request object from Shared Memory
        /**
        * @var HTTPRequest
        */
        vars::$Pancake_request = SharedMemory::get(vars::$Pancake_message);
        if(vars::$Pancake_request === false) {
            for($i = 0;$i < 10 && vars::$Pancake_request === false;$i++) {
                usleep(1000);
                vars::$Pancake_request = SharedMemory::get(vars::$Pancake_message);
            }
            if(vars::$Pancake_request === false)
                continue;
        }
        
        // Delete key from SharedMemory in order to reduce the risk of overfilling the SharedMemory while processing
        SharedMemory::delete(vars::$Pancake_message);
        
        // Change directory to DocumentRoot of the vHost / requested file path
        chdir(vars::$Pancake_currentThread->vHost->getDocumentRoot() . dirname(vars::$Pancake_request->getRequestFilePath()));
        
        // Get currently defined funcs, consts and vars
        vars::$Pancake_funcsPre = get_defined_functions();
        vars::$Pancake_constsPre = get_defined_constants(true);
        vars::$Pancake_includesPre = get_included_files();
        vars::$Pancake_classesPre = get_declared_classes();
        vars::$Pancake_interfacesPre = get_declared_interfaces();
        if(\PHP_MINOR_VERSION >= 4)
            vars::$Pancake_traitsPre = get_declared_traits();
        
        // Set environment vars
        $_GET = vars::$Pancake_request->getGETParams();
        $_POST = vars::$Pancake_request->getPOSTParams();
        $_COOKIE = vars::$Pancake_request->getCookies();
        $_REQUEST = array_merge($_GET, $_POST);
        $_REQUEST = array_merge($_REQUEST, $_COOKIE);
        $_SERVER = vars::$Pancake_request->createSERVER();
        $_FILES = vars::$Pancake_request->getUploadedFiles();
        
        define('PANCAKE_PHP', true);
        
        // Start output buffer
        ob_start();
        
        // Set error-handling
        error_reporting(ini_get('error_reporting'));
        set_error_handler('Pancake\PHPErrorHandler');
        
        // Script will throw an exception when trying to exit, this way we can handle it easily
        try {
            include vars::$Pancake_request->getvHost()->getDocumentRoot() . vars::$Pancake_request->getRequestFilePath();
            
            // Run header callback
            if(@vars::$Pancake_headerCallback)
                call_user_func(vars::$Pancake_headerCallback);
            
            //Pancake_lockVariable('Pancake_shutdownCalls', true);
            // Run Registered Shutdown Functions
            foreach((array) vars::$Pancake_shutdownCalls as $shutdownCall) {
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
            vars::$Pancake_shutdownCalls = null;
        } catch(\DeepTraceExitException $e) {
        } catch(\Exception $exception) {
            if(($oldHandler = set_exception_handler('Pancake\dummy')) !== null) {
                call_user_func($oldHandler, $exception); 
            } else {
                while(ob_get_level())
                    PHPFunctions\OutputBuffering\endFlush();
                if(ini_get('display_errors')) {
                    echo "\n";
                    echo 'Fatal error: Uncaught exception \'' . get_class($exception) . '\'';
                    if($exception->getMessage())
                        echo ' with message \'' . $exception->getMessage() . '\'';
                    echo ' in ' . $exception->getFile() . ':' . $exception->getLine();
                    echo "\n";
                    echo "Stack trace:";
                    echo "\n";
                    $trace = explode("\n", $exception->getTraceAsString());
                    
                    // Output trace elements
                    foreach($trace as $traceElement) {
                        if(strpos($traceElement, 'sys/threads/single/phpWorker.thread.php'))
                            break;
                        echo $traceElement . "\n";
                        $i++;
                    }
                    echo '#' . $i . ' {main}';
                    echo "\n";
                    echo "  thrown in " . $exception->getFile() . ' on line ' . $exception->getLine();
                // Send 500 if no content
                } else if(!ob_get_contents()) {
                    $invalidRequest = true;
                    vars::$Pancake_request->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
                }
            }
        }
        
        // Reset error-handling
        error_reporting(ERROR_REPORTING);
        set_error_handler('Pancake\errorHandler');
        set_exception_handler(null);
        
        // Destroy all output buffers
        while(PHPFunctions\OutputBuffering\getLevel() > 1)
            PHPFunctions\OutputBuffering\endFlush();

        // Get contents from output buffer
        $contents = ob_get_contents();
        
        if(session_id())
            vars::$Pancake_request->setCookie(session_name(), session_id(), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
        
        $_GET = vars::$Pancake_request->getGETParams();
        if(isset($_GET['pancakephpdebug']) && DEBUG_MODE === true) {
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
                    $body .= $const . "\r\n";
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
            if(\PHP_MINOR_VERSION >= 4) {
                $body .= 'New traits:' . "\r\n";
                foreach(get_declared_traits() as $trait)
                    if(!in_array($trait, vars::$Pancake_traitsPre))
                        $body .= $trait . "\r\n";
                $body .= "\r\n";
            }
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
        
        if(ini_get('expose_php') == true)
            vars::$Pancake_request->setHeader('X-Powered-By', 'PHP/' . PHP_VERSION);
        
        // Update RequestObject and send it to RequestWorker
        if(!$invalidRequest)
            vars::$Pancake_request->setAnswerBody($contents);
        SharedMemory::put(vars::$Pancake_request, vars::$Pancake_message);
        IPC::send(vars::$Pancake_request->getRequestWorker()->IPCid, 1);
        
        // Reset ini-settings
        ini_set(null, null, true);
        
        // Clean
        PHPFunctions\OutputBuffering\endClean();

        @session_write_close();
                                   
        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
        cleanGlobals(vars::$Pancake_exclude);
        
        vars::$Pancake_processedRequests++;
        
        $funcsPost = get_defined_functions();
        $constsPost = get_defined_constants(true);
        
        if(vars::$Pancake_currentThread->vHost->shouldAutoDelete('interfaces'))
            foreach(get_declared_interfaces() as $interface) {
                if(!in_array($interface, vars::$Pancake_interfacesPre) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($interface, 'interfaces'))
                    dt_remove_interface($interface);
            }
        
        if(vars::$Pancake_currentThread->vHost->shouldAutoDelete('functions'))
            foreach($funcsPost['user'] as $func) {
                if(!in_array($func, vars::$Pancake_funcsPre['user']) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($func, 'functions'))
                    dt_remove_function($func);
            }
        
        if(vars::$Pancake_currentThread->vHost->shouldAutoDelete('classes'))
            foreach(get_declared_classes() as $class) {
                if(!in_array($class, vars::$Pancake_classesPre) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($class, 'classes'))
                    dt_remove_class($class);
            }
        
        if(vars::$Pancake_currentThread->vHost->shouldAutoDelete('constants'))
            foreach($constsPost['user'] as $const => $constValue) {
                if(!array_key_exists($const, vars::$Pancake_constsPre['user']) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($const, 'constants'))
                    dt_remove_constant($const);
            }
            
        if(vars::$Pancake_currentThread->vHost->shouldAutoDelete('includes'))
            foreach(get_included_files() as $include) {
                if(!in_array($include, vars::$Pancake_includesPre) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($include, 'includes'))
                    dt_remove_include($include);
            } 
        
        if(\PHP_MINOR_VERSION >= 4 && vars::$Pancake_currentThread->vHost->shouldAutoDelete('traits')) {
            foreach(get_declared_traits() as $trait) {
                if(!in_array($trait, vars::$Pancake_traitsPre) && !vars::$Pancake_currentThread->vHost->isAutoDeleteExclude($trait, 'traits'))
                    dt_remove_trait($trait);
            }
        }
        
        foreach(vars::$Pancake_currentThread->vHost->getForcedDeletes() as $delete) {
            switch($delete['type']) {
                case 'classes':
                    @dt_remove_class($delete['name']);
                break;
                case 'functions':
                    @dt_remove_function($delete['name']);
                break;
                case 'includes':
                    @dt_remove_include($delete['name']);
                break;
                case 'traits':
                    @dt_remove_trait($delete['name']);
                break;
                case 'constants':
                    @dt_remove_constant($delete['name']);
                break;
                case 'interfaces':
                    @dt_remove_interface($delete['name']);
                break;
            }
        }
        
        cleanGlobals(vars::$Pancake_exclude);
        
        if(vars::$Pancake_currentThread->vHost->getPHPWorkerLimit() && vars::$Pancake_processedRequests >= vars::$Pancake_currentThread->vHost->getPHPWorkerLimit()) {
            IPC::send(9999, 1);
            exit;
        }
        
        dt_clear_cache();
        
        clearstatcache();
        
        gc_collect_cycles();
    }
?>
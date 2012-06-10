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
    
    // Don't allow scripts to get information about other vHosts
    if(!$Pancake_currentThread->vHost->exposePancakevHostsInPHPInfo())
        unset($Pancake_vHosts);
    
    // Get variables to exclude from deletion (probably set by cached files)
    $Pancake_exclude = cleanGlobals(array(), true);
    $Pancake_exclude[] = 'Pancake_exclude';
    
    // Initialize some variables
    $invalidRequest = false;
    
    // Ready
    $Pancake_currentThread->parentSignal(\SIGUSR1);
    
    // Set user and group
    setUser();
    
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
        if(\PHP_MINOR_VERSION >= 4)
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
                    $Pancake_request->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
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
        
        /*Pancake_lockVariable('Pancake_request', true);
        Pancake_lockVariable('Pancake_message', true);*/
        
        if(session_id())
            $Pancake_request->setCookie(session_name(), session_id(), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));
        
        $_GET = $Pancake_request->getGETParams();
        if(isset($_GET['pancakephpdebug']) && DEBUG_MODE === true) {
            $body = 'Dump of RequestObject:' . "\r\n";
            $body .= print_r($Pancake_request, true);
            $body .= "\r\n";
            $body .= 'New classes:' . "\r\n";
            foreach(get_declared_classes() as $class)
                if(!in_array($class, $Pancake_classesPre))
                    $body .= $class . "\r\n";
            $body .= "\r\n";
            $body .= 'New constants:' . "\r\n";
            $consts = get_defined_constants(true);
            foreach((array) $consts['user'] as $const => $constValue)
                if(!array_key_exists($const, $Pancake_constsPre['user']))
                    $body .= $const . "\r\n";
            $body .= "\r\n";
            $body .= 'New interfaces:' . "\r\n";
            foreach(get_declared_interfaces() as $interface)
                if(!in_array($interface, $Pancake_interfacesPre))
                    $body .= $interface . "\r\n";
            $body .= "\r\n";
            $body .= 'New functions:' . "\r\n";
            $funcs = get_defined_functions();
            foreach($funcs['user'] as $func)
                if(!in_array($func, $Pancake_funcsPre['user']))
                    $body .= $func . "\r\n";
            $body .= "\r\n";
            if(\PHP_MINOR_VERSION >= 4) {
                $body .= 'New traits:' . "\r\n";
                foreach(get_declared_traits() as $trait)
                    if(!in_array($trait, $Pancake_traitsPre))
                        $body .= $trait . "\r\n";
                $body .= "\r\n";
            }
            $body .= 'New includes:' . "\r\n"; 
            foreach(get_included_files() as $include)
                if(!in_array($include, $Pancake_includesPre))
                    $body .= $include . "\r\n";
            
            $body .= "\r\nContent Body: \r\n";
            $body .= $contents;
            $contents = $body;
            $Pancake_request->setHeader('Content-Type', 'text/plain');
            $Pancake_request->setAnswerCode(200);
        }
        
        if(ini_get('expose_php') == true)
            $Pancake_request->setHeader('X-Powered-By', 'PHP/' . PHP_VERSION);
        
        // Update RequestObject and send it to RequestWorker
        if(!$invalidRequest)
            $Pancake_request->setAnswerBody($contents);
        SharedMemory::put($Pancake_request, $Pancake_message);
        IPC::send($Pancake_request->getRequestWorker()->IPCid, 1);
        
        // Reset ini-settings
        ini_set(null, null, true);
        
        // Clean
        PHPFunctions\OutputBuffering\endClean();

        @session_write_close();
                                   
        // We're cleaning the globals here because PHP 5.4 is likely to crash when having an instance of a non-existant class
        cleanGlobals($Pancake_exclude);
        
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
        
        if($Pancake_currentThread->vHost->shouldAutoDelete('interfaces'))
            foreach(get_declared_interfaces() as $interface) {
                if(!in_array($interface, $Pancake_interfacesPre) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($interface, 'interfaces'))
                    dt_remove_interface($interface);
            }
        
        if($Pancake_currentThread->vHost->shouldAutoDelete('functions'))
            foreach($funcsPost['user'] as $func) {
                if(!in_array($func, $Pancake_funcsPre['user']) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($func, 'functions'))
                    dt_remove_function($func);
            }
        
        if($Pancake_currentThread->vHost->shouldAutoDelete('classes'))
            foreach(get_declared_classes() as $class) {
                if(!in_array($class, $Pancake_classesPre) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($class, 'classes'))
                    dt_remove_class($class);
            }
        
        if($Pancake_currentThread->vHost->shouldAutoDelete('constants'))
            foreach($constsPost['user'] as $const => $constValue) {
                if(!array_key_exists($const, $Pancake_constsPre['user']) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($const, 'constants'))
                    dt_remove_constant($const);
            }
            
        if($Pancake_currentThread->vHost->shouldAutoDelete('includes'))
            foreach(get_included_files() as $include) {
                if(!in_array($include, $Pancake_includesPre) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($include, 'includes'))
                    dt_remove_include($include);
            } 
        
        if(\PHP_MINOR_VERSION >= 4 && $Pancake_currentThread->vHost->shouldAutoDelete('traits')) {
            foreach(get_declared_traits() as $trait) {
                if(!in_array($trait, $Pancake_traitsPre) && !$Pancake_currentThread->vHost->isAutoDeleteExclude($trait, 'traits'))
                    dt_remove_trait($trait);
            }
            unset($Pancake_traitsPre);
        }
        
        foreach($Pancake_currentThread->vHost->getForcedDeletes() as $delete) {
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
        
        dt_clear_cache();
        
        gc_collect_cycles();
    }
    
    dt_throw_exit_exception(false);      
?>

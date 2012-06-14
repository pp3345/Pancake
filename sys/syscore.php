<?php
    
    /****************************************************************/
    /* Pancake                                                      */
    /* syscore.php                                                  */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(defined('Pancake\PANCAKE'))
        exit;
    
    //declare(ticks = 5);
    const VERSION = '0.3';
    const PANCAKE = true;
    const REQUEST_WORKER_TYPE = 1;
    const PHP_WORKER_TYPE = 2;
    const SYSTEM = 1;
    const REQUEST = 2;
    define('Pancake\ERROR_REPORTING', \E_COMPILE_ERROR | \E_COMPILE_WARNING | \E_CORE_ERROR | \E_CORE_WARNING | \E_ERROR | \E_PARSE | \E_RECOVERABLE_ERROR | \E_USER_ERROR | \E_USER_WARNING | \E_WARNING);
    
    // Include files necessary to run Pancake
    require_once 'configuration.class.php';
    require_once 'functions.php';
    require_once 'thread.class.php';
    require_once 'threads/requestWorker.class.php';
    require_once 'threads/phpWorker.class.php';
    require_once 'HTTPRequest.class.php';
    require_once 'invalidHTTPRequest.exception.php';
    require_once 'sharedMemory.class.php';
    require_once 'IPC.class.php';
    require_once 'vHost.class.php';
    require_once 'authenticationFile.class.php';
    require_once 'mime.class.php';
    
    // Set error reporting
    error_reporting(ERROR_REPORTING);
    
    // Set error handler
    set_error_handler('Pancake\errorHandler');
    
    // Get start options 
    $startOptions = getopt('-h', array('benchmark::', 'debug', 'daemon', 'live', 'help'));
    
    // Display help if requested
    if(isset($startOptions['h']) || isset($startOptions['help'])) {
        echo 'Pancake '.VERSION."\n";
        echo '2012 Yussuf "pp3345" Khalil'."\n";
        echo "\n";
        echo 'Pancake is a simple and lightweight HTTP-server. These are the start-options you may use:'."\n";
        echo '-h --help                          Displays this help'."\n";
        echo '--benchmark=100                    Runs Pancake in benchmark-mode - It will then benchmark the speed needed for handling requests. You can specify a custom amount for the number of requests to be executed'."\n";
        echo '--debug                            Runs Pancake in debug-mode - It will output further information on errors, which may prove helpful for developers'."\n";
        echo '--daemon                           Runs Pancake in daemon-mode - It will then run as a process in background'."\n";
        echo '--live                             Runs Pancake in live-mode - It will not run in background and output every piece of information directly to the user'."\n";
        exit;
    }
    
    out('Loading Pancake '.VERSION.'...', SYSTEM, false);
    
    // Check for POSIX-compliance
    if(!is_callable('posix_getpid')) {
        out('Pancake can\'t run on this system. Either your operating system isn\'t POSIX-compliant or PHP was compiled with --disable-posix', SYSTEM, false);
        abort();
    }
    
    // Check for available PCNTL-functions
    if(!extension_loaded('pcntl')) {
        out('Pancake can\'t run on this system. You need to recompile PHP with --enable-pcntl', SYSTEM, false);
        abort();
    }
    
    // Check if PECL-extension for YAML-support is installed
    if(!extension_loaded('yaml')) {
        out('You need to install the PECL-extension for YAML-support in order to run Pancake.', SYSTEM, false);
        abort();
    }
    
    // Check for System V
    if(!extension_loaded('sysvmsg') || !extension_loaded('sysvshm')) {
        out('You need to compile PHP with --enable-sysvmsg and --enable-sysvshm in order to run Pancake.', SYSTEM, false);
        abort();
    }
    
    // Check if socket-extension is available
    if(!extension_loaded('sockets')) {
        out('You need to compile PHP with support for sockets (--enable-sockets) in order to run Pancake.', SYSTEM, false);
        abort();
    }
    
    // Check for DeepTrace
    if(!extension_loaded('DeepTrace')) {
        out('You need to run Pancake with the DeepTrace-extension, which is delivered with Pancake. Just run pancake.sh.', SYSTEM, false);
        abort();
    }
    
    // Check for root-user
    if(posix_getuid() !== 0) {
        out('You need to run Pancake as root.', SYSTEM, false);
        abort();
    }
    
    // Load configuration
    Config::load();
    
    // Remove some PHP-functions and -constants in order to provide ability to run PHP under Pancake
    dt_remove_function('php_sapi_name');
    dt_remove_function('setcookie');
    dt_remove_function('setrawcookie');
    dt_remove_function('header');
    dt_remove_function('headers_sent');
    dt_remove_function('headers_list');
    dt_remove_function('header_remove');
    dt_remove_function('is_uploaded_file');
    dt_remove_function('move_uploaded_file');
    dt_remove_function('filter_input');
    dt_remove_function('filter_has_var');
    dt_remove_function('filter_input_array');
    if(function_exists('http_response_code')) dt_remove_function('http_response_code');
    if(function_exists('header_register_callback')) dt_remove_function('header_register_callback');
    dt_rename_function('phpinfo', 'Pancake\PHPFunctions\phpinfo');  
    dt_rename_function('ob_get_level', 'Pancake\PHPFunctions\OutputBuffering\getLevel');
    dt_rename_function('ob_end_clean', 'Pancake\PHPFunctions\OutputBuffering\endClean');
    dt_rename_function('ob_end_flush', 'Pancake\PHPFunctions\OutputBuffering\endFlush');
    dt_rename_function('ob_flush', 'Pancake\PHPFunctions\OutputBuffering\flush');
    dt_rename_function('ob_get_flush', 'Pancake\PHPFunctions\OutputBuffering\getFlush');
    dt_rename_function('session_start', 'Pancake\PHPFunctions\sessionStart');
    dt_rename_function('ini_set', 'Pancake\PHPFunctions\setINI');
    dt_rename_function('debug_backtrace', 'Pancake\PHPFunctions\debugBacktrace');
    dt_rename_function('debug_print_backtrace', 'Pancake\PHPFunctions\debugPrintBacktrace');
    dt_rename_function('register_shutdown_function', 'Pancake\PHPFunctions\registerShutdownFunction');
    dt_remove_constant('PHP_SAPI'); 
    
    dt_show_plain_info(false);
    
    // Set thread title 
    dt_set_proctitle('Pancake HTTP-Server '.VERSION);
    
    // Set PANCAKE_DEBUG_MODE
    if(isset($startOptions['debug']) || Config::get('main.debugmode') === true)
        define('Pancake\DEBUG_MODE', true);
    else
        define('Pancake\DEBUG_MODE', false);
        
    if(DEBUG_MODE === true)
        out('Debugging enabled');
        
    out('Basic configuration initialized', SYSTEM, true, true);
           
    // Check if configured user exists
    if(posix_getpwnam(Config::get('main.user')) === false || posix_getgrnam(Config::get('main.group')) === false) {
        out('The configured user/group doesn\'t exist.', SYSTEM, false);
        abort();
    }
    
    // Handle signals
    //pcntl_signal(SIGUSR2, 'Pancake\abort');
    //pcntl_signal(SIGTERM, 'Pancake\abort');
    //pcntl_signal(SIGINT, 'Pancake\abort');      
    
    // Daemonize
    if(isset($startOptions['daemon'])) {
        ignore_user_abort(true);
        
        if(is_resource(\STDIN))  fclose(\STDIN);
        if(is_resource(\STDOUT)) fclose(\STDOUT);
        if(is_resource(\STDERR)) fclose(\STDERR);
        fopen('/dev/null', 'r');
        fopen('/dev/null', 'r');
        fopen('/dev/null', 'r');
        define('Pancake\DAEMONIZED', true);
    } else
        define('Pancake\DAEMONIZED', false);
    
    // Check for ports to listen on
    if(!Config::get('main.listenports')) {
        out('You need to specify at least one port for Pancake to listen on. We recommend port 80.', SYSTEM, false);
        abort();
    }       
    
    // Check if configured worker-amounts are OK
    if(Config::get('main.requestworkers') < 1) {
        out('You need to specify an amount of request-workers greater or equal to 1.', SYSTEM, false);
        abort();
    }
    
    // Check for configured vhosts
    if(!Config::get('vhosts')) {
        out('You need to define at least one virtual host.', SYSTEM, false);
        abort();
    }
    
    // Load Shared Memory and IPC
    SharedMemory::create();
    IPC::create();  
    
    // Load MIME-types
    MIME::load();
    
    // Dirty workaround for error-logging (else may get permission denied)
    trigger_error('Nothing', \E_USER_NOTICE);
    
    // Create sockets
    // IPv6
    foreach((array) Config::get('main.ipv6') as $interface) { 
        foreach(Config::get('main.listenports') as $listenPort) {
            // Create socket
            $socket = socket_create(\AF_INET6, \SOCK_STREAM, \SOL_TCP);
            
            // Set option to reuse local address
            socket_set_option($socket, \SOL_SOCKET, \SO_REUSEADDR, 1);
            
            // Bind to interface
            if(!socket_bind($socket, $interface, $listenPort)) {
                trigger_error('Failed to create socket for '.$interface.' (IPv6) on port '.$listenPort, \E_USER_WARNING);
                continue;
            } 
            
            // Start listening  
            socket_listen($socket);
            socket_set_nonblock($socket);
            $Pancake_sockets[] = $socket;
        }
    }
    
    out('Listening on ' . count(Config::get('main.ipv6')) . ' IPv6 network interfaces', SYSTEM, true, true);
    
    // IPv4
    foreach((array) Config::get('main.ipv4') as $interface) {
        foreach(Config::get('main.listenports') as $listenPort) {
            // Create socket
            $socket = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
            
            // Set option to reuse local address
            socket_set_option($socket, \SOL_SOCKET, \SO_REUSEADDR, 1);
            
            // Bind to interface
            if(!socket_bind($socket, $interface, $listenPort)) {
                trigger_error('Failed to create socket for '.$interface.' (IPv4) on port '.$listenPort, \E_USER_WARNING);
                continue;
            } 
            
            // Start listening  
            socket_listen($socket);
            socket_set_nonblock($socket);
            $Pancake_sockets[] = $socket;
        }
    }
    
    out('Listening on ' . count(Config::get('main.ipv4')) . ' IPv4 network interfaces', SYSTEM, true, true);
    
    // Check if any sockets are available
    if(!$Pancake_sockets) {
        trigger_error('No sockets available to listen on', \E_USER_ERROR);
        abort();
    }
    
    // Load vHosts
    foreach(Config::get('vhosts') as $name => $config) {
        try {
            $vHosts[$name] = new vHost($name);
        } catch(\Exception $exception) {
            unset($vHosts[$name]);
            trigger_error('Configuration of vHost "'.$name.'" is invalid: '.$exception->getMessage(), \E_USER_WARNING);
        }
    }
    
    // Check if any vHosts are available
    if(!$vHosts) {
        trigger_error('No vHosts available.', \E_USER_ERROR);
        abort();
    }
    
    // Check if the default vHost is set
    if(!(vHost::getDefault() instanceof vHost)) {
        out('You need to specify a default vHost. (Set isdefault: true)', SYSTEM, false);
        abort();
    }
    
    // Set vHosts by Names
    foreach($vHosts as $vHost) {
        foreach($vHost->getListen() as $address)
            $Pancake_vHosts[$address] = $vHost;
    }
    
    pcntl_sigprocmask(\SIG_BLOCK, array(\SIGUSR1));
    
    // We're doing this in two steps so that all vHosts will be displayed in phpinfo()
    foreach($vHosts as $vHost) {
        for($i = 0;$i < $vHost->getPHPWorkerAmount();$i++) {
            cleanGlobals(array('i', 'vHosts', 'vHost'));
            $thread = new PHPWorker($vHost);
            if($Pancake_currentThread) {
                require $thread->codeFile;
                exit;
            }
            pcntl_sigtimedwait(array(\SIGUSR1), $x, Config::get('main.workerboottime'));
            if(!$x) {
                $thread->kill();
                out('Failed to boot worker ' . $thread->friendlyName . ' in time - Aborting');
                abort();
            }
            //$phpWorkers[] = $thread;
        }
    }
    
    // Debug-output
    out('Loaded '.count($vHosts).' vHosts', SYSTEM, true, true);
                       
    cleanGlobals();
    
    // Create RequestWorkers
    for($i = 0;$i < Config::get('main.requestworkers');$i++) {
        $thread = new RequestWorker(); 
        pcntl_sigtimedwait(array(\SIGUSR1), $x, Config::get('main.workerboottime'));
        if(!$x) {
            $thread->kill();
            out('Failed to boot worker ' . $thread->friendlyName . ' in time - Aborting');
            abort();
        }
        //$requestWorkers[] = $thread;
    }
        
    out('Created '.Config::get('main.requestworkers').' RequestWorkers', SYSTEM, true, true);
    
    out('Ready');
    
    // Clean
    cleanGlobals();
    gc_collect_cycles();
    
    // Set blocking mode for some signals
    pcntl_sigprocmask(\SIG_BLOCK, array(\SIGCHLD, \SIGINT, \SIGUSR2, \SIGTERM));
    
    // Don't do anything except if one of the children died
    while(true) {
        pcntl_sigwaitinfo(array(\SIGCHLD, \SIGINT, \SIGUSR2, \SIGTERM), $info);
        
        // pcntl_sigwaitinfo() might be interrupted in some cases
        if(!$info)
            continue;
        
        switch($info['signo']) {
            case \SIGCHLD:
                // Destroy zombies
                pcntl_wait($x, \WNOHANG);
                
                $thread = Thread::get($info['pid']);
                if(IPC::get(MSG_IPC_NOWAIT, 9999)) {
                    out('Worker ' . $thread->friendlyName . ' reached request-limit - Rebooting worker', REQUEST, true, true);
                } else
                    out('Detected crash of worker ' . $thread->friendlyName . ' - Rebooting worker');
                
                // PHPWorkers need to be run in global scope
                if($thread instanceof PHPWorker) {
                    $thread->start(false);
                    if($Pancake_currentThread) {
                        require $thread->codeFile;
                        exit;
                    }
                    
                    // Send error to RequestWorker
                    $ipcStatus = IPC::status();
                    if(($thread = Thread::get($ipcStatus['msg_lspid'])) instanceof RequestWorker)
                        IPC::send($thread->IPCid, 500);
                } else
                    $thread->start();
            break;
            case \SIGINT:
            case \SIGTERM:
            case \SIGUSR2:
                abort();
            break;
        }
        
        unset($info);
    }
?>

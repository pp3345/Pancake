<?php
    
    /****************************************************************/
    /* Pancake                                                      */
    /* syscore.php                                                  */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    declare(ticks = 5);
    const PANCAKE_VERSION = '0.2';
    const PANCAKE_HTTP = true;
    const PANCAKE_REQUEST_WORKER_TYPE = 1;
    const PANCAKE_PHP_WORKER_TYPE = 2;
    
    // Include files necessary to run Pancake
    require_once 'functions.php';
    require_once 'thread.class.php';
    require_once 'configuration.class.php';
    require_once 'threads/requestWorker.class.php';
    require_once 'threads/phpWorker.class.php';
    require_once 'HTTPRequest.class.php';
    require_once 'invalidHTTPRequest.exception.php';
    require_once 'sharedMemory.class.php';
    require_once 'IPC.class.php';
    require_once 'vHost.class.php';
    require_once 'authenticationFile.class.php';
    
    // Set error reporting
    error_reporting(E_ALL ^ E_DEPRECATED ^ E_NOTICE);
    
    // Set error handler
    set_error_handler('Pancake_errorHandler');
    
    // Get start options 
    $startOptions = getopt('-h', array('benchmark::', 'debug', 'daemon', 'live', 'help'));
    
    // Display help if requested
    if(isset($startOptions['h']) || isset($startOptions['help'])) {
        echo 'Pancake '.PANCAKE_VERSION."\n";
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
    
    Pancake_out('Loading Pancake '.PANCAKE_VERSION.'...', SYSTEM, false);
    
    // Check for POSIX-compliance
    if(!is_callable('posix_getpid')) {
        Pancake_out('Pancake can\'t run on this system. Either your operating system isn\'t POSIX-compliant or PHP was compiled with --disable-posix', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check for available PCNTL-functions
    if(!extension_loaded('pcntl')) {
        Pancake_out('Pancake can\'t run on this system. You need to recompile PHP with --enable-pcntl', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check if PECL-extension for YAML-support is installed
    if(!extension_loaded('yaml')) {
        Pancake_out('You need to install the PECL-extension for YAML-support in order to run Pancake.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check for System V
    if(!extension_loaded('sysvmsg') || !extension_loaded('sysvshm')) {
        Pancake_out('You need to compile PHP with --enable-sysvmsg and --enable-sysvshm in order to run Pancake.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check for DeepTrace
    if(!extension_loaded('DeepTrace')) {
        Pancake_out('You need to run Pancake with the DeepTrace-extension, which is delivered with Pancake. Just run pancake.sh.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check for root-user
    if(posix_getuid() !== 0) {
        Pancake_out('You need to run Pancake as root.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Load configuration
    Pancake_Config::load();
    
    // Remove some PHP-functions and -constants in order to provide ability to run PHP under Pancake
    dt_remove_function('php_sapi_name');
    dt_remove_function('setcookie');
    dt_remove_function('header');
    dt_remove_function('headers_sent');
    dt_remove_function('headers_list');
    dt_remove_function('header_remove');
    dt_rename_function('phpinfo', 'Pancake_phpinfo_orig');
    
    // Set thread title 
    dt_set_proctitle('Pancake HTTP-Server '.PANCAKE_VERSION);
    
    // Set PANCAKE_DEBUG_MODE
    if(isset($startOptions['debug']) || Pancake_Config::get('main.debugmode') === true)
        define('PANCAKE_DEBUG_MODE', true);
    
    Pancake_out('Basic configuration loaded');
    if(PANCAKE_DEBUG_MODE === true)
        Pancake_out('Running in debugmode');
        
    // Check if configured user exists
    if(posix_getpwnam(Pancake_Config::get('main.user')) === false || posix_getgrnam(Pancake_Config::get('main.group')) === false) {
        Pancake_out('The configured user/group doesn\'t exist.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Handle signals
    pcntl_signal(SIGUSR2, 'Pancake_abort');
    pcntl_signal(SIGTERM, 'Pancake_abort');
    pcntl_signal(SIGINT, 'Pancake_abort');
    
    // Daemonize
    if(isset($startOptions['daemon'])) {
        ignore_user_abort(true);
        
        if(is_resource(STDIN))  fclose(STDIN);
        if(is_resource(STDOUT)) fclose(STDOUT);
        if(is_resource(STDERR)) fclose(STDERR);
        define('PANCAKE_DAEMONIZED', true);
    }
    
    // Check for ports to listen on
    if(!Pancake_Config::get('main.listenports')) {
        Pancake_out('You need to specify at least one port for Pancake to listen on. We recommend port 80.', SYSTEM, false);
        Pancake_abort();
    }       
    
    // Check if configured worker-amounts are OK
    if(Pancake_Config::get('main.requestworkers') < 1) {
        Pancake_out('You need to specify an amount of request-workers greater or equal to 1.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Check for configured vhosts
    if(!Pancake_Config::get('vhosts')) {
        Pancake_out('You need to define at least one virtual host.', SYSTEM, false);
        Pancake_abort();
    }
    
    // Load Shared Memory and IPC
    Pancake_SharedMemory::create();
    Pancake_IPC::create();  
    
    // Dirty workaround for error-logging (else may get permission denied)
    trigger_error('Nothing', E_USER_NOTICE);
    
    // Create sockets
    foreach(Pancake_Config::get('main.listenports') as $listenPort) {
        // Create socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        // Set option to reuse local address
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        // Bind to all interfaces
        if(!socket_bind($socket, 0, $listenPort)) {
            trigger_error('Failed to create socket on port '.$listenPort, E_USER_WARNING);
            continue;
        } 
        
        // Start listening  
        socket_listen($socket);
        socket_set_nonblock($socket);
        $Pancake_sockets[$listenPort] = $socket;
    }
    
    // Check if any sockets are available
    if(!$Pancake_sockets) {
        trigger_error('No sockets available to listen on', E_USER_ERROR);
        Pancake_abort();
    }
    
    // Load vHosts
    foreach(Pancake_Config::get('vhosts') as $name => $config) {
        try {
            $vHosts[$name] = new Pancake_vHost($name);
        } catch(Exception $exception) {
            unset($vHosts[$name]);
            trigger_error('Configuration of vHost "'.$name.'" is invalid: '.$exception->getMessage(), E_USER_WARNING);
        }
    }
    
    // Check if any vHosts are available
    if(!$vHosts) {
        trigger_error('No vHosts available.', E_USER_ERROR);
        Pancake_abort();
    }
    
    // Set vHosts by Names and create PHP-workers
    foreach($vHosts as $vHost) {
        foreach($vHost->getListen() as $address)
            $Pancake_vHosts[$address] = $vHost;
        for($i = 0;$i < $vHost->getPHPWorkerAmount();$i++) {
            $thread = new Pancake_PHPWorker($vHost);
            if($thread->startedManually) {
                require $thread->codeFile;
                exit;
            }
        }
    }
    
    // Debug-outpt
    Pancake_out('Loaded '.count($vHosts).' vHosts', SYSTEM, false, true);
        
    // Create RequestWorkers
    for($i = 0;$i < Pancake_Config::get('main.requestworkers');$i++)
        $requestWorkers[] = new Pancake_RequestWorker();
    Pancake_out('Created '.Pancake_Config::get('main.requestworkers').' RequestWorkers', SYSTEM, true, true);
    
    Pancake_out('Ready for connections');
    
    // Clean
    unset($requestWorkers);
    unset($socketWorkers);
    unset($user);
    unset($group);
    unset($Pancake_sockets);
    unset($startOptions);
    unset($Pancake_currentThread);
    unset($port);
    unset($config);
    unset($name);
    unset($i);
    unset($socket);
    unset($_SERVER);
    unset($_GET);
    unset($_POST);
    unset($_COOKIE);
    unset($_FILES);
    unset($GLOBALS);
    unset($argv);
    unset($argc);
    
    // Good night
    while(true) {
        sleep(1);
    }
?>

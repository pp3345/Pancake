<?php 

    /****************************************************************/
    /* Pancake                                                      */
    /* syscore.php                                                  */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    namespace Pancake;

    if(defined('Pancake\PANCAKE'))
        exit;

    if(!extension_loaded('Pancake')) {
    	echo "Pancake natives not loaded. Please run pancake.sh\n";
    	exit;
    }

    const PANCAKE = true;

    // Include files necessary to run Pancake
    require_once 'sfYamlParser.class.php';
    require_once 'configuration.class.php';
    require_once 'coreFunctions.php';
    require_once 'thread.class.php';
    require_once 'vHost.class.php';

    // Set error reporting
    error_reporting(ERROR_REPORTING);

    // Get start options
    $startOptions = getopt('-h', array('debug', 'daemon', 'help', 'config:', 'use-malloc', 'pidfile:'));

    // Display help if requested
    if(isset($startOptions['h']) || isset($startOptions['help'])) {
        echo 'Pancake HTTP Server ' . VERSION . "\n";
        echo "2012 - 2013 Yussuf Khalil\n\n";
        echo "Pancake is a fast and lightweight HTTP-server. You may append the following settings to the start command:\n\n";
        echo "-h --help                          Show this help\n";
        echo "--debug                            Enable debug mode\n";
        echo "--daemon                           Run Pancake as a system daemon\n";
        echo "--config=...                       Specify a custom configuration file path (defaults to ../conf/config.yml)\n";
        echo "--use-malloc                       Disable Zend Memory Manager (USE WITH CARE!)\n";
        echo "--pidfile=...                      When used in combination with --daemon Pancake will put its PID in this file\n\n";
        echo "Have fun with Pancake!\n";
        exit;
    }

    out('Loading Pancake ' . VERSION . '... 2012 - 2013 Yussuf Khalil', OUTPUT_SYSTEM);

    // Check for php-cli
    if(\PHP_SAPI != 'cli') {
    	out('Pancake must be executed using PHP in CLI mode. You are currently running the "' . \PHP_SAPI . '" SAPI.', OUTPUT_SYSTEM);
    	abort();
    }

    foreach(array("posix", "tokenizer", "ctype") as $extension) {
        if(!extension_loaded($extension)) {
            $missingExtensions++;
            if($missingExtensions > 1) {
                $description .= ", " . $extension;
            } else {
                $description = $extension;
            }
            $compileDescription .= " --enable-" . $extension;
        }
    }

    if(isset($missingExtensions)) {
        if($missingExtensions > 1) {
            out('You are missing the following ' . $missingExtensions . ' PHP extensions: ' . $description . ' - Please compile them as shared extensions and add them to your php.ini or recompile PHP using' . $compileDescription, OUTPUT_SYSTEM);
        } else {
            out('You are missing the PHP extension "' . $description . '" - Please compile it as a shared extension and add it to your php.ini or recompile PHP using' . $compileDescription, OUTPUT_SYSTEM);
        }
        abort();
    }

    // Check for DeepTrace
    if(!extension_loaded('DeepTrace')) {
        out('You need to run Pancake with the bundled DeepTrace extension. Just run pancake.sh.', OUTPUT_SYSTEM);
        abort();
    }

    // Check for Suhosin
    if(extension_loaded('suhosin'))
    	out('It seems that your server is running Suhosin. Although everything should work fine, Suhosin is not officially supported by Pancake. If you encounter any errors, please try deactivating Suhosin.', OUTPUT_SYSTEM);

    // Check for root-user
    if(posix_getuid() !== 0) {
        out('You need to run Pancake as root.', OUTPUT_SYSTEM);
        abort();
    }

	if(isset($startOptions['use-malloc'])) {
		out('Zend Memory Manager disabled', OUTPUT_SYSTEM);
	}

    // Load configuration
    Config::load(isset($startOptions['config']) ? $startOptions['config'] : null);

    // Open log files
    loadFilePointers();

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
    if(extension_loaded('filter')) {
        dt_remove_function('filter_input');
        dt_remove_function('filter_has_var');
        dt_remove_function('filter_input_array');
    }
    dt_remove_function('get_required_files');
    dt_remove_function('restore_error_handler');
    dt_remove_function('ini_alter');
    dt_remove_function('stream_register_wrapper');
    dt_remove_function('http_response_code');
    dt_remove_function('header_register_callback');
    dt_remove_function('session_register_shutdown');
    dt_rename_function('phpinfo', 'Pancake\PHPFunctions\phpinfo');
    dt_rename_function('ob_get_level', 'Pancake\PHPFunctions\OutputBuffering\getLevel');
    dt_rename_function('ob_end_clean', 'Pancake\PHPFunctions\OutputBuffering\endClean');
    dt_rename_function('ob_end_flush', 'Pancake\PHPFunctions\OutputBuffering\endFlush');
    dt_rename_function('ob_flush', 'Pancake\PHPFunctions\OutputBuffering\flush');
    dt_rename_function('ob_get_flush', 'Pancake\PHPFunctions\OutputBuffering\getFlush');
    dt_rename_function('ini_set', 'Pancake\PHPFunctions\setINI');
    dt_rename_function('debug_backtrace', 'Pancake\PHPFunctions\debugBacktrace');
    dt_rename_function('debug_print_backtrace', 'Pancake\PHPFunctions\debugPrintBacktrace');
    dt_rename_function('register_shutdown_function', 'Pancake\PHPFunctions\registerShutdownFunction');
    dt_rename_function('get_included_files', 'Pancake\PHPFunctions\getIncludes');
    dt_rename_function('set_error_handler', 'Pancake\PHPFunctions\setErrorHandler');
    dt_rename_function('memory_get_usage', 'Pancake\PHPFunctions\getMemoryUsage');
    dt_rename_function('memory_get_peak_usage', 'Pancake\PHPFunctions\getPeakMemoryUsage');
    dt_rename_function('get_browser', 'Pancake\PHPFunctions\getBrowser');
    dt_rename_function('error_get_last', 'Pancake\PHPFunctions\errorGetLast');
    dt_rename_function('spl_autoload_register', 'Pancake\PHPFunctions\registerAutoload');
    dt_rename_function('register_tick_function', 'Pancake\PHPFunctions\registerTickFunction');
    dt_rename_function('stream_wrapper_register', 'Pancake\PHPFunctions\streamWrapperRegister');
    dt_rename_method('ReflectionFunction', 'isDisabled', 'Pancake_isDisabledOrig');
    if(extension_loaded('session')) {
        dt_rename_function('session_regenerate_id', 'Pancake\PHPFunctions\sessionRegenerateID');
        dt_rename_function('session_start', 'Pancake\PHPFunctions\sessionStart');
        dt_rename_function('session_destroy', 'Pancake\PHPFunctions\sessionDestroy');
        dt_rename_function('session_set_save_handler', 'Pancake\PHPFunctions\setSessionSaveHandler');
        dt_rename_function('session_id', 'Pancake\PHPFunctions\sessionID');
    }
    dt_remove_constant('PHP_SAPI');

    dt_phpinfo_mode(\DT_PHPINFO_HTML);

    // Set PANCAKE_DEBUG_MODE
    if(isset($startOptions['debug']) || Config::get('main.debugmode') === true) {
        define('Pancake\DEBUG_MODE', true);
        out('Debugging enabled', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);
    } else
        define('Pancake\DEBUG_MODE', false);

    out('Basic configuration initialized', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);

    // Check if configured user exists
    if(posix_getpwnam(Config::get('main.user')) === false || posix_getgrnam(Config::get('main.group')) === false) {
        out('The configured user/group doesn\'t exist.');
        abort();
    }

    // Check for ports to listen on
    if(!Config::get('main.listenports')) {
        out('You need to specify at least one port for Pancake to listen on. We recommend port 80.');
        abort();
    }

    // Check if configured worker-amounts are OK
    if(Config::get('main.requestworkers') < 1) {
        out('You need to specify an amount of request-workers greater or equal to 1.');
        abort();
    }

    // Check for configured vhosts
    if(!Config::get('vhosts')) {
        out('You need to define at least one virtual host.');
        abort();
    }

	// Daemonize
    if(isset($startOptions['daemon'])) {
        ignore_user_abort(true);

		$pid = Fork();
		
		if($pid == -1) {
			out('Failed to daemonize', OUTPUT_SYSTEM);
			abort();
		} else if($pid) {
			// Parent
			exit;
		}

        if(isset($startOptions['pidfile'])) {
            file_put_contents($startOptions['pidfile'], posix_getpid());
        }

		if(is_resource(\STDIN))  fclose(\STDIN);
        if(is_resource(\STDOUT)) fclose(\STDOUT);
        if(is_resource(\STDERR)) fclose(\STDERR);
        fopen('/dev/null', 'r');
        fopen('/dev/null', 'r');
        fopen('/dev/null', 'r');

        define('Pancake\DAEMONIZED', true);
    } else
        define('Pancake\DAEMONIZED', false);
		
	// Set thread title
    dt_set_proctitle('Pancake HTTP Server ' . VERSION);

    // Create sockets
    // IPv6
    foreach((array) Config::get('main.ipv6') as $interface) {
        foreach(\array_merge((array) Config::get('main.listenports'), (array) Config::get('tls.ports')) as $listenPort) {
            // Create socket
            $socket = Socket(\AF_INET6, \SOCK_STREAM, \SOL_TCP);

            // Set option to reuse local address
            ReuseAddress($socket);

            // Bind to interface
            if(!Bind($socket, \AF_INET6, $interface, $listenPort)) {
                trigger_error('Failed to create socket for ' . $interface . ' (IPv6) on port ' . $listenPort, \E_USER_WARNING);
                continue;
            }

            // Start listening
            Listen($socket, Config::get('main.socketbacklog'));
            SetBlocking($socket, false);
            $Pancake_sockets[$socket] = $socket;
        }
        if($interface == '::0' && $Pancake_sockets)
        	goto socketsCreated;
    }

    out('Listening on ' . count(Config::get('main.ipv6')) . ' IPv6 network interfaces', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);

    // IPv4
    foreach((array) Config::get('main.ipv4') as $interface) {
        foreach(\array_merge((array) Config::get('main.listenports'), (array) Config::get('tls.ports')) as $listenPort) {
            // Create socket
            $socket = Socket(\AF_INET, \SOCK_STREAM, \SOL_TCP);

            // Set option to reuse local address
            ReuseAddress($socket);

            // Bind to interface
            if(!Bind($socket, \AF_INET, $interface, $listenPort)) {
                trigger_error('Failed to create socket for ' . $interface . ' (IPv4) on port ' . $listenPort, \E_USER_WARNING);
                continue;
            }

            // Start listening
            Listen($socket, Config::get('main.socketbacklog'));
            SetBlocking($socket, false);
            $Pancake_sockets[$socket] = $socket;
        }
    }

    out('Listening on ' . count(Config::get('main.ipv4')) . ' IPv4 network interfaces', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);

    // Check if any sockets are available
    if(!$Pancake_sockets) {
        trigger_error('No sockets available to listen on', \E_USER_ERROR);
        abort();
    }

    socketsCreated:

    // Load vHosts
    foreach(Config::get('vhosts') as $name => $config) {
        try {
            $vHosts[$name] = new vHost($name);
            if(!$vHosts[$name]->enabled) {
                unset($vHosts[$name]);
                continue;
            }
            if($vHosts[$name]->isDefault)
            	$haveDefault = true;
        } catch(\Exception $exception) {
            unset($vHosts[$name]);
            trigger_error('Configuration of vHost "' . $name . '" is invalid: ' . $exception->getMessage(), \E_USER_WARNING);
        }
    }

    // Check if any vHosts are available
    if(!$vHosts) {
        trigger_error('No vHosts available.', \E_USER_ERROR);
        abort();
    }

    // Check if the default vHost is set
    if(!isset($haveDefault)) {
        out('You need to specify a default vHost. (Set isdefault: true)');
        abort();
    }

    // Set vHosts by Names
    /*foreach($vHosts as $vHost) {
        foreach($vHost->listen as $address)
            $Pancake_vHosts[$address] = $vHost;
    }*/

    $Pancake_vHosts = $vHosts;

    SigProcMask(\SIG_BLOCK, array(\SIGUSR1));

    // We're doing this in two steps so that all vHosts will be displayed in phpinfo()
    foreach($vHosts as $vHost) {
    	if($vHost->phpSocket)
        	$Pancake_phpSockets[$vHost->phpSocket] = $vHost->phpSocket;

        for($i = 0;$i < $vHost->phpWorkers;$i++) {
            cleanGlobals(array('i', 'vHosts', 'vHost', 'Pancake_phpSockets'));

            require_once 'threads/phpWorker.class.php';

            $thread = new PHPWorker($vHost);
            if(isset($Pancake_currentThread)) {
                require $thread->codeFile;
                goto do_exit;
            }
            if(Config::get('main.waitphpworkerboot')) {
	            SigWaitInfo(array(\SIGUSR1), $x, (int) Config::get('main.workerboottime'));
	            if(!$x) {
	                $thread->kill();
	                out('Failed to boot ' . $thread->friendlyName . ' in time - Aborting');
	                abort();
	            }
            }
            if(DEBUG_MODE === true)
                out('PID of ' . $thread->friendlyName . ': ' . $thread->pid);
        }
    }

    // Debug-output
    out('Loaded '.count($vHosts).' vHosts', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);

    cleanGlobals(array('Pancake_phpSockets'));

    require_once 'threads/requestWorker.class.php';

    // Create RequestWorkers
    for($i = 0;$i < Config::get('main.requestworkers');$i++) {
        $thread = new RequestWorker();
        if($thread->start() === "THREAD_EXIT")
        	goto do_exit;
        SigWaitInfo(array(\SIGUSR1), $x, (int) Config::get('main.workerboottime'));
        if(!$x) {
            $thread->kill();
            out('Failed to boot ' . $thread->friendlyName . ' in time - Aborting');
            abort();
        }
        if(DEBUG_MODE === true)
                out('PID of ' . $thread->friendlyName . ': ' . $thread->pid);
    }

    out('Created '.Config::get('main.requestworkers').' RequestWorkers', OUTPUT_SYSTEM | OUTPUT_LOG | OUTPUT_DEBUG);

    out('Ready');

    // Clean
    cleanGlobals(array('Pancake_phpSockets'));
    gc_collect_cycles();

    // Set blocking mode for some signals
    SigProcMask(\SIG_BLOCK, array(\SIGCHLD, \SIGINT, \SIGUSR2, \SIGTERM, \SIGHUP));

    // Don't do anything except if one of the children died or Pancake gets the signal to shutdown
    while(true) {
        SigWaitInfo(array(\SIGCHLD, \SIGINT, \SIGUSR2, \SIGTERM, \SIGHUP), $info);

        // SigWaitInfo() might be interrupted in some cases
        if(!$info)
            continue;

        switch($info['signo']) {
            case \SIGCHLD:
                // Destroy zombies
                Wait($x, \WNOHANG);

                $thread = Thread::get($info['pid']);
                if(Read($thread->localSocket, 17) == "EXPECTED_SHUTDOWN") {
                    out($thread->friendlyName . ' requested reboot', OUTPUT_DEBUG | OUTPUT_SYSTEM | OUTPUT_LOG);
                } else
                    out('Detected crash of ' . $thread->friendlyName . ' - Rebooting worker');

                // PHPWorkers need to be run in global scope
                if($thread instanceof PHPWorker) {
                    $thread->start(false);
                    if(isset($Pancake_currentThread)) {
                        require $thread->codeFile;
                        break 2;
                    }
                } else if($thread->start() === "THREAD_EXIT")
                	break 2;

                out('New PID of ' . $thread->friendlyName . ': ' . $thread->pid, OUTPUT_DEBUG | OUTPUT_SYSTEM);
                break;
            case \SIGINT:
            case \SIGTERM:
            case \SIGUSR2:
                abort(true);
            	break 2;
            case \SIGHUP:
                loadFilePointers();
                foreach(Thread::getAll() as $thread) {
                    if(isset($thread->localSocket))
                        Write($thread->localSocket, "LOAD_FILE_POINTERS");
                }
        }

        unset($info);
    }

    do_exit:
?>

<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* sapi.php                                                     */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    /* See php.net for further documentation on the SAPI-functions */

    if(Pancake\PANCAKE !== true)
        exit;
    
    const PHP_SAPI = 'pancake';
    
    function php_sapi_name() {
        return 'pancake';
    } 
    
    function pancake_logo_guid() {
        return 'PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B';
    }
    
    function setcookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        return Pancake\vars::$Pancake_request->setCookie($name, $value, $expire, $path, $domain, $secure, $httponly); 
    }
    
    function setrawcookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        return Pancake\vars::$Pancake_request->setCookie($name, $value, $expire, $path, $domain, $secure, $httponly, true); 
    }
    
    function header($string, $replace = true, $http_response_code = 0) {
        if(strtoupper(substr($string, 0, 5)) == 'HTTP/') {
            $data = explode(' ', $string);
            if(isset($data[1]))
            	Pancake\vars::$Pancake_request->setAnswerCode($data[1]);
        } else {
            $header = explode(':', $string, 2);
            Pancake\vars::$Pancake_request->setHeader($header[0], isset($header[1]) ? trim($header[1]) : null, $replace);
            if($header[0] == 'Location' && Pancake\vars::$Pancake_request->getAnswerCode() != 201 && substr(Pancake\vars::$Pancake_request->getAnswerCode(), 0, 1) != 3)
                Pancake\vars::$Pancake_request->setAnswerCode(302);
        }
        
        if($http_response_code)
            Pancake\vars::$Pancake_request->setAnswerCode($http_response_code);
    }
    
    function header_remove($name = null) {
        if($name)
            Pancake\vars::$Pancake_request->removeHeader($name);
        else
            Pancake\vars::$Pancake_request->removeAllHeaders();
    }
    
    function headers_sent() {
        return false;
    }
    
    function headers_list() { 
        return Pancake\vars::$Pancake_request->getAnswerHeadersArray();
    }
    
    if(PHP_MINOR_VERSION >= 4) {
        function http_response_code($response_code = 0) {  
            
            if($response_code)
                Pancake\vars::$Pancake_request->setAnswerCode($response_code);
            return Pancake\vars::$Pancake_request->getAnswerCode();
        }
        
        function header_register_callback($callback) {
            if(!is_callable($callback))
                return false;
            
            Pancake\vars::$Pancake_headerCallbacks[] = $callback;
            return true;
        }
        
        function session_register_shutdown() {
        	return register_shutdown_function('session_write_close');
        }
    }
    
    function phpinfo($what = INFO_ALL) {
        Pancake\vars::$Pancake_request->setHeader('Content-Type', 'text/html; charset=utf-8');
        
        // Get original phpinfo
        ob_start();
        if(!Pancake\PHPFunctions\phpinfo($what)) {
        	Pancake\PHPFunctions\OutputBuffering\endClean();
            return false;
        }
        $phpInfo = ob_get_contents();
        Pancake\PHPFunctions\OutputBuffering\endClean();
        
        // Modify it
        $phpInfo = str_replace('<td class="v">Command Line Interface </td>', '<td class="v">Pancake Embedded SAPI </td>', $phpInfo);
        
        if(Pancake\vars::$Pancake_request->getvHost()->exposePancakeInPHPInfo() && $what == INFO_ALL) {
            $pancakeAdd =  '<table border="0" cellpadding="3" width="600">';
            $pancakeAdd .= '<tr class="h"><td>';
            $pancakeAdd .= '<a href="http://www.pancakehttp.net">';
            if(ini_get('expose_php') && Pancake\Config::get('main.exposepancake') === true)
                $pancakeAdd .= '<img border="0" src="?=PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B" alt="Pancake Logo">';
            $pancakeAdd .= '<h1 class="p">Pancake Version ' . Pancake\VERSION .'</h1>';
            $pancakeAdd .= '</a>';
            $pancakeAdd .= '</td></tr></table><br />';
            $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
            $pancakeAdd .= '<tr><td class="e">Debug Mode</td><td class="v">'.(Pancake\Config::get('main.debugmode') ? 'enabled' : 'disabled').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">User</td><td class="v">'.Pancake\Config::get('main.user').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Group</td><td class="v">'.Pancake\Config::get('main.group').'</td></tr>';
            
            $pancakeAdd .= '<tr><td class="e">Included Configuration files</td><td class="v">';
            foreach(Pancake\Config::get('include') as $include) {
                if(isset($first)) $pancakeAdd .= ', ';
                $pancakeAdd .= $include;
                $first = true;
            }
            $pancakeAdd .= '</td></tr>';
            
            $pancakeAdd .= '<tr><td class="e">Running RequestWorkers</td><td class="v">'.Pancake\Config::get('main.requestworkers').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">RequestWorker processing limit</td><td class="v">'.(Pancake\Config::get('main.requestworkerlimit') ? Pancake\Config::get('main.requestworkerlimit') . ' requests' : '<i>no limit</i>').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Expose Pancake</td><td class="v">' . (Pancake\Config::get('main.exposepancake') ? 'enabled' : 'disabled') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Timestamps</td><td class="v">' . Pancake\Config::get('main.dateformat') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Sizeprefixes</td><td class="v">' . (Pancake\Config::get('main.sizeprefix') == 'si' ? 'SI' : 'binary') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Current vHost</td><td class="v">' . Pancake\vars::$Pancake_currentThread->vHost->getName() . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Path for temporary files</td><td class="v">' . Pancake\Config::get('main.tmppath') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Path to requestlog</td><td class="v">' . Pancake\Config::get('main.logging.request') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Path to systemlog</td><td class="v">' . Pancake\Config::get('main.logging.system') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Path to errorlog</td><td class="v">' . Pancake\Config::get('main.logging.error') . '</td></tr>';
            
            $pancakeAdd .= '</table><br/>';
            
            $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
            $pancakeAdd .= '<tr class="v"><td>';
            $pancakeAdd .= 'Pancake Copyright (c) 2012 Yussuf Khalil <br/>';
            $pancakeAdd .= 'Find out more about Pancake at <a href="http://www.pancakehttp.net">pancakehttp.net</a>';
            $pancakeAdd .= '</td></tr>';  
            $pancakeAdd .= '</table><br/>';
            
            if(Pancake\vars::$Pancake_request->getvHost()->exposePancakevHostsInPHPInfo()) {
            	$vHostsDone = array();
            	
                $pancakeAdd .= '<h1>Virtual Hosts</h1>';
                
                foreach(Pancake\vars::$Pancake_vHosts as $vHost) {
                    if(in_array($vHost->getID(), $vHostsDone))
                        continue;
                    
                    $pancakeAdd .= '<h2>'.$vHost->getName().'</h2>';
                    
                    $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
                    
                    $pancakeAdd .= '<tr><td class="e">Running PHPWorkers</td><td class="v">' . $vHost->getPHPWorkerAmount() . '</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">PHPWorker processing limit</td><td class="v">'.($vHost->getPHPWorkerLimit() ? $vHost->getPHPWorkerLimit() . ' requests' : '<i>no limit</i>').'</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">Is Default</td><td class="v">' . ($vHost->isDefault() ? 'yes' : 'no') . '</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">Document Root</td><td class="v">' . $vHost->getDocumentRoot() . '</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">GZIP-compression</td><td class="v">' . ($vHost->allowGZIPCompression() ? 'enabled' : 'disabled') . '</td></tr>';
                    if($vHost->allowGZIPCompression()) {
                        $pancakeAdd .= '<tr><td class="e">Minimum filesize for GZIP-compression</td><td class="v">' . Pancake\formatFilesize($vHost->getGZIPMinimum()) . '</td></tr>';
                        $pancakeAdd .= '<tr><td class="e">GZIP-level</td><td class="v">' . $vHost->getGZIPLevel() . '</td></tr>';
                    }
                    
                    $pancakeAdd .= '<tr><td class="e">Per-Write Limit</td><td class="v">' . Pancake\formatFilesize($vHost->getWriteLimit()) . '</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">Directory Listings</td><td class="v">' . ($vHost->allowDirectoryListings() ? 'enabled' : 'disabled') . '</td></tr>';
                    $pancakeAdd .= '<tr><td class="e">Index Files</td><td class="v">';
                    
                    unset($first);
                    foreach($vHost->getIndexFiles() as $indexFile) {
                        if(isset($first)) $pancakeAdd .= ', ';
                        $pancakeAdd .= $indexFile;
                        $first = true;
                    }
                    
                    $pancakeAdd .= '</td></tr>';
                    
                    $pancakeAdd .= '<tr><td class="e">Hosts</td><td class="v">';
                    
                    unset($first);
                    foreach($vHost->getListen() as $listen) {
                        if(isset($first)) $pancakeAdd .= ', ';
                        $pancakeAdd .= $listen;
                        $first = true;
                    }
                    
                    $pancakeAdd .= '</td></tr>';
                    
                    $pancakeAdd .= '</table><br/>';
                    
                    $vHostsDone[] = $vHost->getID();
                }
            }
            
            $phpInfo = str_replace('<div class="center">', '<div class="center">' . $pancakeAdd, $phpInfo);
        }
                                                         
        if($what == INFO_ALL || $what & INFO_ENVIRONMENT) {
            // Replace environment information
            $env = '<h2>Evnironment</h2>';
            $env .= '<table border="0" cellpadding="3" width="600">';
            $env .= '<tbody><tr class="h"><th>Variable</th><th>Value</th></tr>';
            $env .= '<tr><td class="e">USER </td><td class="v">'.Pancake\Config::get('main.user').' </td></tr>';
            $env .= '</tbody></table>';
            
            $phpInfo = preg_replace('~<h2>Environment</h2>\s*<table border="0" cellpadding="3" width="600">[\s\S]*</table>~U', $env, $phpInfo);
        }
                
        echo $phpInfo;
        return true;
    }
    
    function is_uploaded_file($filename) {
        return in_array($filename, Pancake\vars::$Pancake_request->getUploadedFileNames());
    }
    
    function move_uploaded_file($filename, $destination) {
        if(!is_uploaded_file($filename))
            return false;
        if(!rename($filename, $destination)) {
            Pancake\PHPErrorHandler(E_WARNING, "Unable to move '" . $filename . "' to '" . $destination . "'");
            return false;
        }
        return true;
    }
    
    function register_shutdown_function($callback) {
    	if(!is_callable($callback))
    		return false;
    	
        $shutdownCall = array('callback' => $callback, 'args' => array());
        
        $args = func_get_args();
        unset($args[0]);
        
        foreach($args as $arg)
            $shutdownCall['args'][] = $arg;
        
        Pancake\vars::$Pancake_shutdownCalls[] = $shutdownCall;
        
        return true;
    }
    
    function ob_get_level() {
        return Pancake\PHPFunctions\OutputBuffering\getLevel() - 1;
    }
    
    function ob_end_clean() {
        if(ob_get_level() > 0)
            return Pancake\PHPFunctions\OutputBuffering\endClean();
        Pancake\PHPErrorHandler(E_NOTICE, 'ob_end_clean(): failed to delete buffer. No buffer to delete');
        return false;
    }
    
    function ob_end_flush() {
        if(ob_get_level() > 0)
            return Pancake\PHPFunctions\OutputBuffering\endFlush();
        Pancake\PHPErrorHandler(E_NOTICE, 'ob_end_flush(): failed to delete and flush buffer. No buffer to delete or flush');
        return false;
    }
    
    function ob_get_flush() {
        if(ob_get_level() > 0)
            return Pancake\PHPFunctions\OutputBuffering\getFlush();
        Pancake\PHPErrorHandler(E_NOTICE, 'ob_get_flush(): failed to delete and flush buffer. No buffer to delete or flush');
        return false;
    }
    
    function ob_flush() {
        if(ob_get_level() > 0)
            return Pancake\PHPFunctions\OutputBuffering\flush();      
    }
    
    function session_start() {
        if(session_id());
        else if(array_key_exists(session_name(), Pancake\vars::$Pancake_request->getGETParams())) {
        	$get = Pancake\vars::$Pancake_request->getGETParams();
            session_id($get[session_name()]);
        } else if(array_key_exists(session_name(), Pancake\vars::$Pancake_request->getCookies())) {
        	$cookie = Pancake\vars::$Pancake_request->getCookies();
            session_id($cookie[session_name()]);
        }
        
        if(Pancake\PHPFunctions\sessionStart()) {
        	Pancake\vars::$sessionID = session_id();
        	return true;
        }
        
        return false;
    }
    
    function filter_input($type, $variable_name, $filter = FILTER_DEFAULT, $options = FILTER_FLAG_NONE) {
        // Create bitmask of flags
        if(is_array($options)) {
        	$flags = 0;
        	
            foreach((array) $options['flags'] as $option)
                $flags |= $option;
        } else
            $flags = $options;
        
        // Get variable
        switch($type) {
            case INPUT_GET:
                $GET = Pancake\vars::$Pancake_request->getGETParams();   
                if(!array_key_exists($variable_name, $GET))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $GET[$variable_name];
                break;
            case INPUT_POST:
                $POST = Pancake\vars::$Pancake_request->getPOSTParams();   
                if(!array_key_exists($variable_name, $POST))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $POST[$variable_name];
                break;
            case INPUT_COOKIE:
                $COOKIE = Pancake\vars::$Pancake_request->getCookies();   
                if(!array_key_exists($variable_name, $COOKIE))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $COOKIE[$variable_name];
                break;
            case INPUT_SERVER:
                $SERVER = Pancake\vars::$Pancake_request->createSERVER();   
                if(!array_key_exists($variable_name, $SERVER))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $SERVER[$variable_name];
                break;
            case INPUT_ENV:
                if(!array_key_exists($variable_name, $_ENV))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $_ENV[$variable_name];
                break;
        }
            
        return filter_var($var, $filter, $options);      
    }
    
    function filter_input_array($type, $definition = null) {
    	$endArray = $data = $filterOptions = array();
    	
        foreach($definition as $key => $options) {
        	if(!isset($options['flags']))
        		$options['flags'] = 0;
        	
            switch($type) {
                case INPUT_GET:
                    $GET = Pancake\vars::$Pancake_request->getGETParams();   
                    if(!array_key_exists($key, $GET)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $GET[$key];
                    break;
                case INPUT_POST:
                    $POST = Pancake\vars::$Pancake_request->getPOSTParams();   
                    if(!array_key_exists($key, $POST)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $POST[$key];
                    break;
                case INPUT_COOKIE:
                    $COOKIE = Pancake\vars::$Pancake_request->getCookies();   
                    if(!array_key_exists($key, $COOKIE)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $COOKIE[$key];
                    break;
                case INPUT_SERVER:
                    $SERVER = Pancake\vars::$Pancake_request->createSERVER();   
                    if(!array_key_exists($key, $SERVER)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $SERVER[$key];
                    break;
                case INPUT_ENV:
                    if(!array_key_exists($key, $_ENV)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $_ENV[$key];
                    break; 
            }
            
            $data[$key] = $var;
            $filterOptions[$key] = $options;
        }

        return array_merge(filter_var_array($data, $filterOptions), $endArray);
    }
    
    function filter_has_var($type, $variable_name) {
        switch($type) {
            case INPUT_GET:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getGETParams());
            case INPUT_POST:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getPOSTParams());
            case INPUT_COOKIE:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getCookies());
            case INPUT_SERVER:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->createSERVER());
            case INPUT_ENV:
                return array_key_exists($variable_name, $_ENV);
            default:
                return false;
        }
    }
    
    function ini_set($varname, $newvalue, $reset = false) {
        static $settings = array();
        if($reset === true) {
            foreach($settings as $varname => $newvalue)
                Pancake\PHPFunctions\setINI($varname, $newvalue);
            $settings = array();
            return true;
        }
        if(!isset($settings[$varname]))
            $settings[$varname] = ini_get($varname);
        return Pancake\PHPFunctions\setINI($varname, $newvalue); 
    }
    
    function ini_alter($varname, $newvalue) {
    	return ini_set($varname, $newvalue);
    }
    
    if(PHP_MINOR_VERSION == 3 && PHP_RELEASE_VERSION < 6) {
        function debug_backtrace($provide_object = true) {
            return Pancake\workBacktrace(Pancake\PHPFunctions\debugBacktrace($provide_object));
        }
    } else {
        function debug_backtrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit = 0) {
        	if($limit)
        		$limit += 3;
            return Pancake\workBacktrace(PHP_MINOR_VERSION >= 4 ? Pancake\PHPFunctions\debugBacktrace($options, $limit) : Pancake\PHPFunctions\debugBacktrace($options));
        }
    }
    
    function debug_print_backtrace($options = 0, $limit = 0) {
    	if($limit)
    		$limit += 3;
    	
        ob_start();
        Pancake\PHPFunctions\debugPrintBacktrace($options, $limit);
        $backtrace = ob_get_contents();
        Pancake\PHPFunctions\OutputBuffering\endClean();
        
        $trace = "";
        $i = 0;
        
        foreach(explode("\n", $backtrace) as $index => $tracePart) {
            if(!$index
            || (strpos($tracePart, '/sys/php/util.php') && Pancake\vars::$executingErrorHandler)
            || (strpos($tracePart, 'Pancake\PHPErrorHandler') && Pancake\vars::$executingErrorHandler))
                continue;
            if(strpos($tracePart, '/sys/threads/single/phpWorker.thread.php'))
                break;
            if($index-1)
                $trace .= "\n";
            $tracePart = explode(" ", $tracePart, 2);
            
            $trace .= "#" . $i . " " . $tracePart[1];
            $i++;
        }
        echo $trace;
    }
    
    function get_included_files($setNull = false) {
    	static $pancakeIncludes = array();
    	
    	$includes = Pancake\PHPFunctions\getIncludes();
    	if($setNull) {
    		$pancakeIncludes = $includes;
    		return;
    	}
    	foreach($includes as $index => $fileName)
    		if(in_array($fileName, $pancakeIncludes))
    			unset($includes[$index]);
    	return $includes;
    }
    
    function get_required_files() {
    	return get_included_files();
    }
    
    function apache_child_terminate() {
    	return Pancake\vars::$workerExit = true;
    }
    
    function apache_request_headers() {
    	return Pancake\vars::$Pancake_request->getRequestHeadersArray();
    }
    
    function apache_response_headers() {
    	return Pancake\vars::$Pancake_request->getAnswerHeadersArray();
    }
    
    function getallheaders() {
    	return Pancake\vars::$Pancake_request->getRequestHeadersArray();
    }
    
    function set_error_handler($error_handler, $error_types = null) {
    	if(!$error_types)
    		$error_types = E_ALL | E_STRICT;
    	if(!is_callable($error_handler))
    		return null;
    	$returnValue = Pancake\vars::$errorHandler;

    	Pancake\vars::$errorHandler = array('call' => $error_handler, 'for' => $error_types);
    	Pancake\vars::$errorHandlerHistory[] = array('call' => $error_handler, 'for' => $error_types);
    	
    	return $returnValue;
    }
    
    function restore_error_handler() {
    	array_pop(Pancake\vars::$errorHandlerHistory);
    	end(Pancake\Vars::$errorHandlerHistory);
    	if(($handler = current(Pancake\vars::$errorHandlerHistory)) === false)
    		Pancake\vars::$errorHandler = null;
    	else
    		Pancake\vars::$errorHandler = $handler;
    	
    	return true;
    }
    
    function memory_get_usage($real_usage = false, $setNull = false) {
    	static $nullUsage = 0;

    	if($setNull) {
    		$nullUsage = Pancake\PHPFunctions\getMemoryUsage();
    		return;
    	}
    	
    	return Pancake\PHPFunctions\getMemoryUsage($real_usage) - ($real_usage ? 0 : $nullUsage);
    }
    
    function memory_get_peak_usage($real_usage = false, $setNull = false) {
    	static $nullUsage = 0;
    
    	if($setNull) {
    		$nullUsage = Pancake\PHPFunctions\getPeakMemoryUsage();
    		return;
    	}
    	 
    	return Pancake\PHPFunctions\getPeakMemoryUsage($real_usage) - ($real_usage ? 0 : $nullUsage);
    }
    
    function get_browser($user_agent = null, $return_array = false) {
    	return Pancake\PHPFunctions\getBrowser($user_agent ? $user_agent : Pancake\vars::$Pancake_request->getRequestHeader('User-Agent', false), $return_array);
    }
   
	function error_get_last() {
		$lastError = Pancake\PHPFunctions\errorGetLast();
		
   		return is_array($lastError) && $lastError['type'] == \E_ERROR ? $lastError : Pancake\vars::$lastError;
	}
	
	function session_id($id = "") {
		if(((PHP_MINOR_VERSION >= 4 && session_status() == 1)
		|| (PHP_MINOR_VERSION == 3 && !Pancake\vars::$sessionID))
			&& !$id)
			return '';
		if($id)
			Pancake\vars::$sessionID = $id;
		return $id ? Pancake\PHPFunctions\sessionID($id) : Pancake\PHPFunctions\sessionID();
	}
    
	function session_set_save_handler($open, $close = true, $read = null, $write = null, $destroy = null, $gc = null) {
		if(PHP_MINOR_VERSION >= 4 && is_object($open) && in_array('SessionHandlerInterface', class_implements($open))) {
			$retval = Pancake\PHPFunctions\setSessionSaveHandler($open, false);
			if($close && $retval)
				session_register_shutdown();
		} else
			$retval = Pancake\PHPFunctions\setSessionSaveHandler($open, $close, $read, $write, $destroy, $gc);
		if($retval)
			return Pancake\vars::$resetSessionSaveHandler = true;
		return false;
	}
	
	function spl_autoload_register($autoload_function = null, $throw = true, $prepend = false, $unregister = false) {
		 static $registeredFunctions = array();
		
		if($unregister) {
			foreach($registeredFunctions as $function)
				spl_autoload_unregister($function);
			$registeredFunctions = array();
			return;
		}
		
		// Some crazy softwares like Joomla want to register private static methods as autoloaders, which is only possible, when spl_autoload_register() is called from the same class
		// But with the SAPI-wrapped function the real spl_autoload_register() is always called from the function's scope
		if(is_array($autoload_function)) {
			$reflect = new ReflectionMethod($autoload_function[0], $autoload_function[1]);
			if($reflect->isPrivate() || $reflect->isProtected()) {
				$name = 'Pancake_TemporaryMethod' . mt_rand();

				dt_add_method(is_object($autoload_function[0]) ? get_class($autoload_function[0]) : $autoload_function[0], $name, null, <<<'FUNCTIONBODY'
						if(!\Pancake\PHPFunctions\registerAutoload(func_get_arg(0), func_get_arg(1), func_get_arg(2)))
							return false;
						return true;
FUNCTIONBODY
						, 0x01 | 0x100);
				
				if(!$autoload_function[0]::$name($autoload_function, $throw, $prepend))
					$returnFalse = true;
				
				dt_remove_method(is_object($autoload_function[0]) ? get_class($autoload_function[0]) : $autoload_function[0], $name);
				
				if(isset($returnFalse))
					return false;
				
				goto registered;
			}
		}
		
		if(!Pancake\PHPFunctions\registerAutoload($autoload_function, $throw, $prepend))
			return false;
		
		registered:
		
		// Do not unregister autoloaders defined at CodeCache-load
		if(defined('PANCAKE_PHP'))
			$registeredFunctions[] = $autoload_function;
		
		return true;
	}
	
    dt_rename_method('\Exception', 'getTrace', 'Pancake_getTraceOrig');
    dt_add_method('\Exception', 'getTrace', null, <<<'FUNCTIONBODY'
$trace = $this->Pancake_getTraceOrig();
unset($trace[count($trace)-1]);
unset($trace[count($trace)-1]);
return $trace;
FUNCTIONBODY
	);
    
    dt_rename_method('\Exception', 'getTraceAsString', 'Pancake_getTraceAsStringOrig');
    dt_add_method('\Exception', 'getTraceAsString', null, <<<'FUNCTIONBODY'
$backtrace = explode("\n", $this->Pancake_getTraceAsStringOrig());
$trace = "";
$i = 0;    	

foreach($backtrace as $traceElement) {
	if(strpos($traceElement, 'phpWorker.thread.php'))
    	break;
    $trace .= $traceElement . "\n";
    $i++;
}
$trace .= '#' . $i . ' {main}';
    		
return $trace;
FUNCTIONBODY
	);
    
    dt_add_method('\ReflectionFunction', 'isDisabled', null, <<<'FUNCTIONBODY'
return $this->Pancake_isDisabledOrig() || in_array($this->name, Pancake\vars::$Pancake_currentThread->vHost->getDisabledFunctions());
FUNCTIONBODY
    );
?>
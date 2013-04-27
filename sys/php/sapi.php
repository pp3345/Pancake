<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* sapi.php                                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    /* See php.net for further documentation on the SAPI-functions */

	#.if 0
    if(Pancake\PANCAKE !== true)
        exit;
    #.endif

    const PHP_SAPI = 'pancake';

    function pancake_logo_guid() {
        return 'PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B';
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

    function register_shutdown_function($callback) {
    	if(!is_callable($callback))
    		return false;

        $shutdownCall = array('callback' => $callback);

        $shutdownCall['args'] = func_get_args();
        unset($shutdownCall['args'][0]);

        Pancake\vars::$Pancake_shutdownCalls[] = $shutdownCall;

        return true;
    }

    #.ifdef 'HAVE_FILTER_EXTENSION'
    function filter_input($type, $variable_name, $filter = /* .constant 'FILTER_DEFAULT' */, $options = /* .constant 'FILTER_FLAG_NONE' */) {
        // Create bitmask of flags
        if(is_array($options)) {
        	$flags = 0;

            foreach((array) $options['flags'] as $option)
                $flags |= $option;
        } else
            $flags = $options;

        // Get variable
        switch($type) {
            case /* .constant 'INPUT_GET' */:
                $GET = Pancake\vars::$Pancake_request->getGETParams();
                if(!array_key_exists($variable_name, $GET))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $GET[$variable_name];
                break;
            case /* .constant 'INPUT_POST' */:
                $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                if(!array_key_exists($variable_name, $POST))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $POST[$variable_name];
                break;
            case /* .constant 'INPUT_COOKIE' */:
                $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                if(!array_key_exists($variable_name, $COOKIE))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $COOKIE[$variable_name];
                break;
            case /* .constant 'INPUT_SERVER' */:
                $SERVER = Pancake\vars::$Pancake_request->createSERVER();
                if(!array_key_exists($variable_name, $SERVER))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $SERVER[$variable_name];
                break;
            case /* .constant 'INPUT_ENV' */:
                if(!array_key_exists($variable_name, $_ENV))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $_ENV[$variable_name];
                break;
        }

        return filter_var($var, $filter, $options);
    }

    function filter_input_array($type, $definition = null) {
    	$endArray = $data = $filterOptions = array();

        foreach((array) $definition as $key => $options) {
        	if(is_int($options))
        		$options = array('filter' => $options, 'flags' => 0);
        	if(!isset($options['flags']))
        		$options['flags'] = 0;

            switch($type) {
                case /* .constant 'INPUT_GET' */:
                    $GET = Pancake\vars::$Pancake_request->getGETParams();
                    if(!array_key_exists($key, $GET)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $GET[$key];
                    break;
                case /* .constant 'INPUT_POST' */:
                    $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                    if(!array_key_exists($key, $POST)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $POST[$key];
                    break;
                case /* .constant 'INPUT_COOKIE' */:
                    $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                    if(!array_key_exists($key, $COOKIE)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $COOKIE[$key];
                    break;
                case /* .constant 'INPUT_SERVER' */:
                    $SERVER = Pancake\vars::$Pancake_request->createSERVER();
                    if(!array_key_exists($key, $SERVER)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $SERVER[$key];
                    break;
                case /* .constant 'INPUT_ENV' */:
                    if(!array_key_exists($key, $_ENV)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
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
            case /* .INPUT_GET */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getGETParams());
            case /* .INPUT_POST */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getPOSTParams());
            case /* .INPUT_COOKIE */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getCookies());
            case /* .INPUT_SERVER */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->createSERVER());
            case /* .INPUT_ENV */:
                return array_key_exists($variable_name, $_ENV);
            default:
                return false;
        }
    }
    #.endif

    function debug_backtrace($options = /* .constant 'DEBUG_BACKTRACE_PROVIDE_OBJECT' */, $limit = 0) {
    	if($limit)
    		$limit += 3;
        return Pancake\workBacktrace(Pancake\PHPFunctions\debugBacktrace($options, $limit));
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
            || (strpos($tracePart, '/sys/compilecache/phpWorker.thread') && strpos($tracePart, 'call_user_func') && Pancake\vars::$executingErrorHandler)
            || (strpos($tracePart, 'Pancake\PHPErrorHandler') && Pancake\vars::$executingErrorHandler))
                continue;
            if(strpos($tracePart, '/sys/compilecache/phpWorker.thread'))
                break;
            if($index-1)
                $trace .= "\n";
            $tracePart = explode(" ", $tracePart, 2);

            $trace .= "#" . $i . " " . $tracePart[1];

            $i++;
        }
        echo $trace;
    }

    function apache_child_terminate() {
    	return Pancake\vars::$workerExit = true;
    }

    function apache_request_headers() {
    	return Pancake\vars::$Pancake_request->requestHeaders;
    }

    function apache_response_headers() {
    	return Pancake\vars::$Pancake_request->answerHeaders;
    }

    function getallheaders() {
    	return Pancake\vars::$Pancake_request->requestHeaders;
    }

    #.ERROR_TYPES = E_ALL | E_STRICT

    function set_error_handler($error_handler, $error_types = /* .ERROR_TYPES */) {
        if(!is_callable($error_handler)) {
            if(is_array($error_handler)) {
                if(isset($error_handler[0]) && isset($error_handler[1])) {
                    $error_handler_name = $error_handler[0] . "::" . $error_handler[1];
                } else {
                    $error_handler_name = "unknown";
                }
            } else {
                $error_handler_name = $error_handler;
            }
            #.PHP_ERROR_WITH_BACKTRACE E_WARNING '"set_error_handler() expects the argument ($error_handler_name) to be a valid callback"'
            return null;
        }

    	$returnValue = Pancake\vars::$errorHandler ? Pancake\vars::$errorHandler['call'] : null;

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

	function error_get_last() {
		$lastError = Pancake\PHPFunctions\errorGetLast();

   		return is_array($lastError) && $lastError['type'] == /* .constant 'E_ERROR' */ ? $lastError : Pancake\vars::$lastError;
	}

	function register_tick_function($function) {
		if(!is_callable($function)) {
			#.PHP_ERROR_WITH_BACKTRACE E_WARNING '"Invalid tick callback \'$function\' passed"'
			return false;
		}
		Pancake\vars::$tickFunctions[] = $function;
		return call_user_func_array('Pancake\PHPFunctions\registerTickFunction', func_get_args());
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
	if(strpos($traceElement, '/sys/compilecache/phpWorker.thread'))
    	break;
    $trace .= $traceElement . "\n";
    $i++;
}
$trace .= '#' . $i . ' {main}';

return $trace;
FUNCTIONBODY
	);

    dt_add_method('\ReflectionFunction', 'isDisabled', null, <<<'FUNCTIONBODY'
return $this->Pancake_isDisabledOrig() || in_array($this->name, Pancake\vars::$Pancake_currentThread->vHost->phpDisabledFunctions);
FUNCTIONBODY
    );
?>
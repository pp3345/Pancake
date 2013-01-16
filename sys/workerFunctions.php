<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* workerFunctions.php                                          */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
	#.if 0
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
	#.endif
    
    /**
    * Formats a filesize
    * 
    * @param int $size Size in Byte
    * @return string Formatted size
    */
    function formatFilesize($size) {
    	#.if 'si' == #.call 'Pancake\Config::get' 'main.sizeprefix'
            if($size >= 1000000000) // 1 Gigabyte
                return round($size / 1000000000, 2) . ' GB';
            else if($size >= 1000000) // 1 Megabyte
                return round($size / 1000000, 2) . ' MB';
            else if($size >= 1000) // 1 Kilobyte
                return round($size / 1000, 2) . ' kB';
            else 
                return $size . ' Byte';
        #.else
            if($size >= 1073741824) // 1 Gibibyte
                return round($size / 1073741824, 2) . ' GiB';
            else if($size >= 1048576) // 1 Mebibyte 
                return round($size / 1048576, 2) . ' MiB';
            else if($size >= 1024) // 1 Kibibyte
                return round($size / 1024, 2) . ' KiB';
            else
                return $size . ' Byte';   
        #.endif
    }
    
    /**
    * Sets user and group for current thread
    * 
    */
    function setUser() {
        #.if Pancake\DEBUG_MODE == false
        $user = posix_getpwnam(/* .call 'Pancake\Config::get' 'main.user' */);
        $group = posix_getgrnam(/* .call 'Pancake\Config::get' 'main.group' */);
        if(!posix_setgid($group['gid'])) {
            trigger_error('Failed to change group', /* .constant 'E_USER_ERROR' */);
            abort();
        }
        if(!posix_setuid($user['uid'])) {
            trigger_error('Failed to change user', /* .constant 'E_USER_ERROR' */);
            abort();
        }
        #.endif
        return true;
    }
    
    #.if Pancake\DEBUG_MODE === true
    function benchmarkFunction($functionName, $returnResults = false, $returnFunctions = false) {
    	static $benchmarkedFunctions = array();
    		
    	if($returnFunctions === true) {
    		return $benchmarkedFunctions;
    	}
    		
    	if($returnResults === true) {
    		foreach($benchmarkedFunctions as $function) {
    			$results[$function] = $function("__GET_PANCAKE_BENCHMARK_RESULTS");
    			//dt_remove_function($function);
    			//dt_rename_function('__PANCAKE_BENCHMARK__' . $function, $function);
    		}
    		//$benchmarkedFunctions = array();
    		return $results;
    	}
    		
    	if(!function_exists($functionName) || isset($benchmarkedFunctions[$functionName]))
    		return false;
    		
    	$benchmarkedFunctions[] = $origFunctionName = $functionName;	
    	
    	if(($pos = strrpos($functionName, "\\")) !== false) {
    		$namespace = substr($functionName, 0, $pos);
    		$functionName = substr($functionName, $pos + 1);
    	}
    	
    	dt_rename_function($origFunctionName, '__PANCAKE_BENCHMARK__' . $functionName);

    	eval(($namespace ? 'namespace ' . $namespace . ';' : '') .	
    		'
			function ' . $functionName . '($getResults = null) {
				static $results = array();
    
				if($getResults === "__GET_PANCAKE_BENCHMARK_RESULTS") {
					$retval = $results;
					$results = array();
					return $retval;
				}
    
    			$args = func_get_args();
				$before = microtime(true);
				$retval = call_user_func_array("__PANCAKE_BENCHMARK__' . $functionName . '", $args);
				$after = microtime(true);
    
				$results[] = $after - $before;
    
				return $retval;
			}
			');
    		
    	return true;
    }
    #.endif
    
    function dummy() {return true;}
?>
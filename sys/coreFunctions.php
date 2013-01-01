<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* coreFunctions.php                                            */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
        
    /**
    * Aborts execution of Pancake
    */
    function abort($return = false) {
        global $Pancake_currentThread;
        global $Pancake_sockets;
        global $Pancake_phpSockets;
        
        if($Pancake_currentThread || (class_exists('Pancake\vars') && $Pancake_currentThread = vars::$Pancake_currentThread))
            return $Pancake_currentThread->parentSignal(\SIGTERM);
        
        out('Stopping...');
            
        foreach((array) $Pancake_sockets as $socket) 
            socket_close($socket);
        foreach((array) $Pancake_phpSockets as $socket) {
            socket_getsockname($socket, $addr);
            socket_close($socket);
            unlink($addr);
        }
        
        $threads = Thread::getAll();
        
        foreach((array) $threads as $worker) {
            /**
            * @var Thread
            */
            $worker;
            
            if(!$worker->running)
                continue;
            if($worker instanceof RequestWorker) {
            	socket_write($worker->localSocket, "GRACEFUL_SHUTDOWN");
            	unlink($worker->socketName);
            } else
            	$worker->stop();
            $worker->waitForExit();
        }
        
        @IPC::destroy();
        if($return)
        	return;
        else
        	exit;
    }
    
    /**
    * Like \array_merge() with the difference that this function overrides keys instead of adding them.
    * 
    * @param array $array1
    * @param array $array2
    * @return array Merged array
    */
    function array_merge($array1, $array2) {
        $endArray = $array1;
        foreach((array) $array2 as $key => $value)
            if(is_array($value))
                $endArray[$key] = array_merge($array1[$key], $array2[$key]);
            else
                $endArray[$key] = $array2[$key];
        return $endArray;
    }
        
    /**
    * Cleans all global and superglobal variables
    * 
    */
    function cleanGlobals($excludeVars = array(), $listOnly = false, $clearRecursive = false) {
        $_GET = $_SERVER = $_POST = $_COOKIE = $_ENV = $_REQUEST = $_FILES = $_SESSION = array();
        
        $list = array();
    
        // We can't reset $GLOBALS like this because it would destroy its function of automatically adding all global vars
        foreach($GLOBALS as $globalName => $globalVar) {
            if($globalName != 'Pancake_vHosts'
            && $globalName != 'Pancake_sockets'
            && $globalName != 'GLOBALS'
            && $globalName != '_GET'
            && $globalName != '_POST'
            && $globalName != '_ENV'
            && $globalName != '_COOKIE'
            && $globalName != '_SERVER'
            && $globalName != '_REQUEST'
            && $globalName != '_FILES'
            && $globalName != '_SESSION'
            && @!in_array($globalName, $excludeVars)) {
                if($listOnly)
                    $list[] = $globalName;
                else {
                	if($clearRecursive && (is_array($GLOBALS[$globalName]) || is_object($GLOBALS[$globalName])))
                		recursiveClearObjects($GLOBALS[$globalName]);
                	
                    $GLOBALS[$globalName] = null;
                    unset($GLOBALS[$globalName]);
                }
            }
        }
        return $listOnly ? $list : true;
    }
    
    /**
     * Resets all indices of an array (recursively) to lower case
     * 
     * @param array $array
     * @param array $caseSensitivePaths If the name of a index matches and the value is an array, this function won't change the case of indexes inside the value
     * @return array
     */
    function arrayIndicesToLower($array, $caseSensitivePaths = array()) {
    	foreach($array as $index => $value) {
    		if(is_array($value) && !in_array(strToLower($index), $caseSensitivePaths))
    			$value = arrayIndicesToLower($value, $caseSensitivePaths);
    		$newArray[strToLower($index)] = $value;
    	}
    	
    	return $newArray;
    }
?>
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
    * Function for output and logging
    * 
    * @param string $text The string to be logged
    * @param int $type SYSTEM or REQUEST 
    * @param bool $log Whether the text should be logged to a file or not
    * @param bool $debugMode Whether the text should only be output in debugmode
    * @return false|string false on error; message on success
    */
    function out($text, $type = SYSTEM, $log = true, $debugMode = false) {
        static $fileStream = array();
        global $Pancake_currentThread;
        
        if(defined('PANCAKE_PHP') && DEBUG_MODE !== true)
        	return false;
        
        $thread = !$Pancake_currentThread && class_exists('Pancake\vars') ? vars::$Pancake_currentThread : $Pancake_currentThread;
        
        if($type !== SYSTEM && $type !== REQUEST)
            return false;
        
        if(!$fileStream && $log === true) {
            if(!($fileStream[SYSTEM] = @fopen(Config::get('main.logging.system'), 'a+')) || !($fileStream[REQUEST] = @fopen(Config::get('main.logging.request'), 'a+'))) {
                out('Couldn\'t open file for logging - Check if it exists and is accessible for Pancake', SYSTEM, false);
                abort();
            }
        }
    
        $friendlyName = ($thread) ? $thread->friendlyName : 'Master';
        
        $message = '['.$friendlyName.'] '
                    .date(Config::get('main.dateformat')).' '
                    .$text."\n";
        
        if($debugMode && DEBUG_MODE !== true)
            return $message;
        
        if(DAEMONIZED !== true)
            fwrite(STDOUT, $message);
        if($log === true && is_resource($fileStream[$type]) && !fwrite($fileStream[$type], $message))
            trigger_error('Couldn\'t write to logfile', \E_USER_WARNING);
        return $message;
    }
    
    /**
    * Aborts execution of Pancake
    */
    function abort() {
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
            $worker->stop();            
            $worker->waitForExit();
        }
        
        @IPC::destroy();
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
    * ErrorHandler. Outputs errors if DebugMode is switched on and logs them to a file
    * 
    * @param int $errtype Type of the occured error 
    * @param string $errstr String that describes the error
    * @param string $errfile The file in which the error occured
    * @param int $errline The line in which the error occured
    */
    function errorHandler($errtype, $errstr, $errfile = null, $errline = null) {
        static $fileStream = null;
        if(!$fileStream)
            $fileStream = @fopen(Config::get('main.logging.error'), 'a+');
        // Check for @
        if(error_reporting() == 0)
            return true;
        if($errtype & ERROR_REPORTING) {
            $message = 'An error ('.$errtype.') occured: '.$errstr.' in '.$errfile.' on line '.$errline;
            $msg = out($message, SYSTEM, false);
            if(is_resource($fileStream))
                fwrite($fileStream, $msg);
        }
        return true;
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
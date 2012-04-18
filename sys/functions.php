<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* functions.php                                                */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * Function for output and logging
    * @param string $text The string to be logged
    * @param int $type PANCAKE_SYSTEM or PANCAKE_REQUEST 
    * @param bool $log Whether the text may be logged or not
    * @param bool $debugMode Whether the text should only be output in debugmode
    */
    function Pancake_out($text, $type = PANCAKE_SYSTEM, $log = true, $debugMode = false) {
        static $fileStream = array();
        global $Pancake_currentThread;
        
        if($type !== PANCAKE_SYSTEM && $type !== PANCAKE_REQUEST)
            return false;
        
        if(!$fileStream && $log === true) {
            if(!($fileStream[PANCAKE_SYSTEM] = @fopen(Pancake_Config::get('main.logging.system'), 'a+')) || !($fileStream[PANCAKE_REQUEST] = @fopen(Pancake_Config::get('main.logging.request'), 'a+'))) {
                Pancake_out('Couldn\'t open file for logging - Check if it exists and is accessible for Pancake', PANCAKE_SYSTEM, false);
                Pancake_abort();
            }
        }
    
        $friendlyName = (!$Pancake_currentThread) ? 'Master' : $Pancake_currentThread->friendlyName;
        
        $message = '['.$friendlyName.'] '
                    .date(Pancake_Config::get('main.dateformat')).' '
                    .$text."\n";
        
        if($debugMode && PANCAKE_DEBUG_MODE !== true)
            return $message;
        
        if(PANCAKE_DAEMONIZED !== true)
            fwrite(STDOUT, $message);
        if($log === true && is_resource($fileStream[$type]) && !fwrite($fileStream[$type], $message))
            trigger_error('Couldn\'t write to logfile', E_USER_WARNING);
        return $message;
    }
    
    /**
    * Aborts execution of Pancake
    */
    function Pancake_abort() {
        global $Pancake_currentThread;
        global $Pancake_sockets;
        if($Pancake_currentThread) {
            $Pancake_currentThread->parentSignal(SIGUSR2);
            return;
        }
        
        Pancake_out('Stopping...');
            
        if($Pancake_sockets) { 
            foreach($Pancake_sockets as $socket) 
                socket_close($socket);
        }
        $threads = Pancake_Thread::getAll();
        if($threads)
            foreach($threads as $worker) {
                $worker->stop();            
                $worker->waitForExit();
            }
        @Pancake_SharedMemory::destroy();
        @Pancake_IPC::destroy();
        exit;
    }                                    
    
    /**
    * Like array_merge(). But not so stupid.
    * 
    * @param array $array1
    * @param array $array2
    * @return array Merged array
    */
    function Pancake_array_merge($array1, $array2) {
        $endArray = $array1;
        foreach((array) $array2 as $key => $value)
            if(is_array($value))
                $endArray[$key] = Pancake_array_merge($array1[$key], $array2[$key]);
            else
                $endArray[$key] = $array2[$key];
        return $endArray;
    }
    
    /**
    * Formats a filesize
    * 
    * @param int $size Size in Byte
    * @return string Formatted size
    */
    function Pancake_formatFilesize($size) {
        if(Pancake_Config::get('main.sizeprefix') == 'si') {
            if($size >= 1000000000) // 1 Gigabyte
                return round($size / 1000000000, 2) . ' GB';
            else if($size >= 1000000) // 1 Megabyte
                return round($size / 1000000, 2) . ' MB';
            else if($size >= 1000) // 1 Kilobyte
                return round($size / 1000, 2) . ' kB';
            else 
                return $size . ' Byte';
        } else {
            if($size >= 1073741824) // 1 Gibibyte
                return round($size / 1073741824, 2) . ' GiB';
            else if($size >= 1048576) // 1 Mebibyte 
                return round($size / 1048576, 2) . ' MiB';
            else if($size >= 1024) // 1 Kibibyte
                return round($size / 1024, 2) . ' KiB';
            else
                return $size . ' Byte';   
        }
    }
    
    /**
    * ErrorHandler. Outputs errors if DebugMode is switched on and logs them to a file
    * 
    * @param int $errtype Type of the occured error 
    * @param string $errstr String that describes the error
    * @param string $errfile The file in which the error occured
    * @param int $errline The line in which the error occured
    */
    function Pancake_errorHandler($errtype, $errstr, $errfile = null, $errline = null) {
        global $Pancake_currentThread;
        static $fileStream;                                                                                                                                    
        if(!$fileStream)
            $fileStream = @fopen(Pancake_Config::get('main.logging.error'), 'a+');
        // Check for @
        if(error_reporting() == 0)
            return true;
        if($errtype & PANCAKE_ERROR_REPORTING) {
            $message = 'An error ('.$errtype.') occured: '.$errstr.' in '.$errfile.' on line '.$errline;
            $msg = Pancake_out($message, PANCAKE_SYSTEM, false);
            if(is_resource($fileStream))
                fwrite($fileStream, $msg);
        }
        return true;
    }
    
    /**
    * Sets user and group for current thread
    * 
    */
    function Pancake_setUser() {
        $user = posix_getpwnam(Pancake_Config::get('main.user'));
        $group = posix_getgrnam(Pancake_Config::get('main.group'));
        if(!posix_setgid($group['gid'])) {
            trigger_error('Failed to change group', E_USER_ERROR);
            Pancake_abort();
        }
        if(!posix_setuid($user['uid'])) {
            trigger_error('Failed to change user', E_USER_ERROR);
            Pancake_abort();
        }
        return true;
    }
    
    /**
    * Cleans all global and superglobal variables
    * 
    */
    function Pancake_cleanGlobals($excludeVars = null) {
        $_GET = $_SERVER = $_POST = $_COOKIE = $_ENV = $_REQUEST = $_FILES = $_SESSION = array();
    
        // We can't reset $GLOBALS like this because it would destroy its function of automatically adding all global vars
        foreach($GLOBALS as $globalName => $globalVar) {
            if($globalName != 'Pancake_currentThread'
            && $globalName != 'Pancake_vHosts'
            && $globalName != 'Pancake_sockets'
            && $globalName != 'Pancake_processedRequests'
            && $globalName != 'GLOBALS'
            && $globalName != '_GET'
            && $globalName != '_POST'
            && $globalName != '_ENV'
            && $globalName != '_COOKIE'
            && $globalName != '_SERVER'
            && $globalName != '_REQUEST'
            && $globalName != '_FILES'
            && $globalName != '_SESSION'
            && $globalName != 'Pancake_constsPre'
            && $globalName != 'Pancake_includesPre'
            && $globalName != 'Pancake_classesPre'
            && $globalName != 'Pancake_funcsPre'
            && $globalName != 'Pancake_interfacesPre'
            && @!in_array($globalName, $excludeVars)) {
                $GLOBALS[$globalName] = null;
                
                unset($GLOBALS[$globalName]);
            }
        }
        return true;
    }
    
    function Pancake_dummy() {}
?>
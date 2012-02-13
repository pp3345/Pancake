<?php
  
    /****************************************************************/
    /* Pancake                                                    */
    /* functions.php                                                */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * Function for output and logging
    * @param string $text The string to be logged
    * @param int $type SYSTEM or REQUEST 
    * @param bool $log Whether the text may be logged or not
    * @param bool $debugMode Whether the text should only be output in debugmode
    */
    function Pancake_out($text, $type = SYSTEM, $log = true, $debugMode = false) {
        static $fileStream = array();
        global $currentThread;
        
        if($type != SYSTEM && $type != REQUEST)
            return false;
        
        if(!$fileStream && $log === true) {
            if(!($fileStream[SYSTEM] = fopen(Pancake_Config::get('main.logging.system'), 'a+')) || !($fileStream[REQUEST] = fopen(Pancake_Config::get('main.logging.request'), 'a+'))) {
                Pancake_out('Couldn\'t open file for logging - Check if it exists and is accessible for Pancake', SYSTEM, false);
                Pancake_abort();
            }
        }
    
        $friendlyName = (!$currentThread) ? 'Master' : $currentThread->friendlyName;
        
        $message = '['.$friendlyName.'] '
                    .date(Pancake_Config::get('main.dateformat')).' '
                    .$text."\n";
        
        if($debugMode && PANCAKE_DEBUG_MODE !== true)
            return $message;
        
        if(PANCAKE_DAEMONIZED !== true)
            echo $message;
        if($log === true && !fwrite($fileStream[$type], $message))
            Pancake_out('Couldn\'t write to logfile', SYSTEM, false);
        return $message;
    }
    
    /**
    * Aborts execution of Pancake
    */
    function Pancake_abort() {
        global $currentThread;
        global $Pancake_sockets;
        global $socketWorkers;
        global $requestWorkers;
        if($currentThread) {
            $currentThread->parentSignal(SIGUSR2);
            return;
        }
        
        Pancake_out('Stopping...');
            
        if($socketWorkers || $Pancake_sockets) { 
            foreach($socketWorkers as $worker)
                $worker->stop();   
            foreach($Pancake_sockets as $socket) 
                socket_close($socket);
        }
        if($requestWorkers)
            foreach($requestWorkers as $worker)
                $worker->stop();
        exit;
    }                                    
    
    /**
    * Like array_merge(). But not so stupid.
    * 
    * @param array $array1
    * @param array $array2
    */
    function array_intelligent_merge($array1, $array2) {
        $endArray = $array2;
        foreach($array1 as $key => $value)
            if(is_array($value))
                $endArray[$key] = array_intelligent_merge($array1[$key], $array2[$key]);
            else if(empty($array2[$key]))
                $endArray[$key] = $array1[$key];
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
    function Pancake_errorHandler($errtype, $errstr, $errfile = null, $errline = null) {
        global $currentThread;
        static $fileStream;
        if(!$fileStream)
            $fileStream = @fopen(Pancake_Config::get('main.logging.error'), 'a+');
        if($errtype == E_ERROR || $errtype == E_WARNING || $errtype == E_USER_WARNING || $errtype == E_USER_ERROR || $errtype == E_RECOVERABLE_ERROR) {
            $message = 'An error ('.$errtype.') occured: '.$errstr.' in '.$errfile.' on line '.$errline;
            fwrite($fileStream, Pancake_out($message, SYSTEM, false, true));
        }
        return true;
    }
?>

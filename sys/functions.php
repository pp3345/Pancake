<?php
  
    /****************************************************************/
    /* dreamServ                                                    */
    /* functions.php                                                */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    /**
    * Function for output and logging
    * @param string $text The string to be logged
    * @param int $type SYSTEM or REQUEST 
    * @param bool $log Whether the text may be logged or not
    * @param bool $debugMode Whether the text should only be output in debugmode
    */
	function out($text, $type = SYSTEM, $log = true, $debugMode = false) {
        static $fileStream = array();
        global $currentThread;
        
        if($type != SYSTEM && $type != REQUEST)
            return false;
        
        if(!$fileStream && $log === true) {
            if(!($fileStream[SYSTEM] = fopen(Config::get('main.logging.system'), 'a+')) || !($fileStream[REQUEST] = fopen(Config::get('main.logging.request'), 'a+'))) {
                out('Couldn\'t open file for logging - Check if it exists and is accessible for dreamServ', SYSTEM, false);
                abort();
            }
        }
    
        $friendlyName = (!$currentThread) ? 'Master' : $currentThread->friendlyName;
        
        $message = '['.$friendlyName.'] '
                    .date(Config::get('main.dateformat')).' '
                    .$text."\n";
        
        if($debugMode && DEBUG_MODE !== true)
            return $message;
        
        if(DAEMONIZED !== true)
            echo $message;
        if($log === true && !fwrite($fileStream[$type], $message))
            out('Couldn\'t write to logfile', SYSTEM, false);
        return $message;
    }
    
    /**
    * Aborts execution of dreamServ
    */
    function abort() {
        global $currentThread;
        if(!$currentThread)
            exit;
        $currentThread->parentSignal(SIGUSR2); 
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
    function errorHandler($errtype, $errstr, $errfile = null, $errline = null) {
        global $currentThread;
        if($errtype == E_ERROR || $errtype == E_WARNING || $errtype == E_USER_WARNING || $errtype == E_USER_ERROR || $errtype == E_RECOVERABLE_ERROR) {
            $message = 'An error ('.$errtype.') occured: '.$errstr.' in '.$errfile.' on line '.$errline;
            file_put_contents(Config::get('main.logging.error'), out($message, SYSTEM, false, true), FILE_APPEND);
        }
        return true;
    }
?>

<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* util.php                                                     */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    /* See php.net for further documentation on the SAPI-functions */
    
    const PHP_SAPI = 'pancake';
    
    function php_sapi_name() {
        return 'pancake';
    } 
    
    function setcookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        global $Pancake_request;
        
        return $Pancake_request->setCookie($name, $value, $expire, $path, $domain, $secure, $httponly); 
    }
    
    function setrawcookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        global $Pancake_request;
        
        return $Pancake_request->setCookie($name, $value, $expire, $path, $domain, $secure, $httponly, true); 
    }
    
    function header($string, $replace = true, $http_response_code = 0) {
        global $Pancake_request;
        
        if(strtoupper(substr($string, 0, 5)) == 'HTTP/') {
            $data = explode(' ', $string);
            $Pancake_request->setAnswerCode($data[1]);
        } else {
            $header = explode(':', $string, 2);
            $Pancake_request->setHeader($header[0], trim($header[1]), $replace);
            if($header[0] == 'Location' && $Pancake_request->getAnswerCode() != 201 && substr($Pancake_request->getAnswerCode(), 0, 1) != 3)
                $Pancake_request->setAnswerCode(302);
        }
        
        if($http_response_code)
            $Pancake_request->setAnswerCode($http_response_code);
    }
    
    function header_remove($name = null) {
        global $Pancake_request;
        
        if($name)
            $Pancake_request->removeHeader($name);
        else
            $Pancake_request->removeAllHeaders();
    }
    
    function headers_sent() {
        return false;
    }
    
    function headers_list() {
        global $Pancake_request;
        
        return $Pancake_request->getAnswerHeadersArray();
    }
    
    function http_response_code($response_code = 0) {
        global $Pancake_request;      
        
        if($response_code)
            $Pancake_request->setAnswerCode($response_code);
        return $Pancake_request->getAnswerCode();
    }
    
    function phpinfo($what = INFO_ALL) {
        global $Pancake_currentThread;
        global $Pancake_vHosts;
        global $Pancake_request;
        
        $Pancake_request->setHeader('Content-Type', 'text/html; charset=utf-8');
        
        // Get original phpinfo
        ob_start();
        if(!Pancake_phpinfo_orig($what))
            return false;
        $phpInfo = ob_get_contents();
        Pancake_ob_end_clean_orig();
        
        // Modify it
        $phpInfo = str_replace('<td class="v">Command Line Interface </td>', '<td class="v">Pancake HTTP-Server </td>', $phpInfo);
        
        $pancakeAdd =  '<table border="0" cellpadding="3" width="600">';
        $pancakeAdd .= '<tr class="h"><td>';
        $pancakeAdd .= '<a href="http://www.pancakehttp.net">';
        $pancakeAdd .= '<h1 class="p">Pancake Version ' . PANCAKE_VERSION .'</h1>';
        $pancakeAdd .= '</a>';
        $pancakeAdd .= '</td></tr></table><br />';
        $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
        $pancakeAdd .= '<tr><td class="e">Debug Mode</td><td class="v">'.(Pancake_Config::get('main.debugmode') ? 'enabled' : 'disabled').'</td></tr>';
        $pancakeAdd .= '<tr><td class="e">User</td><td class="v">'.Pancake_Config::get('main.user').'</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Group</td><td class="v">'.Pancake_Config::get('main.group').'</td></tr>';
        
        $pancakeAdd .= '<tr><td class="e">Included configuration files</td><td class="v">';
        foreach(Pancake_Config::get('include') as $include) {
            if($first) $pancakeAdd .= ', ';
            $pancakeAdd .= $include;
            $first = true;
        }
        $pancakeAdd .= '</td></tr>';
        
        $pancakeAdd .= '<tr><td class="e">Running RequestWorkers</td><td class="v">'.Pancake_Config::get('main.requestworkers').'</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Expose Pancake</td><td class="v">' . (Pancake_Config::get('main.exposepancake') ? 'enabled' : 'disabled') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Timestamp-format</td><td class="v">' . Pancake_Config::get('main.dateformat') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Sizeprefix-format</td><td class="v">' . (Pancake_Config::get('main.sizeprefix') == 'si' ? 'SI' : 'Binary') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Shared Memory</td><td class="v">' . Pancake_formatFilesize(Pancake_Config::get('main.sharedmemory')) . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Timeout for read actions</td><td class="v">' . Pancake_Config::get('main.readtimeout') . ' µs</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Current vHost</td><td class="v">' . $Pancake_currentThread->vHost->getName() . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Path for temporary files</td><td class="v">' . Pancake_Config::get('main.tmppath') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Path to requestlog</td><td class="v">' . Pancake_Config::get('main.logging.request') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Path to systemlog</td><td class="v">' . Pancake_Config::get('main.logging.system') . '</td></tr>';
        $pancakeAdd .= '<tr><td class="e">Path to errorlog</td><td class="v">' . Pancake_Config::get('main.logging.error') . '</td></tr>';
        
        $pancakeAdd .= '</table><br/>';
        
        $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
        $pancakeAdd .= '<tr class="v"><td>';
        $pancakeAdd .= 'Pancake Copyright (c) 2012 Yussuf Khalil <br/>';
        $pancakeAdd .= 'Take a look at <a href="http://www.pancakehttp.net">pancakehttp.net</a> for more information about Pancake';
        $pancakeAdd .= '</td></tr>';  
        $pancakeAdd .= '</table><br/>';
        
        $pancakeAdd .= '<h1>Virtual Hosts</h1>';
        
        foreach($Pancake_vHosts as $vHost) {
            if(@in_array($vHost->getID(), $vHostsDone))
                continue;
            
            $pancakeAdd .= '<h2>'.$vHost->getName().'</h2>';
            
            $pancakeAdd .= '<table border="0" cellpadding="3" width="600">';
            
            $pancakeAdd .= '<tr><td class="e">Running PHPWorkers</td><td class="v">' . $vHost->getPHPWorkerAmount() . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Is Default</td><td class="v">' . ($vHost->isDefault() ? 'yes' : 'no') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Document Root</td><td class="v">' . $vHost->getDocumentRoot() . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">GZIP-compression</td><td class="v">' . ($vHost->allowGZIPCompression() ? 'enabled' : 'disabled') . '</td></tr>';
            if($vHost->allowGZIPCompression()) {
                $pancakeAdd .= '<tr><td class="e">Minimum filesize for GZIP-compression</td><td class="v">' . Pancake_formatFilesize($vHost->getGZIPMinimum()) . '</td></tr>';
                $pancakeAdd .= '<tr><td class="e">GZIP-level</td><td class="v">' . $vHost->getGZIPLevel() . '</td></tr>';
            }
            
            $pancakeAdd .= '<tr><td class="e">Per-Write Limit</td><td class="v">' . Pancake_formatFilesize($vHost->getWriteLimit()) . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Directory Listings</td><td class="v">' . ($vHost->allowDirectoryListings() ? 'enabled' : 'disabled') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Index Files</td><td class="v">';
            
            unset($first);
            foreach($vHost->getIndexFiles() as $indexFile) {
                if($first) $pancakeAdd .= ', ';
                $pancakeAdd .= $indexFile;
                $first = true;
            }
            
            $pancakeAdd .= '</td></tr>';
            
            $pancakeAdd .= '<tr><td class="e">Hosts</td><td class="v">';
            
            unset($first);
            foreach($vHost->getListen() as $listen) {
                if($first) $pancakeAdd .= ', ';
                $pancakeAdd .= $listen;
                $first = true;
            }
            
            $pancakeAdd .= '</td></tr>';
            
            $pancakeAdd .= '</table><br/>';
            
            $vHostsDone[] = $vHost->getID();
        }
        
        $phpInfo = str_replace('<div class="center">', '<div class="center">' . $pancakeAdd, $phpInfo);
        
        echo $phpInfo;
        return true;
    }
    
    function Pancake_PHPExitHandler($exitmsg = null) {
        echo $exitmsg;
        return !defined('PANCAKE_PHP');
    }
    
    function Pancake_PHPErrorHandler($errtype, $errstr, $errfile = "Unknown", $errline = 0) {
        if((error_reporting() & $errtype) == 0 || error_reporting() == 0)
            return true;
        
        $typeNames = array( E_ERROR => 'Fatal error',
                            E_WARNING => 'Warning',
                            E_PARSE => 'Parse error',
                            E_NOTICE => 'Notice',
                            E_CORE_ERROR => 'PHP Fatal error', 
                            E_CORE_WARNING => 'PHP Warning',
                            E_COMPILE_ERROR => 'PHP Fatal error',
                            E_COMPILE_WARNING => 'PHP Warning',
                            E_USER_ERROR => 'Fatal error',
                            E_USER_WARNING => 'Warning',
                            E_USER_NOTICE => 'Notice',
                            E_STRICT => 'Strict Standards',
                            E_RECOVERABLE_ERROR => 'Catchable fatal error',
                            E_DEPRECATED => 'Deprecated',
                            E_USER_DEPRECATED => 'Deprecated');
        
        $message = $typeNames[$errtype].':  '.$errstr.' in '.$errfile .' on line '.$errline."\n";
        if(ini_get('display_errors'))
            echo $message;
        
        return true;
    }
    
    function is_uploaded_file($filename) {
        global $Pancake_request;
        return in_array($filename, $Pancake_request->getUploadedFileNames());
    }
    
    function move_uploaded_file($filename, $destination) {
        if(!is_uploaded_file($filename))
            return false;
        if(!rename($filename, $destination)) {
            trigger_error("Unable to move '" . $filename . "' to '" . $destination . "'", E_USER_WARNING);
            return false;
        }
        return true;
    }
    
    function register_shutdown_function($callback) {
        global $Pancake_shutdownCalls;
        $shutdownCall['callback'] = $callback;
        
        $args = func_get_args();
        unset($args[0]);
        foreach($args as $arg)
            $shutdownCall['args'][] = $arg;
        
        $Pancake_shutdownCalls[] = $shutdownCall;
        
        return true;
    }
    
    function ob_get_level() {
        return Pancake_ob_get_level_orig() - 1;
    }
    
    function ob_end_clean() {
        if(ob_get_level() > 0)
            return Pancake_ob_end_clean_orig();
        trigger_error('ob_end_clean(): failed to delete buffer. No buffer to delete', E_USER_NOTICE);
        return false;
    }
    
    function ob_end_flush() {
        if(ob_get_level() > 0)
            return Pancake_ob_end_flush_orig();
        trigger_error('ob_end_flush(): failed to delete and flush buffer. No buffer to delete or flush', E_USER_NOTICE);
        return false;
    }
    
    function ob_get_flush() {
        if(ob_get_level() > 0)
            return Pancake_ob_get_flush_orig();
        trigger_error('ob_get_flush(): failed to delete and flush buffer. No buffer to delete or flush', E_USER_NOTICE);
        return false;
    }
    
    function ob_flush() {
        if(ob_get_level() > 0)
            return Pancake_ob_flush_orig();      
    }
    
    function session_start() {
        if(session_id()) {
            return Pancake_session_start_orig();
        } else if($_GET[session_name()]) {
            session_id($_GET[session_name()]);
            return Pancake_session_start_orig();
        } else if($_COOKIE[session_name()]) {
            session_id($_COOKIE[session_name()]);
            return Pancake_session_start_orig();
        } else {
            if(!Pancake_session_start_orig())
                return false;
            return true;
        }
    }
    
    /**
    * Loads a file into Pancakes CodeCache
    * 
    * @param Pancake_vHost $vHost
    * @param string $fileName Filename, relative to the vHosts DocumentRoot
    */
    function Pancake_cacheFile(Pancake_vHost $vHost, $fileName) {
        if($vHost->isExcludedFile($fileName))
            return;
        if(is_dir($vHost->getDocumentRoot() . '/' . $fileName)) {
            //Pancake_out('Scanning directory ' . $vHost->getDocumentRoot() . '/' . $fileName);
            $directory = scandir($vHost->getDocumentRoot() . '/' . $fileName);
            if(substr($fileName, -1, 1) != '/')
                $fileName .= '/';
            foreach($directory as $file)
                if($file != '..' && $file != '.')
                    Pancake_cacheFile($vHost, $fileName . $file);
        } else {
            if(Pancake_MIME::typeOf($vHost->getDocumentRoot() . '/' . $fileName) != 'text/x-php')
                return;
            //Pancake_out('Caching file ' . $vHost->getDocumentRoot() . '/' . $fileName);
            require_once $vHost->getDocumentRoot() . '/' . $fileName;
        }
    }
?>

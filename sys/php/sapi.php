<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* sapi.php                                                     */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
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
    
    if(PHP_MINOR_VERSION >= 4) {
        function http_response_code($response_code = 0) {  
            global $Pancake_request; 
            
            if($response_code)
                $Pancake_request->setAnswerCode($response_code);
            return $Pancake_request->getAnswerCode();
        }
        
        function header_register_callback($callback) {
            global $Pancake_headerCallback;
            if(!is_callable($callback))
                return false;
            $Pancake_headerCallback = $callback;
            return true;
        }
    }
    
    function phpinfo($what = INFO_ALL) {
        global $Pancake_currentThread;
        global $Pancake_request;
        global $Pancake_vHosts;
        
        $Pancake_request->setHeader('Content-Type', 'text/html; charset=utf-8');
        
        // Get original phpinfo
        ob_start();
        if(!Pancake_phpinfo_orig($what))
            return false;
        $phpInfo = ob_get_contents();
        Pancake_ob_end_clean_orig();
        
        // Modify it
        $phpInfo = str_replace('<td class="v">Command Line Interface </td>', '<td class="v">Pancake Embedded SAPI </td>', $phpInfo);
        
        if($Pancake_request->getvHost()->exposePancakeInPHPInfo() && $what == INFO_ALL) {
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
                if($first) $pancakeAdd .= ', ';
                $pancakeAdd .= $include;
                $first = true;
            }
            $pancakeAdd .= '</td></tr>';
            
            $pancakeAdd .= '<tr><td class="e">Running RequestWorkers</td><td class="v">'.Pancake\Config::get('main.requestworkers').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">RequestWorker processing limit</td><td class="v">'.(Pancake\Config::get('main.requestworkerlimit') ? Pancake\Config::get('main.requestworkerlimit') . ' requests' : '<i>no limit</i>').'</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Expose Pancake</td><td class="v">' . (Pancake\Config::get('main.exposepancake') ? 'enabled' : 'disabled') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Timestamps</td><td class="v">' . Pancake\Config::get('main.dateformat') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Sizeprefixes</td><td class="v">' . (Pancake\Config::get('main.sizeprefix') == 'si' ? 'SI' : 'binary') . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Shared Memory</td><td class="v">' . Pancake\formatFilesize(Pancake\Config::get('main.sharedmemory')) . '</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Timeout for read actions</td><td class="v">' . Pancake\Config::get('main.readtimeout') . ' µs</td></tr>';
            $pancakeAdd .= '<tr><td class="e">Current vHost</td><td class="v">' . $Pancake_currentThread->vHost->getName() . '</td></tr>';
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
            
            if($Pancake_request->getvHost()->exposePancakevHostsInPHPInfo()) {
                $pancakeAdd .= '<h1>Virtual Hosts</h1>';
                
                foreach($Pancake_vHosts as $vHost) {
                    if(@in_array($vHost->getID(), $vHostsDone))
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
    
    function filter_input($type, $variable_name, $filter = FILTER_DEFAULT, $options = FILTER_FLAG_NONE) {
        global $Pancake_request; 
        
        // Create bitmask of flags
        if(is_array($options)) {
            foreach((array) $options['flags'] as $option)
                $flags |= $option;
        } else
            $flags = $options;
        
        // Get variable
        switch($type) {
            case INPUT_GET:
                $GET = $Pancake_request->getGETParams();   
                if(!array_key_exists($variable_name, $GET))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $GET[$variable_name];
                break;
            case INPUT_POST:
                $POST = $Pancake_request->getPOSTParams();   
                if(!array_key_exists($variable_name, $POST))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $POST[$variable_name];
                break;
            case INPUT_COOKIE:
                $COOKIE = $Pancake_request->getCookies();   
                if(!array_key_exists($variable_name, $COOKIE))
                    return $flags & FILTER_NULL_ON_FAILURE ? false : null;
                $var = $COOKIE[$variable_name];
                break;
            case INPUT_SERVER:
                $SERVER = $Pancake_request->createSERVER();   
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
        global $Pancake_request;
        
        foreach($definition as $key => $options) {
            switch($type) {
                case INPUT_GET:
                    $GET = $Pancake_request->getGETParams();   
                    if(!array_key_exists($key, $GET)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $GET[$key];
                    break;
                case INPUT_POST:
                    $POST = $Pancake_request->getPOSTParams();   
                    if(!array_key_exists($key, $POST)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $POST[$key];
                    break;
                case INPUT_COOKIE:
                    $COOKIE = $Pancake_request->getCookies();   
                    if(!array_key_exists($key, $COOKIE)) {
                        $endArray[$key] = $options['flags'] & FILTER_NULL_ON_FAILURE ? false : null;
                        continue 2;
                    }
                    $var = $COOKIE[$key];
                    break;
                case INPUT_SERVER:
                    $SERVER = $Pancake_request->createSERVER();   
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
        
        $result = filter_var_array($data, $filterOptions);
        return array_merge($result, $endArray);
    }
    
    function filter_has_var($type, $variable_name) {
        global $Pancake_request;
        
        switch($type) {
            case INPUT_GET:
                return array_key_exists($variable_name, $Pancake_request->getGETParams());
            case INPUT_POST:
                return array_key_exists($variable_name, $Pancake_request->getPOSTParams());
            case INPUT_COOKIE:
                return array_key_exists($variable_name, $Pancake_request->getCookies());
            case INPUT_SERVER:
                return array_key_exists($variable_name, $Pancake_request->createSERVER());
            case INPUT_ENV:
                return array_key_exists($variable_name, $_ENV);
            default:
                return false;
        }
    }
    
    /*function Pancake_lockVariable($varName, $unlock = false) {
        static $lock;
        if($unlock) {
            return $GLOBALS[$varName] = $lock[$varName];
        }
        $lock[$varName] = $GLOBALS[$varName];
    }*/
?>

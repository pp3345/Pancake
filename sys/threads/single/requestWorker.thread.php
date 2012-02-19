<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.thread.php                                     */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    global $Pancake_sockets;
    global $Pancake_vHosts;
    
    if(Pancake_Config::get('main.mimeencoding') === true)
        $fileInfo = new finfo(FILEINFO_MIME);
    else
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    
    function stop() {
        exit;
    }
    
    // Set user and group
    Pancake_setUser();
    
    // Set handler for stop-signals
    pcntl_signal(SIGTERM, 'stop');
    
    $listenSockets = $listenSocketsOrig = $Pancake_sockets;
    
    // Wait for incoming requests     
    while(@socket_select($listenSockets, $x = null, $x = null, 31536000) !== false) {
        
        // Accept connection
        foreach($listenSockets as $socket) {
            if(socket_get_option($socket, SOL_SOCKET, SO_KEEPALIVE) == 1) {
                $requestSocket = $socket;
                break;
            }
            if(!($requestSocket = @socket_accept($socket)))
                goto next;
            
            // Firefox sends HTTP-Request in first TCP-segment while Chrome sends HTTP-Request after TCP-Handshake
            // Set timeout - DoS-protection
            socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
            break;
        }
        
        // Receive data from client
        while($bytes = socket_read($requestSocket, 16384)) {
            if(!$data)
                socket_set_nonblock($requestSocket);
            $data .= $bytes;
        }
        
        // Check if any data was received
        if(!$data) {
            // Check for keep-alive
            if($key = array_search($requestSocket, $listenSocketsOrig, true))
                unset($listenSocketsOrig[$key]);
            // Close socket
            socket_close($requestSocket);
            goto clean;
        }
        
        // Get information about client
        socket_getPeerName($requestSocket, $ip, $port);
    
        // Create HTTPRequest
        try {
            $request = new Pancake_HTTPRequest($currentThread);
            $request->init($data);
        } catch(Pancake_InvalidHTTPRequestException $e) {
            goto write; // EVIL! :O
        }
        
        $_GET = $request->getGETParams();
        
        // Output debug-information to client
        if(isset($_GET['pancakedebug']) && PANCAKE_DEBUG_MODE === true) {
            $request->setHeader('Content-Type', 'text/plain');
                                                    
            $body = 'Received Data:'."\r\n";
            $body .= $request->getRequestLine()."\r\n";
            $body .= $request->getRequestHeaders()."\r\n";
            $body .= 'Dump of RequestObject:'."\r\n";
            $body .= print_r($request, true);
            $request->setAnswerBody($body);
            
            goto write;
        }
        
        // Get time of last modification
        $modified = filemtime($request->getvHost()->getDocumentRoot().$request->getRequestFilePath());
        // Set Last-Modified-Header as RFC 2822
        $request->setHeader('Last-Modified', date('r', $modified));
        
        // Check for If-Modified-Since
        if($request->getRequestHeader('If-Modified-Since'))
            if(strtotime($request->getRequestHeader('If-Modified-Since')) == $modified) {
                $request->setAnswerCode(304);
                goto write;
            }
        
        // Check for directory
        if(is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath())) {
            $directory = scandir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath());
            
            // Build directory listing
            $body =  '<!doctype html>';
            $body .= '<html>';
            $body .= '<head>';
            $body .= '<title>Directory Listing of '.$request->getRequestFilePath().'</title>';
            $body .= '<style>';
                $body .= 'body{font-family:"Arial"}';
                $body .= 'thead{font-weight:bold}';
                $body .= 'hr{border:1px solid #000}';
            $body .= '</style>';
            $body .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            $body .= '</head>';
            $body .= '<body>';
            $body .= '<h1>Index of '.$request->getRequestFilePath().'</h1>';
            $body .= '<hr/>';
            $body .= '<table>';
                $body .= '<thead><tr>';
                    $body .= '<th>Filename</th>';
                    $body .= '<th>Type</th>';
                    $body .= '<th>Last Modified</th>';
                    $body .= '<th>Size</th>';
                $body .= '</tr></thead>';
                $body .= '<tbody>';
                    $body .= '<tr>';
                        $body .= '<td>';
                            $dirname = dirname($request->getRequestFilePath());
                            $body .= '<a href="http://'.$request->getRequestHeader('Host').$dirname.'">../</a>';
                        $body .= '</td>';
                        $body .= '<td>';
                            $body .= 'Directory';
                        $body .= '</td>';
                    $body .= '</tr>';
                    foreach($directory as $file) {
                        if($file == '.' 
                        || $file == '..'
                        || !is_readable($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file))
                            continue;
                        
                        $body .= '<tr>';
                            $body .= '<td>';
                                if(substr($request->getRequestFilePath(), -1) != '/') $add = '/';
                                if(is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file))
                                    $body .= '<a href="http://'.$request->getRequestHeader('Host').$request->getRequestFilePath().$add.$file.'/">'.$file.'/</a>';
                                else
                                    $body .= '<a href="http://'.$request->getRequestHeader('Host').$request->getRequestFilePath().$add.$file.'">'.$file.'</a>';
                            $body .= '</td>';
                            $body .= '<td>';
                                if(is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file))
                                    $body .= 'Directory';
                                else
                                    $body .= $fileInfo->file($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file);
                            $body .= '</td>';
                            $body .= '<td>';
                                $body .= date(Pancake_Config::get('main.dateformat'), filemtime($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file));
                            $body .= '</td>';
                            if(!is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file)) {
                                $body .= '<td>';
                                    $body .= Pancake_formatFilesize(filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file));
                                $body .= '</td>';
                            }
                        $body .= '</tr>';
                    }
                $body .= '</tbody>';
            $body .= '</table>';
            
            if(Pancake_Config::get('main.exposepancake') === true) {
                $body .= '<hr/>';
                $body .= 'Pancake '.PANCAKE_VERSION;
            }
            
            $body .= '</body>';
            $body .= '</html>';
            $request->setAnswerBody($body);
        } else {    
            $request->setHeader('Content-Type', $fileInfo->file($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()));
            $request->setAnswerBody(file_get_contents($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()));
        }
        
        write:
        
        // Get answer and it's size
        $answer = $request->buildAnswer();           
        
        // Check if user wants keep-alive-connection
        if(strtolower($request->getAnswerHeader('Connection')) == 'keep-alive')
            socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Set blocking, so that we don't try to write when socket is unavailable
        socket_set_block($requestSocket);
        
        // Write answer to socket
        socket_write($requestSocket, $answer);
        
        // Close socket
        if(strtolower($request->getAnswerHeader('Connection')) != 'keep-alive')
            socket_shutdown($requestSocket);
        
        // Output request-information
        Pancake_out('REQ '.$request->getAnswerCode().' '.$ip.': '.$request->getRequestLine().' on vHost '.(($request->getvHost()) ? $request->getvHost()->getName() : null).' (via '.$request->getRequestHeader('Host').') - '.$request->getRequestHeader('User-Agent'), REQUEST);
        
        next:
        
        if($request && !in_array($requestSocket, $listenSocketsOrig, true) && strtolower($request->getAnswerHeader('Connection')) == 'keep-alive')
            $listenSocketsOrig[] = $requestSocket;
            
        clean:
        
        $listenSockets = $listenSocketsOrig;
        
        // Clean old request-data
        unset($data);
        unset($bytes);
        unset($request);
        unset($sentTotal);
        unset($answer);
        unset($body);
        unset($directory);
        unset($requestSocket);
        unset($socket);
        unset($_GET);
        unset($add);
        
        // Reset statcache
        clearstatcache();
    }
?>

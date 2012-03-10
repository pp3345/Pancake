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
            
            // Set timeout - DoS-protection
            socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
            break;
        }
        
        // Receive data from client
        while($bytes = socket_read($requestSocket, 1048576)) { // 1 MiB
            $data .= $bytes;
            if(strpos($data, "\r\n\r\n") && strpos($data, "POST") !== 0)
                socket_set_nonblock($requestSocket);
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
            $request = new Pancake_HTTPRequest($Pancake_currentThread, $ip, $port);
            $request->init($data);
        } catch(Pancake_InvalidHTTPRequestException $e) {
            goto write; // EVIL! :O
        }
        
        if($request->getRequestType() == 'TRACE')
            goto write;
        
        // Check for "OPTIONS"-requestmethod
        if($request->getRequestType() == 'OPTIONS') {
            // We can add OPTIONS here without checking if it is allowed because Pancake would already have aborted the request if it wasn't allowed
            $allow = 'GET, POST, OPTIONS';
            if(Pancake_Config::get('main.allowhead') === true)
                $allow .= ', HEAD';
            if(Pancake_Config::get('main.allowtrace') === true)
                $allow .= ', TRACE';
            $request->setHeader('Allow', $allow);
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
        
        // Check for PHP
        if(Pancake_MIME($request->getvHost()->getDocumentRoot() . $request->getRequestFilePath()) == 'text/x-php' && $request->getvHost()->getPHPWorkerAmount()) {
            // Search Available PHP-Worker
            $key = Pancake_PHPWorker::handleRequest($request);
            
            // Wait for PHP-Worker to finish
            Pancake_IPC::get();
            
            // Get updated request-object from Shared Memory
            $request = Pancake_SharedMemory::get($key);
            if($request === false) {
                for($i = 0;$i < 10 && $request === false;$i++) {
                    usleep(1000);
                    $request = Pancake_SharedMemory::get($key);
                }
                if($request === false)
                    continue;
            }
            
            // Remove object from Shared Memory
            Pancake_SharedMemory::delete($key);
            
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
                                    $body .= Pancake_MIME($request->getvHost()->getDocumentRoot() . $request->getRequestFilePath() . '/' . $file);
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
            $request->setHeader('Content-Type', Pancake_MIME($request->getvHost()->getDocumentRoot() . $request->getRequestFilePath())); 
            $request->setHeader('Accept-Ranges', 'bytes'); 
            
            // Check if GZIP-compression should be used  
            if($request->acceptsCompression('gzip') && $request->getvHost()->allowGZIPCompression() === true && filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()) >= $request->getvHost()->getGZIPMimimum()) {
                // Set encoding-header
                $request->setHeader('Transfer-Encoding', 'gzip');
                // Create temporary file
                $gzipPath = tempnam(Pancake_Config::get('main.tmppath'), 'GZIP');
                $gzipFileHandle = gzopen($gzipPath, 'w' . $request->getvHost()->getGZIPLevel());
                // Load uncompressed requested file
                $requestedFileHandle = fopen($request->getvHost()->getDocumentRoot().$request->getRequestFilePath(), 'r');
                // Compress file
                while(!feof($requestedFileHandle))
                    gzwrite($gzipFileHandle, fread($requestedFileHandle, $request->getvHost()->getWriteLimit()));
                // Close GZIP-resource and open normal file-resource
                gzclose($gzipFileHandle);
                $requestFileHandle = fopen($gzipPath, 'r');
                // Set Content-Length
                $request->setHeader('Content-Length', filesize($gzipPath) - $request->getRangeFrom());
                // Check if we should send the file in parts
                if(filesize($gzipPath) - $request->getRangeFrom() > $request->getvHost()->getWriteLimit())
                    $sendParts = true;
            } else {
                $request->setHeader('Content-Length', filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()) - $request->getRangeFrom());
                $requestFileHandle = fopen($request->getvHost()->getDocumentRoot().$request->getRequestFilePath(), 'r');
                if(filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()) - $request->getRangeFrom() > $request->getvHost()->getWriteLimit())
                    $sendParts = true;
            }
            
            // Check if a specific range was requested
            if($request->getRangeFrom()) {
                $request->setAnswerCode(206);
                fseek($requestFileHandle, $request->getRangeFrom());
                if($gzipPath)
                    $request->setHeader('Content-Range', 'bytes ' . $request->getRangeFrom().'-'.(filesize($gzipPath) - 1).'/'.filesize($gzipPath));
                else
                    $request->setHeader('Content-Range', 'bytes ' . $request->getRangeFrom().'-'.(filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()) - 1).'/'.filesize($request->getvHost()->getDocumentRoot().$request->getRequestFilePath()));
            }
            
            $data = fread($requestFileHandle, $request->getvHost()->getWriteLimit());
            $request->setAnswerBody($data);
            unset($data);
        }
        
        write:
        
        // Get answer and its size
        $answer = $request->buildAnswer();           
        
        // Output request-information
        Pancake_out('REQ '.$request->getAnswerCode().' '.$ip.': '.$request->getRequestLine().' on vHost '.(($request->getvHost()) ? $request->getvHost()->getName() : null).' (via '.$request->getRequestHeader('Host').') - '.$request->getRequestHeader('User-Agent'), REQUEST);
        
        // Check if user wants keep-alive-connection
        if($request->getAnswerHeader('Connection') == 'keep-alive')
            socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Set blocking, so that we don't try to write when socket is unavailable
        socket_set_block($requestSocket);
        
        // Write answer to socket
        socket_write($requestSocket, $answer);
        
        // Send parts
        if($sendParts === true && $request->getRequestType() != 'HEAD') {
            while(!feof($requestFileHandle)) {
                $data = fread($requestFileHandle, $request->getvHost()->getWriteLimit());
                if(!socket_write($requestSocket, $data))
                    goto close;
            }
        }
            
        close:
        
        // Close socket
        if(strtolower($request->getAnswerHeader('Connection')) != 'keep-alive')
            socket_shutdown($requestSocket);
        
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
        unset($sendParts);
        if($gzipPath)
            unlink($gzipPath);
        unset($gzipPath);
        if(is_resource($requestFileHandle))
            fclose($requestFileHandle);
        
        gc_collect_cycles();
        
        // Reset statcache
        clearstatcache();
    }
?>

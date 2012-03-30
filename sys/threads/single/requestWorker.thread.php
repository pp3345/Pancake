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
    global $Pancake_postMaxSize;
    
    function stop() {
        exit;
    }
    
    // Set user and group
    Pancake_setUser();
    
    // Set handler for stop-signals
    pcntl_signal(SIGTERM, 'stop');
    
    // Precalculate post_max_size in bytes (so that we don't have to do this on every single POST-request)
    // I assume that K, M and G mean Kibi, Mebi, Gibi - not kilo, Mega, Giga
    $size = strtolower(ini_get('post_max_size'));
    if(strpos($size, 'k'))
        $size = (int) $size * 1024;
    else if(strpos($size, 'm'))
        $size = (int) $size * 1024 * 1024;
    else if(strpos($size, 'g'))
        $size = (int) $size * 1024 * 1024 * 1024;
    $Pancake_postMaxSize = $size;
    
    $listenSockets = $listenSocketsOrig = $Pancake_sockets;

    // Wait for incoming requests     
    while(@socket_select($listenSockets, $liveWriteSockets, $x = null, 31536000) !== false) { 
        if($liveWriteSockets) {
            foreach($liveWriteSockets as $socket) {
                $socketID = (int) $socket;
                $requestSocket = $socket;
                goto liveWrite;
            }
        }
        // Accept connection
        foreach($listenSockets as $socket) {
            if($liveReadSockets[(int) $socket] === true || socket_get_option($socket, SOL_SOCKET, SO_KEEPALIVE) == 1) {
                $socketID = (int) $socket;
                $requestSocket = $socket;
                break;
            }
            if(!($requestSocket = @socket_accept($socket)))
                goto clean;
            
            $socketID = (int) $requestSocket;
            
            // Set timeout - DoS-protection
            socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
            break;
        }
        
        // Receive data from client
        $bytes = socket_read($requestSocket, 1048576);
        
        if(!$bytes)
            goto close;
        
        // Check if request was already initialized and we are only reading POST-data
        if($requests[$socketID] instanceof Pancake_HTTPRequest) {
            $postData[$socketID] .= $bytes;
            if(strlen($postData[$socketID]) >= $requests[$socketID]->getRequestHeader('Content-Length'))
                goto readData;
        } else {
            $socketData[$socketID] .= $bytes;

            // Check for POST
            if(strpos($socketData[$socketID], "\r\n\r\n")) {
                if(strpos($socketData[$socketID], "POST") !== 0)
                    goto readData;
                else {
                    $data = explode("\r\n\r\n", $socketData[$socketID], 2);
                    $socketData[$socketID] = $data[0];
                    $postData[$socketID] = $data[1];
                    goto readData;
                }
            }
        }
        // Event-based reading
        $liveReadSockets[$socketID] = true;
        $listenSocketsOrig[] = $requestSocket;
        goto clean;
        
        readData:
    
        if(!$requests[$socketID]) {
            // Get information about client
            socket_getPeerName($requestSocket, $ip, $port);
            
            // Create HTTPRequest / Read Headers
            try {
                $requests[$socketID] = new Pancake_HTTPRequest($Pancake_currentThread, $ip, $port);
                $requests[$socketID]->init($socketData[$socketID]);
            } catch(Pancake_InvalidHTTPRequestException $e) {
                goto write; // EVIL! :O
            }
        }
        
        // Check for POST and get all POST-data
        if($requests[$socketID]->getRequestType() == 'POST') {
            if(strlen($postData[$socketID]) >= $requests[$socketID]->getRequestHeader('Content-Length'))
                $requests[$socketID]->readPOSTData($postData[$socketID]);
            else {
                // Event-based reading
                $liveReadSockets[$requestSocket] = true;
                $listenSocketsOrig[] = $requestSocket;
                goto clean;
            }
        }
        
        if($requests[$socketID]->getRequestType() == 'TRACE')
            goto write;
        
        // Check for "OPTIONS"-requestmethod
        if($requests[$socketID]->getRequestType() == 'OPTIONS') {
            // We can add OPTIONS here without checking if it is allowed because Pancake would already have aborted the request if it wasn't allowed
            $allow = 'GET, POST, OPTIONS';
            if(Pancake_Config::get('main.allowhead') === true)
                $allow .= ', HEAD';
            if(Pancake_Config::get('main.allowtrace') === true)
                $allow .= ', TRACE';
            $requests[$socketID]->setHeader('Allow', $allow);
        }
        
        $_GET = $requests[$socketID]->getGETParams();
        
        // Output debug-information to client
        if(isset($_GET['pancakedebug']) && PANCAKE_DEBUG_MODE === true) {
            $requests[$socketID]->setHeader('Content-Type', 'text/plain');
                                                    
            $body = 'Received Data:'."\r\n";
            $body .= $requests[$socketID]->getRequestLine()."\r\n";
            $body .= $requests[$socketID]->getRequestHeaders()."\r\n";
            $body .= 'Dump of RequestObject:'."\r\n";
            $body .= print_r($requests[$socketID], true);
            $requests[$socketID]->setAnswerBody($body);
            
            goto write;
        }
        
        // Check for PHP
        if($requests[$socketID]->getMIMEType() == 'text/x-php' && $requests[$socketID]->getvHost()->getPHPWorkerAmount()) {
            if(ini_get('expose_php') == true)
                $requests[$socketID]->setHeader('X-Powered-By', 'PHP/' . PHP_VERSION);
            
            // Search Available PHP-Worker
            $key = Pancake_PHPWorker::handleRequest($requests[$socketID]);
            
            // Wait for PHP-Worker to finish
            Pancake_IPC::get();
            
            // Get updated request-object from Shared Memory
            $requests[$socketID] = Pancake_SharedMemory::get($key);
            if($requests[$socketID] === false) {
                for($i = 0;$i < 10 && $requests[$socketID] === false;$i++) {
                    usleep(1000);
                    $requests[$socketID] = Pancake_SharedMemory::get($key);
                }
                if($requests[$socketID] === false)
                    continue;
            }
            
            // Remove object from Shared Memory
            Pancake_SharedMemory::delete($key);
            
            goto write;
        }
        
        // Get time of last modification
        $modified = filemtime($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath());
        // Set Last-Modified-Header as RFC 2822
        $requests[$socketID]->setHeader('Last-Modified', date('r', $modified));
        
        // Check for If-Modified-Since
        if($requests[$socketID]->getRequestHeader('If-Modified-Since'))
            if(strtotime($requests[$socketID]->getRequestHeader('If-Modified-Since')) == $modified) {
                $requests[$socketID]->setAnswerCode(304);
                goto write;
            }
        
        // Check for directory
        if(is_dir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath())) {
            $directory = scandir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath());
            $requests[$socketID]->setHeader('Content-Type', 'text/html; charset=utf-8');
            
            // Build directory listing
            $body =  '<!doctype html>';
            $body .= '<html>';
            $body .= '<head>';
            $body .= '<title>Index of '.$requests[$socketID]->getRequestFilePath().'</title>';
            $body .= '<style>';
                $body .= 'body{font-family:"Arial"}';
                $body .= 'thead{font-weight:bold}';
                $body .= 'hr{border:1px solid #000}';
            $body .= '</style>';
            $body .= '</head>';
            $body .= '<body>';
            $body .= '<h1>Index of '.$requests[$socketID]->getRequestFilePath().'</h1>';
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
                            $dirname = dirname($requests[$socketID]->getRequestFilePath());
                            $body .= '<a href="http://'.$requests[$socketID]->getRequestHeader('Host').$dirname.'">../</a>';
                        $body .= '</td>';
                        $body .= '<td>';
                            $body .= 'Directory';
                        $body .= '</td>';
                    $body .= '</tr>';
                    foreach($directory as $file) {
                        if($file == '.' 
                        || $file == '..'
                        || !is_readable($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file))
                            continue;
                        
                        $body .= '<tr>';
                            $body .= '<td>';
                                if(substr($requests[$socketID]->getRequestFilePath(), -1) != '/') $add = '/';
                                if(is_dir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file))
                                    $body .= '<a href="http://'.$requests[$socketID]->getRequestHeader('Host').$requests[$socketID]->getRequestFilePath().$add.$file.'/">'.$file.'/</a>';
                                else
                                    $body .= '<a href="http://'.$requests[$socketID]->getRequestHeader('Host').$requests[$socketID]->getRequestFilePath().$add.$file.'">'.$file.'</a>';
                            $body .= '</td>';
                            $body .= '<td>';
                                if(is_dir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file))
                                    $body .= 'Directory';
                                else
                                    $body .= Pancake_MIME::typeOf($requests[$socketID]->getvHost()->getDocumentRoot() . $requests[$socketID]->getRequestFilePath() . '/' . $file);
                            $body .= '</td>';
                            $body .= '<td>';
                                $body .= date(Pancake_Config::get('main.dateformat'), filemtime($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file));
                            $body .= '</td>';
                            if(!is_dir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file)) {
                                $body .= '<td>';
                                    $body .= Pancake_formatFilesize(filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file));
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
            $requests[$socketID]->setAnswerBody($body);
        } else {
            $requests[$socketID]->setHeader('Content-Type', $requests[$socketID]->getMIMEType()); 
            $requests[$socketID]->setHeader('Accept-Ranges', 'bytes'); 
            
            // Check if GZIP-compression should be used  
            if($requests[$socketID]->acceptsCompression('gzip') && $requests[$socketID]->getvHost()->allowGZIPCompression() === true && filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath()) >= $requests[$socketID]->getvHost()->getGZIPMimimum()) {
                // Set encoding-header
                $requests[$socketID]->setHeader('Transfer-Encoding', 'gzip');
                // Create temporary file
                $gzipPath[$socketID] = tempnam(Pancake_Config::get('main.tmppath'), 'GZIP');
                $gzipFileHandle = gzopen($gzipPath[$socketID], 'w' . $requests[$socketID]->getvHost()->getGZIPLevel());
                // Load uncompressed requested file
                $requestedFileHandle = fopen($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath(), 'r');
                // Compress file
                while(!feof($requestedFileHandle))
                    gzwrite($gzipFileHandle, fread($requestedFileHandle, $requests[$socketID]->getvHost()->getWriteLimit()));
                // Close GZIP-resource and open normal file-resource
                gzclose($gzipFileHandle);
                $requestFileHandle[$socketID] = fopen($gzipPath[$socketID], 'r');
                // Set Content-Length
                $requests[$socketID]->setHeader('Content-Length', filesize($gzipPath[$socketID]) - $requests[$socketID]->getRangeFrom());
            } else {
                $requests[$socketID]->setHeader('Content-Length', filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath()) - $requests[$socketID]->getRangeFrom());
                $requestFileHandle[$socketID] = fopen($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath(), 'r');
            }
            
            // Check if a specific range was requested
            if($requests[$socketID]->getRangeFrom()) {
                $requests[$socketID]->setAnswerCode(206);
                fseek($requestFileHandle[$socketID], $requests[$socketID]->getRangeFrom());
                if($gzipPath[$socketID])
                    $requests[$socketID]->setHeader('Content-Range', 'bytes ' . $requests[$socketID]->getRangeFrom().'-'.(filesize($gzipPath[$socketID]) - 1).'/'.filesize($gzipPath[$socketID]));
                else
                    $requests[$socketID]->setHeader('Content-Range', 'bytes ' . $requests[$socketID]->getRangeFrom().'-'.(filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath()) - 1).'/'.filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath()));
            }
        }
        
        write:
        
        // Get AnswerHeaders
        $writeBuffer[$socketID] = $requests[$socketID]->buildAnswerHeaders(); 
    
        // Get AnswerBody if set and RequestMethod isn't HEAD
        if($requests[$socketID]->getRequestType() != 'HEAD')
            $writeBuffer[$socketID] .= $requests[$socketID]->getAnswerBody();          
        
        // Output request-information
        Pancake_out('REQ '.$requests[$socketID]->getAnswerCode().' '.$requests[$socketID]->getRemoteIP().': '.$requests[$socketID]->getRequestLine().' on vHost '.(($requests[$socketID]->getvHost()) ? $requests[$socketID]->getvHost()->getName() : null).' (via '.$requests[$socketID]->getRequestHeader('Host').') - '.$requests[$socketID]->getRequestHeader('User-Agent'), PANCAKE_REQUEST);
        
        // Check if user wants keep-alive-connection
        if($requests[$socketID]->getAnswerHeader('Connection') == 'keep-alive')
            socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Set socket to non-blocking so that we can dynamically write as much data as the client may receive at this time
        socket_set_nonblock($requestSocket);
        
        liveWrite:
        
        // Add data to buffer if not all data was sent yet
        if(strlen($writeBuffer[$socketID]) < $requests[$socketID]->getvHost()->getWriteLimit() && !@feof($requestFileHandle[$socketID]) && is_resource($requestFileHandle[$socketID]) && $requests[$socketID]->getRequestType() != 'HEAD')
            $writeBuffer[$socketID] .= fread($requestFileHandle[$socketID], $requests[$socketID]->getvHost()->getWriteLimit() - strlen($writeBuffer[$socketID]));
        
        // Write data to socket   
        if(($writtenLength = socket_write($requestSocket, $writeBuffer[$socketID])) === false)
            goto close;
        // Remove written data from buffer
        $writeBuffer[$socketID] = substr($writeBuffer[$socketID], $writtenLength);
        // Check if more data is available
        if(strlen($writeBuffer[$socketID]) || (!@feof($requestFileHandle[$socketID]) && is_resource($requestFileHandle[$socketID]))) {
            // Event-based writing - In the time the client is still downloading we can process other requests
            if(!@in_array($requestSocket, $liveWriteSocketsOrig))
                $liveWriteSocketsOrig[] = $requestSocket;
            goto clean;
        }
        // Remove Socket from LiveWrite after finishing
        if(($index = @array_search($requestSocket, $liveWriteSocketsOrig)) !== false)
            unset($liveWriteSocketsOrig[$index]);
               
        close:
        
        // Close socket
        if(!($requests[$socketID] instanceof Pancake_HTTPRequest) || strtolower($requests[$socketID]->getAnswerHeader('Connection')) != 'keep-alive') {
            socket_shutdown($requestSocket);
            socket_close($requestSocket);
            
            if($key = array_search($requestSocket, $listenSocketsOrig))
                unset($listenSocketsOrig[$key]);
        }
        
        next:
        
        if($requests[$socketID] instanceof Pancake_HTTPRequest) {
            if($requests[$socketID] && !in_array($requestSocket, $listenSocketsOrig, true) && strtolower($requests[$socketID]->getAnswerHeader('Connection')) == 'keep-alive')
                $listenSocketsOrig[] = $requestSocket;
            
            if($requests[$socketID]->getUploadedFiles())    
                foreach($requests[$socketID]->getUploadedFiles() as $file)
                    @unlink($file['tmp_name']); 
        }
        
        unset($socketData[$socketID]);
        unset($postData[$socketID]);
        unset($requests[$socketID]);
        unset($writeBuffer[$socketID]);
        if($gzipPath[$socketID])
            unlink($gzipPath[$socketID]);
        unset($gzipPath[$socketID]);
        
        if(is_resource($requestFileHandle[$socketID]))
            fclose($requestFileHandle[$socketID]);
                
        clean:
        
        $listenSockets = $listenSocketsOrig;
        $liveWriteSockets = $liveWriteSocketsOrig;
        
        // Clean old request-data
        unset($data);
        unset($bytes);
        unset($sentTotal);
        unset($answer);
        unset($body);
        unset($directory);
        unset($requestSocket);
        unset($socket);
        unset($_GET);
        unset($add);
        unset($continue);
        
        gc_collect_cycles();
        
        // Reset statcache
        clearstatcache();
    }
?>

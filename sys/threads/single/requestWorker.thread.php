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
                $requestSocket = $socket;
                goto liveWrite;
            }
        }
        // Accept connection
        foreach($listenSockets as $socket) {
            if($liveReadSockets[$socket] === true || socket_get_option($socket, SOL_SOCKET, SO_KEEPALIVE) == 1) {
                $requestSocket = $socket;
                break;
            }
            if(!($requestSocket = @socket_accept($socket)))
                goto clean;
            
            // Set timeout - DoS-protection
            socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
            break;
        }
        
        // Receive data from client
        while($bytes .= socket_read($requestSocket, 1048576)) { // 1 MiB
            // Check if request was already initialized and we are only reading POST-data
            if($requests[$requestSocket] instanceof Pancake_HTTPRequest) {
                $postData[$requestSocket] .= $bytes;
                if(strlen($postData[$requestSocket]) >= $requests[$requestSocket]->getRequestHeader('Content-Length'))
                    goto readData;
            } else {
                $socketData[$requestSocket] .= $bytes;

                // Check for POST
                if(strpos($socketData[$requestSocket], "\r\n\r\n") && strpos($socketData[$requestSocket], "POST") !== 0)
                    goto readData;
                else if(strpos($socketData[$requestSocket], "\r\n\r\n") && strpos($socketData[$requestSocket], "POST") === 0) {
                    $data = explode("\r\n\r\n", $socketData[$requestSocket], 2);
                    $socketData[$requestSocket] = $data[0];
                    $postData[$requestSocket] = $data[1];
                    goto readData;
                }
            }
            // Event-based reading
            $liveReadSockets[$requestSocket] = true;
            $listenSocketsOrig[] = $requestSocket;
            goto clean;
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
        
        readData:
    
        if(!$requests[$requestSocket]) {
            // Get information about client
            socket_getPeerName($requestSocket, $ip, $port);
            
            // Create HTTPRequest / Read Headers
            try {
                $requests[$requestSocket] = new Pancake_HTTPRequest($Pancake_currentThread, $ip, $port);
                $requests[$requestSocket]->init($socketData[$requestSocket]);
            } catch(Pancake_InvalidHTTPRequestException $e) {
                goto write; // EVIL! :O
            }
        }
        
        // Check for POST and get all POST-data
        if($requests[$requestSocket]->getRequestType() == 'POST') {
            if(strlen($postData[$requestSocket]) >= $requests[$requestSocket]->getRequestHeader('Content-Length'))
                $requests[$requestSocket]->readPOSTData($postData[$requestSocket]);
            else {
                // Event-based reading
                $liveReadSockets[$requestSocket] = true;
                $listenSocketsOrig[] = $requestSocket;
                goto clean;
            }
        }
        
        if($requests[$requestSocket]->getRequestType() == 'TRACE')
            goto write;
        
        // Check for "OPTIONS"-requestmethod
        if($requests[$requestSocket]->getRequestType() == 'OPTIONS') {
            // We can add OPTIONS here without checking if it is allowed because Pancake would already have aborted the request if it wasn't allowed
            $allow = 'GET, POST, OPTIONS';
            if(Pancake_Config::get('main.allowhead') === true)
                $allow .= ', HEAD';
            if(Pancake_Config::get('main.allowtrace') === true)
                $allow .= ', TRACE';
            $requests[$requestSocket]->setHeader('Allow', $allow);
        }
        
        $_GET = $requests[$requestSocket]->getGETParams();
        
        // Output debug-information to client
        if(isset($_GET['pancakedebug']) && PANCAKE_DEBUG_MODE === true) {
            $requests[$requestSocket]->setHeader('Content-Type', 'text/plain');
                                                    
            $body = 'Received Data:'."\r\n";
            $body .= $requests[$requestSocket]->getRequestLine()."\r\n";
            $body .= $requests[$requestSocket]->getRequestHeaders()."\r\n";
            $body .= 'Dump of RequestObject:'."\r\n";
            $body .= print_r($requests[$requestSocket], true);
            $requests[$requestSocket]->setAnswerBody($body);
            
            goto write;
        }
        
        // Check for PHP
        if($requests[$requestSocket]->getMIMEType() == 'text/x-php' && $requests[$requestSocket]->getvHost()->getPHPWorkerAmount()) {
            // Search Available PHP-Worker
            $key = Pancake_PHPWorker::handleRequest($requests[$requestSocket]);
            
            // Wait for PHP-Worker to finish
            Pancake_IPC::get();
            
            // Get updated request-object from Shared Memory
            $requests[$requestSocket] = Pancake_SharedMemory::get($key);
            if($requests[$requestSocket] === false) {
                for($i = 0;$i < 10 && $requests[$requestSocket] === false;$i++) {
                    usleep(1000);
                    $requests[$requestSocket] = Pancake_SharedMemory::get($key);
                }
                if($requests[$requestSocket] === false)
                    continue;
            }
            
            if(ini_get('expose_php') == true)
                $requests[$requestSocket]->setHeader('X-Powered-By', 'PHP/' . PHP_VERSION);
            
            // Remove object from Shared Memory
            Pancake_SharedMemory::delete($key);
            
            goto write;
        }
        
        // Get time of last modification
        $modified = filemtime($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath());
        // Set Last-Modified-Header as RFC 2822
        $requests[$requestSocket]->setHeader('Last-Modified', date('r', $modified));
        
        // Check for If-Modified-Since
        if($requests[$requestSocket]->getRequestHeader('If-Modified-Since'))
            if(strtotime($requests[$requestSocket]->getRequestHeader('If-Modified-Since')) == $modified) {
                $requests[$requestSocket]->setAnswerCode(304);
                goto write;
            }
        
        // Check for directory
        if(is_dir($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath())) {
            $directory = scandir($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath());
            
            // Build directory listing
            $body =  '<!doctype html>';
            $body .= '<html>';
            $body .= '<head>';
            $body .= '<title>Directory Listing of '.$requests[$requestSocket]->getRequestFilePath().'</title>';
            $body .= '<style>';
                $body .= 'body{font-family:"Arial"}';
                $body .= 'thead{font-weight:bold}';
                $body .= 'hr{border:1px solid #000}';
            $body .= '</style>';
            $body .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            $body .= '</head>';
            $body .= '<body>';
            $body .= '<h1>Index of '.$requests[$requestSocket]->getRequestFilePath().'</h1>';
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
                            $dirname = dirname($requests[$requestSocket]->getRequestFilePath());
                            $body .= '<a href="http://'.$requests[$requestSocket]->getRequestHeader('Host').$dirname.'">../</a>';
                        $body .= '</td>';
                        $body .= '<td>';
                            $body .= 'Directory';
                        $body .= '</td>';
                    $body .= '</tr>';
                    foreach($directory as $file) {
                        if($file == '.' 
                        || $file == '..'
                        || !is_readable($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file))
                            continue;
                        
                        $body .= '<tr>';
                            $body .= '<td>';
                                if(substr($requests[$requestSocket]->getRequestFilePath(), -1) != '/') $add = '/';
                                if(is_dir($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file))
                                    $body .= '<a href="http://'.$requests[$requestSocket]->getRequestHeader('Host').$requests[$requestSocket]->getRequestFilePath().$add.$file.'/">'.$file.'/</a>';
                                else
                                    $body .= '<a href="http://'.$requests[$requestSocket]->getRequestHeader('Host').$requests[$requestSocket]->getRequestFilePath().$add.$file.'">'.$file.'</a>';
                            $body .= '</td>';
                            $body .= '<td>';
                                if(is_dir($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file))
                                    $body .= 'Directory';
                                else
                                    $body .= Pancake_MIME::typeOf($requests[$requestSocket]->getvHost()->getDocumentRoot() . $requests[$requestSocket]->getRequestFilePath() . '/' . $file);
                            $body .= '</td>';
                            $body .= '<td>';
                                $body .= date(Pancake_Config::get('main.dateformat'), filemtime($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file));
                            $body .= '</td>';
                            if(!is_dir($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file)) {
                                $body .= '<td>';
                                    $body .= Pancake_formatFilesize(filesize($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath().'/'.$file));
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
            $requests[$requestSocket]->setAnswerBody($body);
        } else {
            $requests[$requestSocket]->setHeader('Content-Type', $requests[$requestSocket]->getMIMEType()); 
            $requests[$requestSocket]->setHeader('Accept-Ranges', 'bytes'); 
            
            // Check if GZIP-compression should be used  
            if($requests[$requestSocket]->acceptsCompression('gzip') && $requests[$requestSocket]->getvHost()->allowGZIPCompression() === true && filesize($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath()) >= $requests[$requestSocket]->getvHost()->getGZIPMimimum()) {
                // Set encoding-header
                $requests[$requestSocket]->setHeader('Transfer-Encoding', 'gzip');
                // Create temporary file
                $gzipPath = tempnam(Pancake_Config::get('main.tmppath'), 'GZIP');
                $gzipFileHandle = gzopen($gzipPath, 'w' . $requests[$requestSocket]->getvHost()->getGZIPLevel());
                // Load uncompressed requested file
                $requestedFileHandle = fopen($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath(), 'r');
                // Compress file
                while(!feof($requestedFileHandle))
                    gzwrite($gzipFileHandle, fread($requestedFileHandle, $requests[$requestSocket]->getvHost()->getWriteLimit()));
                // Close GZIP-resource and open normal file-resource
                gzclose($gzipFileHandle);
                $requestFileHandle[$requestSocket] = fopen($gzipPath, 'r');
                // Set Content-Length
                $requests[$requestSocket]->setHeader('Content-Length', filesize($gzipPath) - $requests[$requestSocket]->getRangeFrom());
            } else {
                $requests[$requestSocket]->setHeader('Content-Length', filesize($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath()) - $requests[$requestSocket]->getRangeFrom());
                $requestFileHandle[$requestSocket] = fopen($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath(), 'r');
            }
            
            // Check if a specific range was requested
            if($requests[$requestSocket]->getRangeFrom()) {
                $requests[$requestSocket]->setAnswerCode(206);
                fseek($requestFileHandle[$requestSocket], $requests[$requestSocket]->getRangeFrom());
                if($gzipPath)
                    $requests[$requestSocket]->setHeader('Content-Range', 'bytes ' . $requests[$requestSocket]->getRangeFrom().'-'.(filesize($gzipPath) - 1).'/'.filesize($gzipPath));
                else
                    $requests[$requestSocket]->setHeader('Content-Range', 'bytes ' . $requests[$requestSocket]->getRangeFrom().'-'.(filesize($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath()) - 1).'/'.filesize($requests[$requestSocket]->getvHost()->getDocumentRoot().$requests[$requestSocket]->getRequestFilePath()));
            }
        }
        
        write:
        
        // Get AnswerHeaders
        $writeBuffer[$requestSocket] = $requests[$requestSocket]->buildAnswerHeaders(); 
    
        // Get AnswerBody if set
        $writeBuffer[$requestSocket] .= $requests[$requestSocket]->getAnswerBody();          
        
        // Output request-information
        Pancake_out('REQ '.$requests[$requestSocket]->getAnswerCode().' '.$requests[$requestSocket]->getRemoteIP().': '.$requests[$requestSocket]->getRequestLine().' on vHost '.(($requests[$requestSocket]->getvHost()) ? $requests[$requestSocket]->getvHost()->getName() : null).' (via '.$requests[$requestSocket]->getRequestHeader('Host').') - '.$requests[$requestSocket]->getRequestHeader('User-Agent'), REQUEST);
        
        // Check if user wants keep-alive-connection
        if($requests[$requestSocket]->getAnswerHeader('Connection') == 'keep-alive')
            socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Set socket to non-blocking so that we can dynamically write as much data as the client may receive at this time
        socket_set_nonblock($requestSocket);
        
        liveWrite:
        
        // Add data to buffer if not all data was sent yet
        if(strlen($writeBuffer[$requestSocket]) < $requests[$requestSocket]->getvHost()->getWriteLimit() && !@feof($requestFileHandle[$requestSocket]) && is_resource($requestFileHandle[$requestSocket]))
            $writeBuffer[$requestSocket] .= fread($requestFileHandle[$requestSocket], $requests[$requestSocket]->getvHost()->getWriteLimit() - strlen($writeBuffer[$requestSocket]));
        
        // Write data to socket   
        if(($writtenLength = socket_write($requestSocket, $writeBuffer[$requestSocket])) === false)
            goto close;
        // Remove written data from buffer
        $writeBuffer[$requestSocket] = substr($writeBuffer[$requestSocket], $writtenLength);
        // Check if more data is available
        if(strlen($writeBuffer[$requestSocket]) || (!@feof($requestFileHandle[$requestSocket]) && is_resource($requestFileHandle[$requestSocket]))) {
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
        if(strtolower($requests[$requestSocket]->getAnswerHeader('Connection')) != 'keep-alive') {
            socket_shutdown($requestSocket);
            socket_close($requestSocket);
        }
        
        next:
        
        if($requests[$requestSocket] && !in_array($requestSocket, $listenSocketsOrig, true) && strtolower($requests[$requestSocket]->getAnswerHeader('Connection')) == 'keep-alive')
            $listenSocketsOrig[] = $requestSocket;
        
        if($requests[$requestSocket]->getUploadedFiles())    
            foreach($requests[$requestSocket]->getUploadedFiles() as $file)
                @unlink($file['tmp_name']);
        
        unset($socketData[$requestSocket]);
        unset($postData[$requestSocket]);
        unset($requests[$requestSocket]);
        unset($sendParts[$requestSocket]);
        unset($writeBuffer[$requestSocket]);
        
        if(is_resource($requestFileHandle[$requestSocket]))
            fclose($requestFileHandle[$requestSocket]);
                
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
        if($gzipPath)
            unlink($gzipPath);
        unset($gzipPath);
        
        gc_collect_cycles();
        
        // Reset statcache
        clearstatcache();
    }
?>

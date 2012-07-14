<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.thread.php                                     */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    global $Pancake_sockets;
    global $Pancake_vHosts;
    global $Pancake_postMaxSize;
    
    require_once 'invalidHTTPRequest.exception.php';
    
    // Precalculate post_max_size in bytes
    $size = strtolower(ini_get('post_max_size'));
    if(strpos($size, 'k'))
        $size = (int) $size * 1024;
    else if(strpos($size, 'm'))
        $size = (int) $size * 1024 * 1024;
    else if(strpos($size, 'g'))
        $size = (int) $size * 1024 * 1024 * 1024;
    $Pancake_postMaxSize = $size;
    
    $listenSockets = $listenSocketsOrig = $Pancake_sockets;
    
    // Initialize some variables
    $decliningNewRequests = false;
    $liveWriteSocketsOrig = array();
    $liveReadSockets = array();
    $socketData = array();
    $postData = array();
    $processedRequests = 0;
    $requests = array();
    $requestFileHandle = array();
    $gzipPath = array();
    $writeBuffer = array();
    $phpSockets = array();
    $waitSlots = array();
    $waits = array();
    
    // Ready
    $Pancake_currentThread->parentSignal(\SIGUSR1);
    
    // Set user and group
    setUser();
    
    // Wait for incoming requests     
    while(socket_select($listenSockets, $liveWriteSockets, $x, $waitSlots && Config::get('main.waitslottime') ? 0 : null, $waitSlots && Config::get('main.waitslottime') ? Config::get('main.waitslottime') : null) !== false) {
    	// If there are jobs left in the queue at the end of the job-run, we're going to jump back to this point to execute the jobs that are left
    	cycle:
    	
    	// Check if there are requests waiting for a PHPWorker
    	foreach((array) $waitSlots as $socketID => $requestSocket) {
			unset($waitSlots[$socketID]);
    		goto load;
    	}
    	
    	// Upload to clients that are ready to receive
        foreach((array) $liveWriteSockets as $index => $requestSocket) {
        	unset($liveWriteSockets[$index]);
        	
        	$socketID = (int) $requestSocket;
            goto liveWrite;
        }
        
        // New connection, downloadable content from a client or the PHP-SAPI finished a request
        foreach($listenSockets as $index => $socket) {
        	unset($listenSockets[$index]);
        	
            if(isset($liveReadSockets[(int) $socket]) || socket_get_option($socket, \SOL_SOCKET, \SO_KEEPALIVE)) {
                $socketID = (int) $socket;
                $requestSocket = $socket;
                break;
            }
            if(isset($phpSockets[(int) $socket])) {
                $requestSocket = $phpSockets[(int) $socket];
                $socketID = (int) $requestSocket;
                
                $packages = hexdec(socket_read($socket, 8));
                $length = hexdec(socket_read($socket, 8));
                
                if($packages > 1) {
                	$sockData = "";
                	
                	socket_set_block($socket);
                	
                	while($packages--)
                		$sockData .= socket_read($socket, $length);
                	
                	$obj = unserialize($sockData);
                }
                else
                	$obj = unserialize(socket_read($socket, $length));
                
                if($obj instanceof HTTPRequest && !($requests[$socketID]->getvHost()->shouldCompareObjects() && (string) $requests[$socketID] != (string) $obj))
                	$requests[$socketID] = $obj;
                else
                	$requests[$socketID]->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
                
                unset($listenSocketsOrig[array_search($socket, $listenSocketsOrig)]);
                unset($phpSockets[(int) $socket]);
                
                socket_close($socket);
                unset($socket);
                unset($obj);
                
                goto write;
            }

            if((Config::get('main.maxconcurrent') < count($listenSocketsOrig) - count($Pancake_sockets) && Config::get('main.maxconcurrent') != 0) || !($requestSocket = @socket_accept($socket)))
                goto clean;
            $socketID = (int) $requestSocket;

            $socketData[$socketID] = "";
            
            // Set O_NONBLOCK-flag
            socket_set_nonblock($requestSocket);
            break;
        }
        
        // Receive data from client
        if(isset($requests[$socketID]))
            $bytes = @socket_read($requestSocket, $requests[$socketID]->getRequestHeader('Content-Length') - strlen($postData[$socketID]));
        else
            $bytes = @socket_read($requestSocket, 10240);
        
        // socket_read() might return string(0) "" while the socket keeps at non-blocking state - This happens when the client closes the connection under certain conditions
        // We should not close the socket if socket_read() returns bool(false) - This might lead to problems with slow connections
        if($bytes === "")
            goto close;
        
        // Check if request was already initialized and we are only reading POST-data
        if(isset($requests[$socketID])) {
            $postData[$socketID] .= $bytes;
            if(strlen($postData[$socketID]) >= $requests[$socketID]->getRequestHeader('Content-Length'))
                goto readData;
        } else {
            $socketData[$socketID] .= $bytes;

            // Check if all headers were received
            if(strpos($socketData[$socketID], "\r\n\r\n")) {
                // Check for POST
                if(strpos($socketData[$socketID], "POST") === 0) {
                    $data = explode("\r\n\r\n", $socketData[$socketID], 2);
                    $socketData[$socketID] = $data[0];
                    $postData[$socketID] = $data[1];
                }

                goto readData;
            }
            
            // Avoid memory exhaustion by just sending random but long data that does not contain \r\n\r\n
            // I assume that no normal HTTP-header will be longer than 10 KiB
            if(strlen($socketData[$socketID]) >= 10240)
                goto close;
        }
        // Event-based reading
        if(!in_array($requestSocket, $listenSocketsOrig)) {
            $liveReadSockets[$socketID] = true;
            $listenSocketsOrig[] = $requestSocket;
        }
        goto clean;
        
        readData:
    
        if(!isset($requests[$socketID])) {
            // Get information about client
            socket_getPeerName($requestSocket, $ip, $port);
            
            // Get local IP-address and port
            socket_getSockName($requestSocket, $lip, $lport);
            
            // Create request object / Read Headers
            try {
                $requests[$socketID] = new HTTPRequest($Pancake_currentThread, $ip, $port, $lip, $lport);
                $requests[$socketID]->init($socketData[$socketID]);
            } catch(invalidHTTPRequestException $e) {
                $requests[$socketID]->invalidRequest($e);
                goto write;
            }
        }
        
        // Check for POST and get all POST-data
        if($requests[$socketID]->getRequestType() == 'POST') {
            if(strlen($postData[$socketID]) >= $requests[$socketID]->getRequestHeader('Content-Length')) {
                if(strlen($postData[$socketID]) > $requests[$socketID]->getRequestHeader('Content-Length'))
                    $postData[$socketID] = substr($postData[$socketID], 0, $requests[$socketID]->getRequestHeader('Content-Length'));
                if($key = array_search($requestSocket, $listenSocketsOrig))
                    unset($listenSocketsOrig[$key]);
                $requests[$socketID]->readPOSTData($postData[$socketID]);
            } else {
                // Event-based reading
                if(!in_array($requestSocket, $listenSocketsOrig)) {
                    $liveReadSockets[$socketID] = true;
                    $listenSocketsOrig[] = $requestSocket;
                }
                goto clean;
            }
        } else if($key = array_search($requestSocket, $listenSocketsOrig))
            unset($listenSocketsOrig[$key]);
        
        if($requests[$socketID]->getRequestType() == 'TRACE')
            goto write;
        
        // Check for "OPTIONS"-requestmethod
        if($requests[$socketID]->getRequestType() == 'OPTIONS') {
            $allow = 'GET, POST, OPTIONS';
            if(Config::get('main.allowhead') === true)
                $allow .= ', HEAD';
            if(Config::get('main.allowtrace') === true)
                $allow .= ', TRACE';
            $requests[$socketID]->setHeader('Allow', $allow);
        }
        
        // Output debug information
        if(DEBUG_MODE === true && array_key_exists('pancakedebug', $requests[$socketID]->getGETParams())) {
            $requests[$socketID]->setHeader('Content-Type', 'text/plain');
                                                    
            $body = 'Received Headers:'."\r\n";
            $body .= $requests[$socketID]->getRequestLine()."\r\n";
            $body .= $requests[$socketID]->getRequestHeaders()."\r\n";
            $body .= 'Received POST content:'."\r\n";
            $body .= $postData[$socketID] . "\r\n\r\n";
            $body .= 'Dump of RequestObject:'."\r\n";
            $body .= print_r($requests[$socketID], true);
            $requests[$socketID]->setAnswerBody($body);
            
            goto write;
        }
        
        if(ini_get('expose_php') && array_key_exists("", $requests[$socketID]->getGETParams())) {
            $_GET = $requests[$socketID]->getGETParams();
            switch($_GET[""]) {
                case 'PHPE9568F34-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/php.gif');
                    $requests[$socketID]->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPE9568F35-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/zend.gif');
                    $requests[$socketID]->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPE9568F36-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/php_egg.gif');
                    $requests[$socketID]->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPB8B5F2A0-3C92-11d3-A3A9-4C7B08C10000':
                    ob_start();
                    phpcredits();
                    $requests[$socketID]->setHeader('Content-Type', 'text/html');
                    $logo = ob_get_contents();
                    PHPFunctions\OutputBuffering\endClean();
                break;
                case 'PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B':
                    if(Config::get('main.exposepancake') === true) {
                        $logo = file_get_contents('logo/pancake.png');
                        $requests[$socketID]->setHeader('Content-Type', 'image/png');
                    } else
                        goto load;
                break;
                default:
                    goto load;
            }
            $requests[$socketID]->setAnswerBody($logo);
            unset($logo);
            goto write;
        }
        
        load:
        
        // Check for PHP
        if($requests[$socketID]->getMIMEType() == 'text/x-php' && $requests[$socketID]->getvHost()->getPHPWorkerAmount()) {
            $socket = socket_create(\AF_UNIX, \SOCK_SEQPACKET, 0);
            socket_set_nonblock($socket);
            // @ - Do not spam errorlog with Resource temporarily unavailable if there is no PHPWorker available
            @socket_connect($socket, $requests[$socketID]->getvHost()->getSocketName());
           	
            if(socket_last_error($socket) == 11) {
      			$waits[$socketID]++;
            	
            	if($waits[$socketID] > Config::get('main.waitslotwaitlimit')) {
            		$requests[$socketID]->invalidRequest(new invalidHTTPRequestException('There was no worker available to serve your request. Please try again later.', 500));
            		goto write;
            	}	
            	
            	$waitSlotsOrig[$socketID] = $requestSocket;
            	
            	goto clean;
            }
            
            unset($waitSlotsOrig[$socketID]);
            unset($waits[$socketID]);
            
            $data = serialize($requests[$socketID]);
            
            $packages = array();
            
            if(strlen($data) > (socket_get_option($socket, \SOL_SOCKET, \SO_SNDBUF) - 1024)
            && (socket_set_option($socket, \SOL_SOCKET, \SO_SNDBUF, strlen($data) + 1024) + 1)
            && strlen($data) > (socket_get_option($socket, \SOL_SOCKET, \SO_SNDBUF) - 1024)) {
            	$packageSize = socket_get_option($socket, \SOL_SOCKET, \SO_SNDBUF) - 1024;
            
            	for($i = 0;$i < ceil(strlen($data) / $packageSize);$i++)
            		$packages[] = substr($data, $i * $packageSize, $packageSize);
            } else
            		$packages[] = $data;
            		 
            // First transmit the length of the serialized object, then the object itself
            socket_write($socket, dechex(count($packages)));
            socket_write($socket, dechex(strlen($packages[0])));
            foreach($packages as $data)
            	socket_write($socket, $data);
            
            unset($packages);
            
            $listenSocketsOrig[] = $socket;
            $phpSockets[(int) $socket] = $requestSocket;
            
            goto clean;
        }
        
        // Get time of last modification
        $modified = filemtime($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath());
        // Set Last-Modified-Header as RFC 2822
        $requests[$socketID]->setHeader('Last-Modified', date('r', $modified));
        
        // Check for If-Modified-Since
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
                                    $body .= MIME::typeOf($requests[$socketID]->getvHost()->getDocumentRoot() . $requests[$socketID]->getRequestFilePath() . '/' . $file);
                            $body .= '</td>';
                            $body .= '<td>';
                                $body .= date(Config::get('main.dateformat'), filemtime($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file));
                            $body .= '</td>';
                            if(!is_dir($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file)) {
                                $body .= '<td>';
                                    $body .= formatFilesize(filesize($requests[$socketID]->getvHost()->getDocumentRoot().$requests[$socketID]->getRequestFilePath().'/'.$file));
                                $body .= '</td>';
                            }
                        $body .= '</tr>';
                    }
                $body .= '</tbody>';
            $body .= '</table>';
            
            if(Config::get('main.exposepancake') === true) {
                $body .= '<hr/>';
                $body .= 'Pancake '.VERSION;
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
                $requests[$socketID]->setHeader('Content-Encoding', 'gzip');
                // Create temporary file
                $gzipPath[$socketID] = tempnam(Config::get('main.tmppath'), 'GZIP');
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

        // Get Answer Headers
        $writeBuffer[$socketID] = $requests[$socketID]->buildAnswerHeaders();

        // Get Answer Body if set and request method isn't HEAD
        if($requests[$socketID]->getRequestType() != 'HEAD')
            $writeBuffer[$socketID] .= $requests[$socketID]->getAnswerBody();

        // Output request information
        out('REQ '.$requests[$socketID]->getAnswerCode().' '.$requests[$socketID]->getRemoteIP().': '.$requests[$socketID]->getRequestLine().' on vHost '.(($requests[$socketID]->getvHost()) ? $requests[$socketID]->getvHost()->getName() : null).' (via '.$requests[$socketID]->getRequestHeader('Host').' from '.$requests[$socketID]->getRequestHeader('Referer').') - '.$requests[$socketID]->getRequestHeader('User-Agent'), REQUEST);

        // Check if user wants keep-alive connection
        if($requests[$socketID]->getAnswerHeader('Connection') == 'keep-alive')
            socket_set_option($requestSocket, \SOL_SOCKET, \SO_KEEPALIVE, 1);

        // Increment amount of processed requests
        $processedRequests++;
        
        // Clean some data now to improve RAM usage
        unset($socketData[$socketID]);
        unset($postData[$socketID]);
        unset($liveReadSockets[$socketID]);

        liveWrite:
        
        // The buffer should usually only be empty if the hard limit was reached - In this case Pancake won't allocate any buffers except when the client really IS ready to receive data
        if(!strlen($writeBuffer[$socketID]))
        	$writeBuffer[$socketID] = fread($requestFileHandle[$socketID], Config::get('main.writebuffermin'));
        
        // Write data to socket
        if(($writtenLength = @socket_write($requestSocket, $writeBuffer[$socketID])) === false)
            goto close;
        // Remove written data from buffer
        $writeBuffer[$socketID] = substr($writeBuffer[$socketID], $writtenLength);
        
        // Add data to buffer if not all data was sent yet
        if(strlen($writeBuffer[$socketID]) < Config::get('main.writebuffermin') && is_resource($requestFileHandle[$socketID]) && !feof($requestFileHandle[$socketID]) && !(count($writeBuffer) > Config::get('main.writebufferhardmaxconcurrent') && Config::get('main.writebufferhardmaxconcurrent')) && $requests[$socketID]->getRequestType() != 'HEAD' && $writtenLength)
        	$writeBuffer[$socketID] .= fread($requestFileHandle[$socketID], (count($writeBuffer) > Config::get('main.writebuffersoftmaxconcurrent') && Config::get('main.writebuffersoftmaxconcurrent') ? Config::get('main.writebuffermin') : $requests[$socketID]->getvHost()->getWriteLimit()) - strlen($writeBuffer[$socketID]));

        // Check if more data is available
        if(strlen($writeBuffer[$socketID]) || (is_resource($requestFileHandle[$socketID]) && !feof($requestFileHandle[$socketID]) && $requests[$socketID]->getRequestType() != 'HEAD')) {
            // Event-based writing - In the time the client is still downloading we can process other requests
            if(!@in_array($requestSocket, $liveWriteSocketsOrig))
                $liveWriteSocketsOrig[] = $requestSocket;
            goto clean;
        }

        close:

        // Close socket
        if(!isset($requests[$socketID]) || $requests[$socketID]->getAnswerHeader('Connection') != 'keep-alive') {
            @socket_shutdown($requestSocket);
            socket_close($requestSocket);

            if($key = array_search($requestSocket, $listenSocketsOrig))
                unset($listenSocketsOrig[$key]);
        }
        
        if(isset($requests[$socketID])) {
            if(!in_array($requestSocket, $listenSocketsOrig, true) && $requests[$socketID]->getAnswerHeader('Connection') == 'keep-alive')
                $listenSocketsOrig[] = $requestSocket;
               
            foreach((array) $requests[$socketID]->getUploadedFiles() as $file)
                @unlink($file['tmp_name']); 
        }
        
        unset($waitSlotsOrig[$socketID]);
        unset($waits[$socketID]);
        unset($socketData[$socketID]);
        unset($postData[$socketID]);
        unset($liveReadSockets[$socketID]);
        unset($requests[$socketID]);
        unset($writeBuffer[$socketID]);
        if(isset($gzipPath[$socketID])) {
            unlink($gzipPath[$socketID]);
            unset($gzipPath[$socketID]);
        }
        
        if(($key = array_search($requestSocket, $liveWriteSocketsOrig)) !== false)
            unset($liveWriteSocketsOrig[$key]);
        
        if(is_resource($requestFileHandle[$socketID])) {
            fclose($requestFileHandle[$socketID]);
            unset($requestFileHandle[$socketID]);
        }
        
        // Check if request-limit is reached 
        if(Config::get('main.requestworkerlimit') > 0 && $processedRequests >= Config::get('main.requestworkerlimit') && !$socketData && !$postData && !$requests) {
            IPC::send(9999, 1);
            exit;
        }
        
        clean:
        
        if($decliningNewRequests && Config::get('main.maxconcurrent') > count($listenSocketsOrig))
            $listenSocketsOrig = array_merge($Pancake_sockets, $listenSocketsOrig);
        
        if(Config::get('main.maxconcurrent') < count($listenSocketsOrig) - count($Pancake_sockets) && Config::get('main.maxconcurrent') != 0) {
            foreach($Pancake_sockets as $index => $socket)
                unset($listenSocketsOrig[$index]);
            $decliningNewRequests = true;
        }
        
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
        unset($index);
        
        // If jobs are waiting, execute them before select()ing again
        if($listenSockets || $liveWriteSockets || $waitSlots)
        	goto cycle;
        
        $listenSockets = $listenSocketsOrig;
        $liveWriteSockets = $liveWriteSocketsOrig;
        $waitSlots = $waitSlotsOrig;
        
        gc_collect_cycles();
        
        // Reset statcache
        clearstatcache();
    }
?>

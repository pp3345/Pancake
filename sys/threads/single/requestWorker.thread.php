<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.thread.php                                     */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    namespace Pancake;

    #.if 0
    if(PANCAKE !== true)
        exit;
   	#.endif
    
    #.if 0 && #.bool #.call 'Pancake\Config::get' 'main.secureports'
    	#.define 'SUPPORT_TLS' true
    #.endif
    
    #.if #.bool #.call 'Pancake\Config::get' 'fastcgi'
    	#.define 'SUPPORT_FASTCGI' true
    #.endif
    
    #.if #.bool #.Pancake\Config::get("ajp13")
    	#.SUPPORT_AJP13 = true
    #.endif
    
    #.if #.eval 'global $Pancake_vHosts; foreach($Pancake_vHosts as $vHost) if($vHost->phpWorkers) return true; return false;' false
    	#.define 'SUPPORT_PHP' true
    #.endif
    
    #.if #.eval 'global $Pancake_vHosts; foreach($Pancake_vHosts as $vHost) if($vHost->allowGZIP) return true; return false;' false
    	#.define 'SUPPORT_GZIP' true
    #.endif
    
    #.if #.eval 'global $Pancake_vHosts; foreach($Pancake_vHosts as $vHost) if($vHost->authFiles || $vHost->authDirectories) return true; return false;' false
    	#.define 'SUPPORT_AUTHENTICATION' true
    #.endif
    
    #.if #.eval 'global $Pancake_vHosts; foreach($Pancake_vHosts as $vHost) if($vHost->rewriteRules) return true; return false;' false
    	#.define 'SUPPORT_REWRITE' true
    #.endif
    
    #.longDefine 'EVAL_CODE'
    global $Pancake_vHosts;
    
    foreach($Pancake_vHosts as $vHost)
    	if($vHost->gzipStatic && !$vHost->AJP13)
    		return true;
    return false;
    #.endLongDefine
    
    #.if #.eval EVAL_CODE false
    	#.SUPPORT_GZIP_STATIC = true
    #.endif
    
    #.longDefine 'EVAL_CODE'
    global $Pancake_vHosts;
    
    foreach($Pancake_vHosts as $vHost)
    	if($vHost->allowDirectoryListings)
    		return true;
    return false;
    #.endLongDefine
    
    #.if #.eval EVAL_CODE false
    	#.SUPPORT_DIRECTORY_LISTINGS = true
    #.endif
    
    #.longDefine 'EVAL_CODE'
    global $Pancake_vHosts;
    
    foreach($Pancake_vHosts as $vHost)
    	if($vHost->gzipMimeTypes && $vHost->allowGZIP)
    		return true;
    return false;
    #.endLongDefine
    
    #.if #.eval EVAL_CODE false
    	#.SUPPORT_GZIP_MIME_TYPE_LIMIT = true
    #.endif
    
    #.if #.call 'Pancake\Config::get' 'main.waitslotwaitlimit'
    	#.ifdef 'SUPPORT_PHP'
    		#.define 'SUPPORT_WAITSLOTS' true
    	#.endif
    #.endif
    
    #.if #.config 'compressvariables'
    	#.config 'compressvariables' false
    #.endif
    
    #.if #.config 'compressproperties'
    	#.config 'compressproperties' false
    #.endif
    
    #.if 1 < #.call 'count' #.call 'Pancake\Config::get' 'vhosts'
    	#.define 'SUPPORT_MULTIPLE_VHOSTS' true
    #.endif
    
    #.if #.number #.call 'Pancake\Config::get' 'main.iocacheram'
    	#.define 'USE_IOCACHE' true
    #.endif
    
    #.macro 'REQUEST_TYPE' '$requestObject->requestType'
    #.macro 'GET_PARAMS' '$requestObject->getGETParams()'
    #.macro 'MIME_TYPE' '$requestObject->mimeType'
    #.macro 'VHOST' '$requestObject->vHost'
    #.macro 'REQUEST_FILE_PATH' '$requestObject->requestFilePath'
    #.macro 'RANGE_FROM' '$requestObject->rangeFrom'
    #.macro 'RANGE_TO' '$requestObject->rangeTo'
    #.macro 'BUILD_ANSWER_HEADERS' '$requestObject->buildAnswerHeaders()'
    #.macro 'ANSWER_BODY' '$requestObject->answerBody'
    #.macro 'REMOTE_IP' '$requestObject->remoteIP'
    #.macro 'REMOTE_PORT' '$requestObject->remotePort'
    #.macro 'REQUEST_LINE' '$requestObject->requestLine'
    #.macro 'ANSWER_CODE' '$requestObject->answerCode'
    #.macro 'UPLOADED_FILES' '$requestObject->uploadedFiles'
    #.macro 'SIMPLE_GET_REQUEST_HEADER' '(isset($requestObject->requestHeaders[$headerName]) ? $requestObject->requestHeaders[$headerName] : null)' '$headerName'
    #.macro 'CASE_INSENSITIVE_GET_REQUEST_HEADER' '$requestObject->getRequestHeader($headerName, false)' '$headerName'
    #.macro 'QUERY_STRING' '$requestObject->queryString'
    #.macro 'PROTOCOL_VERSION' '$requestObject->protocolVersion'
    #.macro 'REQUEST_URI' '$requestObject->requestURI'
    #.macro 'LOCAL_IP' '$requestObject->localIP'
    #.macro 'LOCAL_PORT' '$requestObject->localPort'
    #.macro 'RAW_POST_DATA' '$requestObject->rawPOSTData'
    #.macro 'ACCEPTS_COMPRESSION' 'isset($requestObject->acceptedCompressions[$compression])' '$compression'
    #.macro 'VHOST_COMPARE_OBJECTS' '/* .VHOST */->shouldCompareObjects'
    #.macro 'VHOST_FASTCGI' '(isset(/* .VHOST */->fastCGI[/* .MIME_TYPE */]) ? /* .VHOST */->fastCGI[/* .MIME_TYPE */] : null)'
    #.macro 'VHOST_AJP13' '/* .VHOST */->AJP13'
    #.macro 'VHOST_PHP_WORKERS' '/* .VHOST */->phpWorkers'
    #.macro 'VHOST_SOCKET_NAME' '/* .VHOST */->phpSocketName'
    #.macro 'VHOST_DOCUMENT_ROOT' '/* .VHOST */->documentRoot'
    #.macro 'VHOST_DIRECTORY_PAGE_HANDLER' '/* .VHOST */->directoryPageHandler'
    #.macro 'VHOST_ALLOW_GZIP_COMPRESSION' '/* .VHOST */->allowGZIP'
    #.macro 'VHOST_GZIP_MINIMUM' '/* .VHOST */->gzipMinimum'
    #.macro 'VHOST_GZIP_LEVEL' '/* .VHOST */->gzipLevel'
    #.macro 'VHOST_WRITE_LIMIT' '/* .VHOST */->writeLimit'
    #.macro 'VHOST_NAME' '/* .VHOST */->name'
    
    #.if Pancake\DEBUG_MODE === true
    	#.define 'BENCHMARK' false
    #.else
    	#.define 'BENCHMARK' false
    #.endif
    
    global $Pancake_sockets;
    global $Pancake_vHosts;
    
    // Precalculate post_max_size in bytes
    // It is impossible to keep this in a more readable way thanks to the nice Zend Tokenizer
   	#.define 'POST_MAX_SIZE' #.eval '$size = strtolower(ini_get("post_max_size")); if(strpos($size, "k")) $size = (int) $size * 1024; else if(strpos($size, "m")) $size = (int) $size * 1024 * 1024; else if(strpos($size, "g")) $size = (int) $size * 1024 * 1024 * 1024; return $size;' false

    #.include 'mime.class.php'
    
    #.ifdef 'SUPPORT_TLS'
    	#.include 'TLSConnection.class.php'
    #.endif
    
    #.ifdef 'SUPPORT_FASTCGI'
    	#.include 'FastCGI.class.php'
    #.endif
    
    #.ifdef 'SUPPORT_AJP13'
    	#.include 'AJP13.class.php'
    #.endif
    
    #.ifdef 'SUPPORT_AUTHENTICATION'
    	#.include 'authenticationFile.class.php'
    #.endif
    
    #.include 'workerFunctions.php'
    #.include 'HTTPRequest.class.php'
    #.include 'invalidHTTPRequest.exception.php'
    #.include 'vHostInterface.class.php'
    
    #.ifdef 'USE_IOCACHE'
    	#.include 'IOCache.class.php'
    #.endif
    
    /*var_dump($buffer = $ioCache->allocateBuffer(10, 'abcdefghijklmnopqrstuvwxyz1234567890'));
    var_dump($ioCache->getBytes($buffer, 36));
    var_dump($buffer2 = $ioCache->allocateBuffer(10, 'anti'));
    var_dump($ioCache->getBytes($buffer2));
    var_dump($buffer);
    var_dump($ioCache->getBytes($buffer, 36));
    var_dump($buffer3 = $ioCache->allocateBuffer(11, "aaaaa"));
    var_dump($ioCache->getBytes($buffer, 36), $ioCache->getBytes($buffer2, 4), $ioCache->getBytes($buffer3, 5));*/
    
    unset($loadCodeFile);

    MIME::load();
    
    foreach($Pancake_vHosts as $id => &$vHost) {
    	if($vHost instanceof vHostInterface)
    		break;
    	$vHost = new vHostInterface($vHost);
    	#.ifdef 'SUPPORT_FASTCGI'
    		$vHost->initializeFastCGI();
    	#.endif
    	#.ifdef 'SUPPORT_AJP13'
    		$vHost->initializeAJP13();
    	#.endif
    	unset($Pancake_vHosts[$id]);
    	foreach($vHost->listen as $address)
    		$Pancake_vHosts[$address] = $vHost;
    }
    
    unset($id, $vHost, $address);
    
    Config::workerDestroy();
    
    $listenSockets = $listenSocketsOrig = $Pancake_sockets;
    
    // Initialize some variables
    #.if #.call 'Pancake\Config::get' 'main.maxconcurrent'
    $decliningNewRequests = false;
    #.endif
    #.ifdef 'SUPPORT_TLS'
    $secureConnection = array();
    #.endif
    #.ifdef 'SUPPORT_FASTCGI'
    $fastCGISockets = array();
    #.endif
    #.ifdef 'SUPPORT_AJP13'
    $ajp13Sockets = array();
    #.endif
    $liveWriteSocketsOrig = array();
    $liveReadSockets = array();
    $socketData = array();
    $postData = array();
    #.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
    $processedRequests = 0;
    #.endif
    $requests = array();
    $requestFileHandle = array();
    #.ifdef 'SUPPORT_GZIP'
    $gzipPath = array();
    #.endif
    $writeBuffer = array();
    #.ifdef 'SUPPORT_PHP'
    $phpSockets = array();
    #.endif
    #.ifdef 'SUPPORT_WAITSLOTS'
    $waitSlots = array();
    $waits = array();
    #.endif
    
    #.if BENCHMARK === true
    	benchmarkFunction("socket_read");
    	benchmarkFunction("socket_write");
    	benchmarkFunction("hexdec");
    #.endif
    
    #.ifdef 'USE_IOCACHE'
    	global $ioCache;
    	$ioCache = new IOCache;
    #.endif
    
    // Ready
    $Pancake_currentThread->parentSignal(/* .constant 'SIGUSR1' */);
    
    // Set user and group
    setUser();
    
    // Wait for incoming requests     
    while(socket_select($listenSockets, $liveWriteSockets, $x
    #.ifdef 'SUPPORT_WAITSLOTS'
    , $waitSlots ? 0 : null, $waitSlots ? /* .call 'Pancake\Config::get' 'main.waitslottime' */ : null
    #.else
    , null
    #.endif
    ) !== false) {
    	// If there are jobs left in the queue at the end of the job-run, we're going to jump back to this point to execute the jobs that are left
    	cycle:
    	
    	#.ifdef 'SUPPORT_WAITSLOTS'
    	// Check if there are requests waiting for a PHPWorker
    	foreach((array) $waitSlots as $socketID => $requestSocket) {
			unset($waitSlots[$socketID]);
			$requestObject = $requests[$socketID];
    		goto load;
    	}
    	#.endif
    	
    	// Upload to clients that are ready to receive
        foreach((array) $liveWriteSockets as $index => $requestSocket) {
        	unset($liveWriteSockets[$index]);
        	
        	$socketID = (int) $requestSocket;
        	$requestObject = $requests[$socketID];
            goto liveWrite;
        }
        
        // New connection, downloadable content from a client or the PHP-SAPI finished a request
        foreach($listenSockets as $index => $socket) {
        	unset($listenSockets[$index]);
        	
        	#.ifdef 'SUPPORT_AJP13'
        	if(isset($ajp13Sockets[(int) $socket])) {
        		$ajp13 = $ajp13Sockets[(int) $socket];
        		
        		do {
        			$newData = socket_read($socket, (isset($result) ? ($result & /* .AJP13_APPEND_DATA */ ? $result ^ /* .AJP13_APPEND_DATA */ : $result) : 5));
        			if(isset($result) && $result & /* .AJP13_APPEND_DATA */)
        				$data .= $newData;
        			else
        				$data = $newData;
        			$result = $ajp13->upstreamRecord($data, $socket);
        			if($result === 0) {
        				unset($ajp13Sockets[(int) $socket]);
        				unset($listenSocketsOrig[array_search($socket, $listenSocketsOrig)]);
        				unset($result);
        				goto clean;
        			}
        		} while($result & /* .AJP13_APPEND_DATA */);
        		
        		if(is_array($result)) {
        			list($requestSocket, $requestObject) = $result;
        			$socketID = (int) $requestSocket;
        			
        			unset($ajp13Sockets[(int) $socket]);
        			unset($listenSocketsOrig[array_search($socket, $listenSocketsOrig)]);
        			unset($result);
        			unset($newData);
        			unset($data);
        			goto write;
        		}
        		
        		unset($result);
        		unset($newData);
        		unset($data);
        		goto clean;
        	}
        	#.endif
        	
        	#.ifdef 'SUPPORT_FASTCGI'
        	if(isset($fastCGISockets[(int) $socket])) {
        		$fastCGI = $fastCGISockets[(int) $socket];
        		do {
        			$newData = socket_read($socket, (isset($result) ? ($result & /* .constant 'FCGI_APPEND_DATA' */ ? $result ^ /* .constant 'FCGI_APPEND_DATA' */ : $result) : 8));
        			if(isset($result) && $result & /* .constant 'FCGI_APPEND_DATA' */)
        				$data .= $newData;
        			else
        				$data = $newData;
        			$result = $fastCGI->upstreamRecord($data);
        			if($result === 0) {
        				unset($fastCGISockets[(int) $socket]);
        				unset($listenSocketsOrig[array_search($socket, $listenSocketsOrig)]);
        				unset($result);
        				goto clean;
        			}
        		} while($result & /* .constant 'FCGI_APPEND_DATA' */);
        		 
        		if(is_array($result)) {
        			list($requestSocket, $requestObject) = $result;
        			$socketID = (int) $requestSocket;
        	
        			unset($result);
        			unset($data);
        			unset($newData);
        			goto write;
        		}
        		 
        		unset($result);
        		unset($data);
        		unset($newData);
        		goto clean;
        	}
        	#.endif
        	
            if(isset($liveReadSockets[(int) $socket]) || socket_get_option($socket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_KEEPALIVE' */)) {
                $socketID = (int) $socket;
                $requestSocket = $socket;
                $requestObject = $requests[$socketID];
                if(!isset($socketData[$socketID])) {
                	#.ifdef 'USE_IOCACHE'
                		$socketData[$socketID] = $ioCache->allocateBuffer(/* .constant 'IOCACHE_SOCKET_BUFFER_PRIORITY' */);
                	#.else
                		$socketData[$socketID] = "";
                	#.endif
                }
                break;
            }
            
            #.ifdef 'SUPPORT_PHP'
            if(isset($phpSockets[(int) $socket])) {
                $requestSocket = $phpSockets[(int) $socket];
                $socketID = (int) $requestSocket;
                $requestObject = $requests[$socketID];
                
                socket_set_block($socket);
                
                $packages = hexdec(socket_read($socket, 8));
                $length = hexdec(socket_read($socket, 8));
                
                if($packages > 1) {
                	$sockData = "";

                	while($packages--)
                		$sockData .= socket_read($socket, $length);
                	
                	$obj = unserialize($sockData);
                	
                	unset($sockData);
                }
                else
                	$obj = unserialize(socket_read($socket, $length));
                
                if($obj instanceof HTTPRequest && !(/* .VHOST_COMPARE_OBJECTS */ && (string) $requestObject != (string) $obj)) {
                	$obj->vHost = $requests[$socketID]->vHost;
                	$requestObject = $requests[$socketID] = $obj;
                } else
                	$requestObject->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
                
                unset($listenSocketsOrig[array_search($socket, $listenSocketsOrig)]);
                unset($phpSockets[(int) $socket]);
                
                socket_close($socket);
                unset($socket);
                unset($obj);
                
                goto write;
            }
            #.endif

            if(
            #.if 0 != #.call 'Pancake\Config::get' 'main.maxconcurrent'
            /* .call 'Pancake\Config::get' 'main.maxconcurrent' */ < count($listenSocketsOrig) - count($Pancake_sockets) || 
            #.endif
            !($requestSocket = @socket_accept($socket)))
                goto clean;
            $socketID = (int) $requestSocket;

            #.ifdef 'USE_IOCACHE'
            	$socketData[$socketID] = $ioCache->allocateBuffer(/* .constant 'IOCACHE_SOCKET_BUFFER_PRIORITY' */);
            #.else
            	$socketData[$socketID] = "";
            #.endif
            
            #.ifdef 'SUPPORT_TLS'
            	socket_getsockname($requestSocket, $ip, $port);
            	if(in_array($port, Config::get("main.secureports")))
            		$secureConnection[$socketID] = new TLSConnection;
            #.endif
            
            // Set O_NONBLOCK-flag
            socket_set_nonblock($requestSocket);
            break;
        }
        
        // Receive data from client
        if(isset($requests[$socketID]))
            $bytes = @socket_read($requestSocket, /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */ - strlen($postData[$socketID]));
        else
            $bytes = @socket_read($requestSocket, 10240);
        
        // socket_read() might return string(0) "" while the socket keeps at non-blocking state - This happens when the client closes the connection under certain conditions
        // We should not close the socket if socket_read() returns bool(false) - This might lead to problems with slow connections
        if($bytes === "")
            goto close;
        
        #.ifdef 'SUPPORT_TLS'
        	if($secureConnection[$socketID] && strlen($bytes) >= 5) {
        		socket_set_block($requestSocket);
        		socket_write($requestSocket, $secureConnection[$socketID]->data($bytes));
        		goto close;
        	}
        #.endif
        
        // Check if request was already initialized and we are only reading POST-data
        if(isset($requests[$socketID])) {
            $postData[$socketID] .= $bytes;
            if(strlen($postData[$socketID]) >= /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */)
                goto readData;
        } else if($bytes) {
        	#.ifdef 'USE_IOCACHE'
        		$ioCache->addBytes($socketData[$socketID], $bytes);
        	#.else
            	$socketData[$socketID] .= $bytes;
            #.endif

            // Check if all headers were received
            #.ifdef 'USE_IOCACHE'
            if(strpos($ioCache->getBytes($socketData[$socketID], -1), "\r\n\r\n")) {
            #.else
            if(strpos($socketData[$socketID], "\r\n\r\n")) {
			#.endif
            	// Check for POST
                #.ifdef 'USE_IOCACHE'
                	if($ioCache->getBytes($socketData[$socketID], 4) === "POST") {
                		$data = explode("\r\n\r\n", $ioCache->getBytes($socketData[$socketID], -1), 2);
				#.else
               		if(strpos($socketData[$socketID], "POST") === 0) {
               			$data = explode("\r\n\r\n", $socketData[$socketID], 2);
               	#.endif
                    #.ifdef 'USE_IOCACHE'
                    	$ioCache->setBytes($socketData[$socketID], $data[0]);
                    	$postData[$socketID] = $ioCache->allocateBuffer(/* .constant 'IOCACHE_POST_BUFFER_PRIORITY' */, $data[1]);
                    #.else
                    	$socketData[$socketID] = $data[0];
	                    $postData[$socketID] = $data[1];
	                #.endif
                }

                goto readData;
            }
            
            // Avoid memory exhaustion by just sending random but long data that does not contain \r\n\r\n
            // I assume that no normal HTTP-header will be longer than 10 KiB
            #.ifdef 'USE_IOCACHE'
            if(/* .IOCACHE_BUFFER_TOTAL_BYTES '$socketData[$socketID]' */ >= 10240)
            #.else
            if(strlen($socketData[$socketID]) >= 10240)
            #.endif
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
                $requestObject = $requests[$socketID] = new HTTPRequest($ip, $port, $lip, $lport);
                #.ifdef 'USE_IOCACHE'
                	$requestObject->init($ioCache->getBytes($socketData[$socketID], -1));
                	$ioCache->deallocateBuffer($socketData[$socketID]);
                #.else
                	$requestObject->init($socketData[$socketID]);
                #.endif
                unset($socketData[$socketID]);
            } catch(invalidHTTPRequestException $e) {
                $requestObject->invalidRequest($e);
                #.ifdef 'USE_IOCACHE'
                	$ioCache->deallocateBuffer($socketData[$socketID]);
                #.endif
                unset($socketData[$socketID], $e);
                goto write;
            }
        }
        
        // Check for POST and get all POST-data
        if(/* .REQUEST_TYPE */ == 'POST') {
			#.ifdef 'USE_IOCACHE'
			if(/* .IOCACHE_BUFFER_TOTAL_BYTES '$postData[$socketID]' */ >= /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */) {
				if(/* .IOCACHE_BUFFER_TOTAL_BYTES '$postData[$socketID]' */ > /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */)
					$ioCache->setBytes($postData[$socketID], substr($ioCache->getBytes($postData[$socketID], -1), 0, /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */));
			#.else
            if(strlen($postData[$socketID]) >= /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */) {
                if(strlen($postData[$socketID]) > /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */)
                    $postData[$socketID] = substr($postData[$socketID], 0, /* .CASE_INSENSITIVE_GET_REQUEST_HEADER '"Content-Length"' */);
            #.endif
                if($key = array_search($requestSocket, $listenSocketsOrig))
                    unset($listenSocketsOrig[$key]);
                /* .RAW_POST_DATA */ = $postData[$socketID];
                unset($postData[$socketID]);
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
        
        #.if #.call 'Pancake\Config::get' 'main.allowtrace'
	        if(/* .REQUEST_TYPE */ == 'TRACE')
	            goto write;
	    #.endif
        
	    #.if #.call 'Pancake\Config::get' 'main.allowoptions'
	        // Check for "OPTIONS"-requestmethod
	        if(/* .REQUEST_TYPE */ == 'OPTIONS')
	            $requestObject->setHeader('Allow', 
	            /* .eval '$allow = "GET, POST, OPTIONS";
	            if(Pancake\Config::get("main.allowhead") === true)
	                $allow .= ", HEAD";
	            if(Pancake\Config::get("main.allowtrace") === true)
	                $allow .= ", TRACE";
	            return $allow;' false
	             */);
	    #.endif
        
        // Output debug information
        #.if Pancake\DEBUG_MODE === true
        if(array_key_exists('pancakedebug', /* .GET_PARAMS */)) {
            $requestObject->setHeader('Content-Type', 'text/plain');
                                                    
            $body = 'Received Headers:' . "\r\n";
            $body .= /* .REQUEST_LINE */ . "\r\n";
            $body .= $requestObject->getRequestHeaders() . "\r\n";
            $body .= 'Received POST content:' . "\r\n";
            $body .= $postData[$socketID] . "\r\n\r\n";
            $body .= 'Dump of RequestObject:' . "\r\n";
            $body .= print_r($requestObject, true);
            /* .ANSWER_BODY */ = $body;
            
            unset($body);
            
            goto write;
        }
        #.endif
        
        #.if #.call 'ini_get' 'expose_php'
        if(array_key_exists("", /* .GET_PARAMS */)) {
            $_GET = /* .GET_PARAMS */;
            switch($_GET[""]) {
                case 'PHPE9568F34-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/php.gif');
                    $requestObject->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPE9568F35-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/zend.gif');
                    $requestObject->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPE9568F36-D428-11d2-A769-00AA001ACF42':
                    $logo = file_get_contents('logo/php_egg.gif');
                    $requestObject->setHeader('Content-Type', 'image/gif');
                break;
                case 'PHPB8B5F2A0-3C92-11d3-A3A9-4C7B08C10000':
                    ob_start();
                    phpcredits();
                    $requests[$socketID]->setHeader('Content-Type', 'text/html');
                    $logo = ob_get_contents();
                    PHPFunctions\OutputBuffering\endClean();
                break;
                #.if true === #.call 'Pancake\Config::get' 'main.exposepancake'
                case 'PAN8DF095AE-6639-4C6F-8831-5AB8FBD64D8B':
                    $logo = file_get_contents('logo/pancake.png');
                    $requestObject->setHeader('Content-Type', 'image/png');
                    break;
          		#.endif
                default:
                    goto load;
            }
            /* .ANSWER_BODY */ = $logo;
            unset($logo);
            unset($_GET);
            goto write;
        }
        #.endif
        
        load:
        
        #.ifdef 'SUPPORT_AJP13'
        if($ajp13 = /* .VHOST_AJP13 */) {
        	$socket = $ajp13->makeRequest($requestObject, $requestSocket);
        	if($socket === false)
        		goto write;
        	$listenSocketsOrig[] = $socket;
        	$ajp13Sockets[(int) $socket] = $ajp13;
        	goto clean;
        }
        #.endif
        
        #.ifdef 'SUPPORT_FASTCGI'
        	// FastCGI
        	if($fastCGI = /* .VHOST_FASTCGI */) {
        		if($fastCGI->makeRequest($requestObject, $requestSocket) === false)
        			goto write;
        		if(!in_array($fastCGI->socket, $listenSocketsOrig)) {
        			$listenSocketsOrig[] = $fastCGI->socket;
        			$fastCGISockets[(int) $fastCGI->socket] = $fastCGI;
        		}
        		goto clean;
        	}
        #.endif
        
       	#.ifdef 'SUPPORT_PHP'
        // Check for PHP
        if(/* .MIME_TYPE */ == 'text/x-php' && /* .VHOST_PHP_WORKERS */) {
            if(!($socket = socket_create(/* .constant 'AF_UNIX' */, /* .constant 'SOCK_SEQPACKET' */, 0)) {
            	$requestObject->invalidRequest(new invalidHTTPRequestException('Failed to create communication socket. Probably the server is overladed. Try again later.', 500));
            	goto write;
            }
            
            socket_set_nonblock($socket);
            // @ - Do not spam errorlog with Resource temporarily unavailable if there is no PHPWorker available
            @socket_connect($socket, /* .VHOST_SOCKET_NAME */);
            
            if(socket_last_error($socket) == 11) {
            	#.ifdef 'SUPPORT_WAITSLOTS'
	      			$waits[$socketID]++;
	            	
	            	if($waits[$socketID] > /* .call 'Pancake\Config::get' 'main.waitslotwaitlimit' */) {
	            		$requestObject->invalidRequest(new invalidHTTPRequestException('There was no worker available to serve your request. Please try again later.', 500));
	            		goto write;
	            	}	
	            	
	            	$waitSlotsOrig[$socketID] = $requestSocket;
	            	
	            	goto clean;
	        	#.else
	            	$requestObject->invalidRequest(new invalidHTTPRequestException('There was no worker available to serve your request. Please try again later.', 500));
	            	goto write;
	           	#.endif
            }
            
            #.ifdef 'SUPPORT_WAITSLOTS'
            unset($waitSlotsOrig[$socketID]);
            unset($waits[$socketID]);
            #.endif
            
            #.ifdef 'USE_IOCACHE'
            	// Load cache data into object
            	if(/* .RAW_POST_DATA */) {
            		$buffer = /* .RAW_POST_DATA */;
            		/* .RAW_POST_DATA */ = $ioCache->getBytes(/* .RAW_POST_DATA */, -1);
            		$ioCache->deallocateBuffer($buffer);
            		unset($buffer);
            	}
            #.endif
            
            $data = serialize($requestObject);
            
            $packages = array();
            
            if(strlen($data) > (socket_get_option($socket, /* .constant "SOL_SOCKET" */, /* .constant "SO_SNDBUF" */) - 1024)
            && (socket_set_option($socket, /* .constant "SOL_SOCKET" */, /* .constant "SO_SNDBUF" */, strlen($data) + 1024) + 1)
            && strlen($data) > (socket_get_option($socket, /* .constant "SOL_SOCKET" */, /* .constant "SO_SNDBUF" */) - 1024)) {
            	$packageSize = socket_get_option($socket, /* .constant "SOL_SOCKET" */, /* .constant "SO_SNDBUF" */) - 1024;
            
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
        #.endif
        
        // Get time of last modification
        $modified = filemtime(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */);
        // Set Last-Modified-Header as RFC 2822
        $requestObject->setHeader('Last-Modified', date('r', $modified));
        
        // Check for If-Modified-Since
        if(strtotime(/* .SIMPLE_GET_REQUEST_HEADER '"If-Modified-Since"' */) == $modified) {
        	/* .ANSWER_CODE */ = 304;
            goto write;
        }
        
        #.ifdef 'SUPPORT_DIRECTORY_LISTINGS'
        // Check for directory
        if(is_dir(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */)) {
            $files = array();
            
            foreach(scandir(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */) as $file) {
            	if($file == '.')
            		continue;
            	$isDir = is_dir(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH*/ . $file);
            	$files[] =
            	array('name' => $file,
            			'address' => 'http://' . /* .SIMPLE_GET_REQUEST_HEADER '"Host"' */ . /* .REQUEST_FILE_PATH*/ . $file . ($isDir ? '/' : ''),
            			'directory' => $isDir,
            			'type' => MIME::typeOf($file),
            			'modified' => filemtime(/* .VHOST_DOCUMENT_ROOT */ .  /* .REQUEST_FILE_PATH*/ . $file),
            			'size' => filesize(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH*/ . $file));
            }
            
            $requestObject->setHeader('Content-Type', 'text/html; charset=utf-8');
            
            ob_start();
            
            if(!include(/* .VHOST_DIRECTORY_PAGE_HANDLER */))
            	include 'php/directoryPageHandler.php';
             
            /* .ANSWER_BODY */ = ob_get_clean();
        } else {
		#.endif
			$requestObject->setHeader('Content-Type', /* .MIME_TYPE */); 
			$requestObject->setHeader('Accept-Ranges', 'bytes');
            
            #.ifdef 'SUPPORT_GZIP'
            // Check if GZIP-compression should be used  
            if(/* .ACCEPTS_COMPRESSION "'gzip'" */ && /* .VHOST_ALLOW_GZIP_COMPRESSION */ === true && filesize(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */) >= /* .VHOST_GZIP_MINIMUM */
            #.ifdef 'SUPPORT_GZIP_MIME_TYPE_LIMIT'
            && /* .VHOST */->gzipMimeTypes && in_array(/* .MIME_TYPE */, /* .VHOST */->gzipMimeTypes)
            #.endif
            ) {
                // Set encoding-header
                $requestObject->setHeader('Content-Encoding', 'gzip');
                // Create temporary file
                $gzipPath[$socketID] = tempnam(/* .call 'Pancake\Config::get' 'main.tmppath' */, 'GZIP');
                $gzipFileHandle = gzopen($gzipPath[$socketID], 'w' . /* .VHOST_GZIP_LEVEL */);
                // Load uncompressed requested file
                $requestedFileHandle = fopen(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */, 'r');
                // Compress file
                while(!feof($requestedFileHandle))
                    gzwrite($gzipFileHandle, fread($requestedFileHandle, /* .VHOST_WRITE_LIMIT */));
                // Close GZIP-resource and open normal file-resource
                gzclose($gzipFileHandle);
                $requestFileHandle[$socketID] = fopen($gzipPath[$socketID], 'r');
                // Set Content-Length
                $requestObject->setHeader('Content-Length', filesize($gzipPath[$socketID]) - /* .RANGE_FROM */);
            } else {
            #.endif
                $requestObject->setHeader('Content-Length', filesize(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */) - /* .RANGE_FROM */);
                $requestFileHandle[$socketID] = fopen(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */, 'r');
            #.ifdef 'SUPPORT_GZIP'
            }
            #.endif
            
            // Check if a specific range was requested
            if(/* .RANGE_FROM */) {
                /* .ANSWER_CODE */ = 206;
                fseek($requestFileHandle[$socketID], /* .RANGE_FROM */);
                #.ifdef 'SUPPORT_GZIP'
                if($gzipPath[$socketID])
                    $requestObject->setHeader('Content-Range', 'bytes ' . /* .RANGE_FROM */.'-'.(filesize($gzipPath[$socketID]) - 1).'/'.filesize($gzipPath[$socketID]));
                else
                #.endif
                    $requestObject->setHeader('Content-Range', 'bytes ' . /* .RANGE_FROM */.'-'.(filesize(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */) - 1).'/'.filesize(/* .VHOST_DOCUMENT_ROOT */ . /* .REQUEST_FILE_PATH */));
            }
        #.ifdef 'SUPPORT_DIRECTORY_LISTINGS'
        }
        #.endif
        
        write:

        // Get Answer Headers
        $writeBuffer[$socketID] = /* .BUILD_ANSWER_HEADERS */;

        #.if #.call 'Pancake\Config::get' 'main.allowhead'
	        // Get answer body if set and request method isn't HEAD
	        if(/* .REQUEST_TYPE */ != 'HEAD')
	    #.endif
	    $writeBuffer[$socketID] .= /* .ANSWER_BODY */;

        // Output request information
        out('REQ './* .ANSWER_CODE */.' './* .REMOTE_IP */.': './* .REQUEST_LINE */.' on vHost '.((/* .VHOST */) ? /* .VHOST_NAME */ : null).' (via './* .SIMPLE_GET_REQUEST_HEADER '"Host"' */.' from './* .SIMPLE_GET_REQUEST_HEADER "'Referer'" */.') - './* .SIMPLE_GET_REQUEST_HEADER '"User-Agent"' */, /* .constant 'Pancake\REQUEST' */);

        // Check if user wants keep-alive connection
        if($requestObject->getAnswerHeader('Connection') == 'keep-alive')
            socket_set_option($requestSocket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_KEEPALIVE' */, 1);

        #.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
	        // Increment amount of processed requests
	        $processedRequests++;
        #.endif
        
        // Clean some data now to improve RAM usage
        unset($socketData[$socketID]);
        unset($postData[$socketID]);
        unset($liveReadSockets[$socketID]);

        liveWrite:
        
        // The buffer should usually only be empty if the hard limit was reached - In this case Pancake won't allocate any buffers except when the client really IS ready to receive data
        if(!strlen($writeBuffer[$socketID]))
        	$writeBuffer[$socketID] = fread($requestFileHandle[$socketID], /* .call 'Pancake\Config::get' 'main.writebuffermin' */);
        
        // Write data to socket
        if(($writtenLength = @socket_write($requestSocket, $writeBuffer[$socketID])) === false)
            goto close;
        // Remove written data from buffer
        $writeBuffer[$socketID] = substr($writeBuffer[$socketID], $writtenLength);
        
        // Add data to buffer if not all data was sent yet
        if(strlen($writeBuffer[$socketID]) < #.call 'Pancake\Config::get' 'main.writebuffermin'
        && is_resource($requestFileHandle[$socketID]) 
        && !feof($requestFileHandle[$socketID]) 
        #.if #.call 'Pancake\Config::get' 'main.writebufferhardmaxconcurrent'
        && count($writeBuffer) < #.call 'Pancake\Config::get' 'main.writebufferhardmaxconcurrent'
        #.endif
        #.if #.call 'Pancake\Config::get' 'main.allowhead'
        && /* .REQUEST_TYPE */ != 'HEAD' 
       	#.endif
        && $writtenLength)
        	$writeBuffer[$socketID] .= fread($requestFileHandle[$socketID], 
        			#.if #.call 'Pancake\Config::get' 'main.writebuffersoftmaxconcurrent'
        			(count($writeBuffer) > /* .call 'Pancake\Config::get' 'main.writebuffersoftmaxconcurrent' */ ? /* .call 'Pancake\Config::get' 'main.writebuffermin' */ : /* .VHOST_WRITE_LIMIT */)
					#.else
        			#.VHOST_WRITE_LIMIT
        			#.endif
        			- strlen($writeBuffer[$socketID]));

        // Check if more data is available
        if(strlen($writeBuffer[$socketID]) || (is_resource($requestFileHandle[$socketID]) && !feof($requestFileHandle[$socketID])
		#.if #.call 'Pancake\Config::get' 'main.allowhead'
        && /* .REQUEST_TYPE */ != 'HEAD'
        #.endif
        )) {
            // Event-based writing - In the time the client is still downloading we can process other requests
            if(!@in_array($requestSocket, $liveWriteSocketsOrig))
                $liveWriteSocketsOrig[] = $requestSocket;
            goto clean;
        }

        close:

        // Close socket
        if(!isset($requests[$socketID]) || $requestObject->getAnswerHeader('Connection') != 'keep-alive') {
            @socket_shutdown($requestSocket);
            socket_close($requestSocket);

            if($key = array_search($requestSocket, $listenSocketsOrig))
                unset($listenSocketsOrig[$key]);
        }
        
        if(isset($requests[$socketID])) {
            if(!in_array($requestSocket, $listenSocketsOrig, true) && $requestObject->getAnswerHeader('Connection') == 'keep-alive')
                $listenSocketsOrig[] = $requestSocket;
               
            foreach((array) /* .UPLOADED_FILES */ as $file)
                @unlink($file['tmp_name']); 
            
            #.ifdef 'USE_IOCACHE'
            	if(/* .RAW_POST_DATA */ instanceof \stdClass)
            		$ioCache->deallocateBuffer(/* .RAW_POST_DATA */);
            #.endif
        }
        
        #.ifdef 'SUPPORT_WAITSLOTS'
        unset($waitSlotsOrig[$socketID]);
        unset($waits[$socketID]);
        #.endif
   		
        #.ifdef 'USE_IOCACHE'
        	if($socketData[$socketID]) {
				#.ifdef 'IOCACHE_DEBUG'
        			out('Late deallocate');
        		#.endif
        		$ioCache->deallocateBuffer($socketData[$socketID]);
        	}
        #.endif
        
        unset($socketData[$socketID]);
        unset($postData[$socketID]);
        unset($liveReadSockets[$socketID]);
        unset($requests[$socketID]);
        unset($writeBuffer[$socketID]);
        #.ifdef 'SUPPORT_GZIP'
        if(isset($gzipPath[$socketID])) {
            unlink($gzipPath[$socketID]);
            unset($gzipPath[$socketID]);
        }
        #.endif
        
        if(($key = array_search($requestSocket, $liveWriteSocketsOrig)) !== false)
            unset($liveWriteSocketsOrig[$key]);
        
        if(is_resource($requestFileHandle[$socketID])) {
            fclose($requestFileHandle[$socketID]);
            unset($requestFileHandle[$socketID]);
        }
        
        #.if Pancake\DEBUG_MODE === true
        if($results = benchmarkFunction(null, true)) {
        	foreach($results as $function => $functionResults) {
        		foreach($functionResults as $result)
        			$total += $result;
        
        		out('Benchmark of function ' . $function . '(): ' . count($functionResults) . ' calls' . ( $functionResults ? ' - ' . (min($functionResults) * 1000) . ' ms min - ' . ($total / count($functionResults) * 1000) . ' ms ave - ' . (max($functionResults) * 1000) . ' ms max - ' . ($total * 1000) . ' ms total' : "") , /* .constant 'Pancake\REQUEST' */);
        		unset($total);
        	}
        	 
        	unset($result);
        	unset($functionResults);
        	unset($results);
        }
        #.endif
        
        // Check if request-limit is reached
        #.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
        if($processedRequests >= /* .call 'Pancake\Config::get' 'main.requestworkerlimit' */ && !$socketData && !$postData && !$requests) {
            IPC::send(9999, 1);
            exit;
        }
        #.endif
        
        clean:
        
        #.if #.call 'Pancake\Config::get' 'main.maxconcurrent'
        if($decliningNewRequests && /* .call 'Pancake\Config::get' 'main.maxconcurrent' */ > count($listenSocketsOrig))
            $listenSocketsOrig = array_merge($Pancake_sockets, $listenSocketsOrig);
        
        if(/* .call 'Pancake\Config::get' 'main.maxconcurrent' */ < count($listenSocketsOrig) - count($Pancake_sockets)) {
            foreach($Pancake_sockets as $index => $socket)
                unset($listenSocketsOrig[$index]);
            $decliningNewRequests = true;
        }
        #.endif
        
        // Clean old request-data
        unset($data);
        unset($bytes);
        unset($requestSocket);
        unset($requestObject);
        unset($socket);
        
        // If jobs are waiting, execute them before select()ing again
        if($listenSockets || $liveWriteSockets
		#.ifdef 'SUPPORT_WAITSLOTS'
        || $waitSlots
        #.endif
        )
        	goto cycle;
        
        $listenSockets = $listenSocketsOrig;
        $liveWriteSockets = $liveWriteSocketsOrig;
        #.ifdef 'SUPPORT_WAITSLOTS'
        $waitSlots = $waitSlotsOrig;
        #.endif
        
        gc_collect_cycles();
        
        // Reset statcache
        clearstatcache();
    }
?>

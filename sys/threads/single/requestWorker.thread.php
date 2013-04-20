<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* requestWorker.thread.php                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    namespace Pancake;

    #.if 0
    if(PANCAKE !== true)
        exit;
   	#.endif
   	   	
   	#.if #.bool #.Pancake\Config::get 'tls'
    	#.SUPPORT_TLS = true
    	#.TLS_ACCEPT = 1
    	#.TLS_READ = 2
    	#.TLS_WRITE = 3
    #.endif

    #.if #.bool #.call 'Pancake\Config::get' 'fastcgi'
        #.macro 'VHOST_FASTCGI' '(isset(/* .VHOST */->fastCGI[$requestObject->mimeType]) ? /* .VHOST */->fastCGI[$requestObject->mimeType] : null)'
    	#.define 'SUPPORT_FASTCGI' true
    #.endif

    #.if #.bool #.Pancake\Config::get("ajp13")
        #.macro 'VHOST_AJP13' '/* .VHOST */->AJP13'
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
    
    #.longDefine 'EVAL_CODE'
    global $Pancake_sockets;
    return count($Pancake_sockets);
    #.endLongDefine
    
    #.LISTEN_SOCKET_COUNT = #.eval EVAL_CODE false

    #.if #.call 'Pancake\Config::get' 'main.waitslotwaitlimit'
    	#.ifdef 'SUPPORT_PHP'
    		#.define 'SUPPORT_WAITSLOTS' true
    	#.endif
    #.endif

    #.config 'compressvariables' false
    #.config 'compressproperties' false
    #.config 'autosubstitutesymbols' false

    #.if 1 < #.call 'count' #.call 'Pancake\Config::get' 'vhosts'
    	#.define 'SUPPORT_MULTIPLE_VHOSTS' true
    #.endif

    #.longDefine 'EVAL_CODE'
    global $Pancake_vHosts;
    
    $writeLimit = \Pancake\Config::get('main.writelimit');
    foreach($Pancake_vHosts as $vHost) {
        if($vHost->writeLimit > $writeLimit) {
            $writeLimit = $vHost->writeLimit;   
        }
    }
    
    return $writeLimit;
    #.endLongDefine
    
    #.WRITE_LIMIT = #.eval EVAL_CODE false
    
    #.macro 'VHOST' '$requestObject->vHost'
    #.macro 'VHOST_PHP_WORKERS' '/* .VHOST */->phpWorkers'
    #.macro 'VHOST_SOCKET_NAME' '/* .VHOST */->phpSocketName'
    #.macro 'VHOST_DOCUMENT_ROOT' '/* .VHOST */->documentRoot'
    #.macro 'VHOST_DIRECTORY_PAGE_HANDLER' '/* .VHOST */->directoryPageHandler'
    #.macro 'VHOST_ALLOW_GZIP_COMPRESSION' '/* .VHOST */->allowGZIP'
    #.macro 'VHOST_GZIP_MINIMUM' '/* .VHOST */->gzipMinimum'
    #.macro 'VHOST_GZIP_LEVEL' '/* .VHOST */->gzipLevel'
    #.macro 'VHOST_NAME' '/* .VHOST */->name'
        
    #.if Pancake\DEBUG_MODE === true
    	#.define 'BENCHMARK' false
    #.else
    	#.define 'BENCHMARK' false
    #.endif

    global $Pancake_sockets;
    global $Pancake_vHosts;

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
    #.include 'vHostInterface.class.php'

    makeFastClass('Pancake\vHostInterface');

    unset($loadCodeFile);

	Thread::clearCache();
    MIME::load();
    
    // Convert vHost instances to vHostInterface instances
    foreach($Pancake_vHosts as $id => &$vHost) {
    	if($vHost instanceof vHostInterface)
    		break;
        if($vHost->phpSocket)
           Close($vHost->phpSocket);
        
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

    // This will be an unconnected socket after startup
    Close($this->localSocket);

    #.ifdef 'SUPPORT_AUTHENTICATION'
        setThread($Pancake_currentThread, vHostInterface::$defaultvHost, $Pancake_vHosts, /* .constant 'SUPPORT_AUTHENTICATION' */);
    #.else
        setThread($Pancake_currentThread, vHostInterface::$defaultvHost, $Pancake_vHosts, 0);
    #.endif
    
    #.ifdef 'SUPPORT_TLS'
    LoadModule('tls', true);
    #.certificateChain = #.Pancake\Config::get 'tls.certificatechain'
    #.privateKey = #.Pancake\Config::get 'tls.privatekey'
    #.cipherList = #.Pancake\Config::get 'tls.cipherlist'
    #.longDefine 'EVAL_CODE'
        /* From ssl.h */
        define('SSL_OP_MICROSOFT_SESS_ID_BUG',            0x00000001);
        define('SSL_OP_NETSCAPE_CHALLENGE_BUG',           0x00000002);
        define('SSL_OP_LEGACY_SERVER_CONNECT',            0x00000004);
        define('SSL_OP_NETSCAPE_REUSE_CIPHER_CHANGE_BUG',     0x00000008);
        define('SSL_OP_SSLREF2_REUSE_CERT_TYPE_BUG', 0x00000010);
        define('SSL_OP_MICROSOFT_BIG_SSLV3_BUFFER', 0x00000020);
        define('SSL_OP_MSIE_SSLV2_RSA_PADDING', 0x00000040);
        define('SSL_OP_SSLEAY_080_CLIENT_DH_BUG', 0x00000080);
        define('SSL_OP_TLS_D5_BUG', 0x00000100);
        define('SSL_OP_TLS_BLOCK_PADDING_BUG', 0x00000200);
        define('SSL_OP_DONT_INSERT_EMPTY_FRAGMENTS', 0x00000800);
        define('SSL_OP_ALL', 0x80000BFF);
        define('SSL_OP_NO_QUERY_MTU', 0x00001000);
        define('SSL_OP_COOKIE_EXCHANGE', 0x00002000);
        define('SSL_OP_NO_TICKET', 0x00004000);
        define('SSL_OP_CISCO_ANYCONNECT', 0x00008000);
        define('SSL_OP_NO_SESSION_RESUMPTION_ON_RENEGOTIATION', 0x00010000);
        define('SSL_OP_NO_COMPRESSION', 0x00020000);
        define('SSL_OP_ALLOW_UNSAFE_LEGACY_RENEGOTIATION', 0x00040000);
        define('SSL_OP_SINGLE_ECDH_USE', 0x00080000);
        define('SSL_OP_SINGLE_DH_USE', 0x00100000);
        define('SSL_OP_EPHEMERAL_RSA', 0x00200000);
        define('SSL_OP_CIPHER_SERVER_PREFERENCE', 0x00400000);
        define('SSL_OP_TLS_ROLLBACK_BUG', 0x00800000);
        define('SSL_OP_NO_SSLv2', 0x01000000);
        define('SSL_OP_NO_SSLv3', 0x02000000);
        define('SSL_OP_NO_TLSv1', 0x04000000);
        define('SSL_OP_NO_TLSv1_2', 0x08000000);
        define('SSL_OP_NO_TLSv1_1', 0x10000000);
        define('SSL_OP_NETSCAPE_CA_DN_BUG', 0x20000000);
        define('SSL_OP_NETSCAPE_DEMO_CIPHER_CHANGE_BUG', 0x40000000);
        define('SSL_OP_CRYPTOPRO_TLSEXT_BUG', 0x80000000);

        $options = 0;
        foreach((array) \Pancake\Config::get('tls.options') as $setting) {
            if(defined($setting))
                $options |= constant($setting);
        }
        
        return $options;
    #.endLongDefine
    #.options = #.eval EVAL_CODE false
    TLSCreateContext(/* .certificateChain */, /* .privateKey */, /* .cipherList */, /* .options */);
    #.endif

    unset($id, $vHost, $address);

    // Make sure the Pancake Module Loader can not be exploited
    disableModuleLoader();

    Config::workerDestroy();

    $Pancake_sockets[$Pancake_currentThread->socket] = $Pancake_currentThread->socket;

    $listenSockets = $listenSocketsOrig = $Pancake_sockets;

    // Initialize some variables
#.ifdef 'SUPPORT_TLS'
    $TLSConnections = array();
#.endif
#.ifdef 'SUPPORT_FASTCGI'
    $fastCGISockets = array();
#.endif
#.ifdef 'SUPPORT_AJP13'
    $ajp13Sockets = array();
#.endif
    $liveWriteSockets = array();
    $liveWriteSocketsOrig = array();
    $liveReadSockets = array();
    $socketData = array();
    $postData = array();
#.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
    $processedRequests = 0;
#.endif
    $requests = array();
#.ifdef 'SUPPORT_GZIP'
    $gzipPath = array();
#.endif
#.ifdef 'SUPPORT_PHP'
    $phpSockets = array();
#.endif
#.ifdef 'SUPPORT_WAITSLOTS'
    $waitSlots = array();
    $waitSlotsOrig = array();
    $waits = array();
#.endif
    
#.if BENCHMARK === true
    benchmarkFunction("hexdec");
#.endif
    
#.if #.Pancake\Config::get("main.naglesalgorithm", 0)
    // Disable Nagle's algorithm if requested
    NaglesAlgorithm(/*.bool #.Pancake\Config::get("main.naglesalgorithm", 0)*/);
#.endif

    // Ready
    $Pancake_currentThread->parentSignal(/* .constant 'SIGUSR1' */);

    // Set blocking for signals
    SigProcMask(/* .constant 'SIG_BLOCK' */, array(/* .constant 'SIGINT' */, /* .constant 'SIGHUP' */));

	//dt_debug_objects_store();

    // Set user and group
    setUser();

    // Wait for incoming requests
    while(Select($listenSockets, $liveWriteSockets
#.ifdef 'SUPPORT_WAITSLOTS'
    , $waitSlots ? /* .call 'Pancake\Config::get' 'main.waitslottime' */ : null
#.endif
    )) {
    	// If there are jobs left in the queue at the end of the job-run, we're going to jump back to this point to execute the jobs that are left
    	cycle:

    	// Upload to clients that are ready to receive
        foreach($liveWriteSockets as $socket) {
        	unset($liveWriteSockets[$socket]);

        	$requestObject = $requests[$socket];
            
#.ifdef 'SUPPORT_TLS'
            // For TLS handshake we need both reads and writes
            if(isset($TLSConnections[$socket]) && $TLSConnections[$socket] < /* .TLS_WRITE */) {
                goto TLSRead;
            }
#.endif
            goto liveWrite;
        }
        
#.ifdef 'SUPPORT_WAITSLOTS'
        // Check if there are requests waiting for a PHPWorker
        foreach($waitSlots as $socket) {
            unset($waitSlots[$socket]);
            $requestObject = $requests[$socket];
            goto load;
        }
#.endif
        
        // New connection, data from client, AJP13 or FastCGI or PHP SAPI data or command from Pancake master process
        foreach($listenSockets as $socket) {
        	unset($listenSockets[$socket]);

#.ifdef 'SUPPORT_TLS'
            // In case of a TLS renegotiation we might need a read event while in write step
            if(isset($TLSConnections[$socket]) && $TLSConnections[$socket] == /* .TLS_WRITE */) {
                $requestObject = $requests[$socket];
                goto liveWrite;
            }
#.endif
            
            // Data from client or new request on keep-alive socket
            if(isset($liveReadSockets[$socket]) || KeepAlive($socket)) {
                $requestObject = $requests[$socket];
                if(!isset($socketData[$socket])) {
                    $socketData[$socket] = "";
                }
                break;
            }
            
#.ifdef 'SUPPORT_AJP13'
        	if(isset($ajp13Sockets[$socket])) {
        		$ajp13 = $ajp13Sockets[$socket];

        		do {
        			$newData = Read($socket, (isset($result) ? ($result & /* .AJP13_APPEND_DATA */ ? $result ^ /* .AJP13_APPEND_DATA */ : $result) : 5));
        			if(isset($result) && $result & /* .AJP13_APPEND_DATA */)
        				$data .= $newData;
        			else
        				$data = $newData;
        			$result = $ajp13->upstreamRecord($data, $socket);
        			if($result === 0) {
        				unset($ajp13Sockets[$socket]);
        				unset($listenSocketsOrig[$socket]);
        				unset($result);
                        unset($data);
        				goto clean;
        			}
        		} while($result & /* .AJP13_APPEND_DATA */);

        		if(is_array($result)) {
        		    unset($ajp13Sockets[$socket]);
                    unset($listenSocketsOrig[$socket]);
        			list($socket, $requestObject) = $result;

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
        	if(isset($fastCGISockets[$socket])) {
        		$fastCGI = $fastCGISockets[$socket];
        		do {
        			$newData = Read($socket, (isset($result) ? ($result & /* .constant 'FCGI_APPEND_DATA' */ ? $result ^ /* .constant 'FCGI_APPEND_DATA' */ : $result) : 8));
        			if(isset($result) && $result & /* .constant 'FCGI_APPEND_DATA' */)
        				$data .= $newData;
        			else
        				$data = $newData;
        			$result = $fastCGI->upstreamRecord($data, $socket);
        			if($result === 0) {
        				unset($fastCGISockets[$socket]);
        				unset($listenSocketsOrig[$socket]);
        				unset($result);
                        unset($data);
        				goto clean;
        			}
        		} while($result & /* .constant 'FCGI_APPEND_DATA' */);

        		if(is_array($result)) {
        			if(isset($result[2])) {
        				// [2] will be set on error - this will cause Pancake to return to the cycle point after answering the client
        				$listenSockets[$socket] = $socket;
        			}

        			list($socket, $requestObject) = $result;

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

#.ifdef 'SUPPORT_PHP'
            if(isset($phpSockets[$socket])) {
                $packages = hexdec(Read($socket, 8));
				if(!$packages) {
					unset($listenSocketsOrig[$socket]);
                	Close($socket);
                    
                	$socket = $phpSockets[$socket];
                    unset($phpSockets[$socket]);
                    $requestObject = $requests[$socket];
					$requestObject->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
					goto write;
				}

                $length = hexdec(Read($socket, 8));

                if($packages > 1) {
                	$sockData = "";

                	while($packages--)
                		$sockData .= Read($socket, $length);

                	$obj = unserialize($sockData);

                	unset($sockData);
                }
                else
                	$obj = unserialize(Read($socket, $length));

				if(!($obj instanceof \stdClass)) {
					unset($listenSocketsOrig[$socket]);
                	unset($phpSockets[$socket]);
                	Close($socket);
                    
                    $socket = $phpSockets[$socket];
                    $requestObject = $requests[$socket];
					$requestObject->invalidRequest(new invalidHTTPRequestException('An internal server error occured while trying to handle your request.', 500));
					goto write;
				}
                
                unset($listenSocketsOrig[$socket]);
                Close($socket);
                $psocket = $socket;
                $socket = $phpSockets[$socket];
                unset($phpSockets[$psocket]);
                $requestObject = $requests[$socket];

				$requestObject->answerHeaders = (array) $obj->answerHeaders;
				$requestObject->answerBody = (string) $obj->answerBody;
				$requestObject->answerCode = (int) $obj->answerCode;

                unset($obj);

                goto write;
            }
#.endif

            // Internal communication
            if($socket == $this->socket) {
                switch(Read($this->socket, 128)) {
                    case "GRACEFUL_SHUTDOWN":
                        // Master wants us to shutdown - stop listening for new connections
                    	foreach($Pancake_sockets as $socket)
                    		unset($listenSocketsOrig[$socket]);

                    	$doShutdown = true;
                    	goto clean;
                    case "LOAD_FILE_POINTERS":
                        // Master got SIGHUP and wants us to flush the file pointers
                        LoadFilePointers();
                        goto clean;
                }
            }

            if(
#.if 0 != #.call 'Pancake\Config::get' 'main.maxconcurrent'
            /* .call 'Pancake\Config::get' 'main.maxconcurrent' */ < count($listenSocketsOrig) - count($Pancake_sockets) ||
#.endif
            !($socket = NonBlockingAccept($socket)))
                goto clean;

#.ifdef 'SUPPORT_TLS'
            GetSockName($socket, $xx, $port);
            
            // Check if we got the new connection on a TLS port
            if(in_array($port, Config::get('tls.ports'))) {
                $TLSConnections[$socket] = /* .TLS_ACCEPT */;
                TLSInitializeConnection($socket);
            }
#.endif
            
            $socketData[$socket] = "";
            break;
        }

#.ifdef 'SUPPORT_TLS'
        TLSRead:
        
        if(isset($TLSConnections[$socket])) {
            switch($TLSConnections[$socket]) {
                case /* .TLS_ACCEPT */:
                    switch(TLSAccept($socket)) {
                        case 1:
                            // Handshake OK
                            $TLSConnections[$socket] = /* .TLS_READ */;
                        case 2:
                            // OpenSSL wants read
                            $liveReadSockets[$socket] = true;
                            $listenSocketsOrig[$socket] = $socket;
                            
                            goto clean;
                        case 3:
                            // OpenSSL wants write
                            $liveWriteSocketsOrig[$socket] = $socket;
                            goto clean;
                        case 0:
                            // Error
                            goto close;
                    }
                    break;
                case /* .TLS_READ */:
                    if(isset($requests[$socket]))
                        $bytes = TLSRead($socket, $requestObject->requestHeaders["content-length"] - strlen($postData[$socket]));
                    else 
                        $bytes = TLSRead($socket, 10240);

                    if($bytes === 2) {
                        // OpenSSL wants read
                        $liveReadSockets[$socket] = true;
                        $listenSocketsOrig[$socket] = $socket;
                        
                        goto clean;
                    }
                    
                    if($bytes === 3) {
                        // OpenSSL wants write
                        $liveWriteSocketsOrig[$socket] = $socket;
                        goto clean;
                    }
                    break; 
            }
        } else
#.endif
        
        // Receive data from client
        if(isset($requests[$socket]))
            $bytes = @Read($socket, $requestObject->requestHeaders["content-length"] - strlen($postData[$socket]));
        else
            $bytes = @Read($socket, 10240);

        // Read() might return string(0) "" while the socket keeps at non-blocking state - This happens when the client closes the connection under certain conditions
        // We should not close the socket if Read() returns bool(false) - This might lead to problems with slow connections
        if($bytes === "")
            goto close;

        // Check if request was already initialized and we are only reading POST-data
        if(isset($requests[$socket])) {
            $postData[$socket] .= $bytes;
            if(strlen($postData[$socket]) >= $requestObject->requestHeaders["content-length"])
                goto readData;
        } else if($bytes) {
            $socketData[$socket] .= $bytes;

            // Check if all headers were received
            if(strpos($socketData[$socket], "\r\n\r\n")) {
            	// Split POST data
           		if(strpos($socketData[$socket], "POST") === 0) {
           			$data = explode("\r\n\r\n", $socketData[$socket], 2);
                	$socketData[$socket] = $data[0];
                    $postData[$socket] = $data[1];
                    unset($data);
                }

                goto readData;
            }

            // Avoid memory exhaustion by just sending random but long data that does not contain \r\n\r\n
            // I assume that no normal HTTP-header will be longer than 10 KiB
            if(strlen($socketData[$socket]) >= 10240)
                goto close;
        }
        // We are still waiting for header data - event-based read
        $liveReadSockets[$socket] = true;
        $listenSocketsOrig[$socket] = $socket;
        goto clean;

        readData:

        if(!isset($requests[$socket])) {
            // Get information about client
            GetPeerName($socket, $ip, $port);

            // Get local IP address and port
            GetSockName($socket, $lip, $lport);

            // Create request object and process headers
            try {
                $requestObject = $requests[$socket] = new HTTPRequest($ip, $port, $lip, $lport);
#.ifdef 'SUPPORT_TLS'
                if(isset($TLSConnections[$socket]))
                    $requestObject->TLS = true;
#.endif
                $requestObject->init($socketData[$socket]);
                
                unset($socketData[$socket]);
            } catch(invalidHTTPRequestException $exception) {
                $requestObject->invalidRequest($exception);
                
                unset($socketData[$socket], $exception);
                goto write;
            }
        }

        // Check for POST and get all POST data
        if($requestObject->requestType == 'POST') {
            if(strlen($postData[$socket]) >= $requestObject->requestHeaders["content-length"]) {
                if(strlen($postData[$socket]) > $requestObject->requestHeaders["content-length"])
                    $postData[$socket] = substr($postData[$socket], 0, $requestObject->requestHeaders["content-length"]);
                unset($listenSocketsOrig[$socket]);
                $requestObject->rawPOSTData = $postData[$socket];
                unset($postData[$socket]);
            } else {
                // We did not receive all POST data yet
                $liveReadSockets[$socket] = true;
                $listenSocketsOrig[$socket] = $socket;
                goto clean;
            }
        } else
            unset($listenSocketsOrig[$socket]);

#.if #.call 'Pancake\Config::get' 'main.allowtrace'
        // If we have a TRACE request we don't need to take any further action
        if($requestObject->requestType == 'TRACE')
            goto write;
#.endif

#.if #.call 'Pancake\Config::get' 'main.allowoptions'
        // Check for OPTIONS
        #.longDefine 'EVAL_CODE'
        $allow = "GET, POST, OPTIONS";
        if(Pancake\Config::get("main.allowhead") === true)
            $allow .= ", HEAD";
        if(Pancake\Config::get("main.allowtrace") === true)
            $allow .= ", TRACE";
        return $allow;
        #.endLongDefine
        if($requestObject->requestType == 'OPTIONS')
            $requestObject->setHeader('Allow', /* .eval EVAL_CODE false */);
#.endif

#.if Pancake\DEBUG_MODE === true
        // Output debug information
        if(isset($requestObject->getGETParams()['pancakedebug'])) {
            $requestObject->setHeader('Content-Type', 'text/plain');

            $body = 'Received Headers:' . "\r\n";
            $body .= $requestObject->requestLine . "\r\n";
            foreach($requestObject->requestHeaders as $name => $value)
                $body .= $name . ": " . $value . "\r\n";
            $body .= 'Received POST content:' . "\r\n";
            $body .= $postData[$socket] . "\r\n\r\n";
            $body .= 'Dump of RequestObject:' . "\r\n";
            $body .= print_r($requestObject, true);
            $requestObject->answerBody = $body;

            unset($body);

            goto write;
        }
#.endif

        load:

#.ifdef 'SUPPORT_AJP13'
        if($ajp13 = /* .VHOST_AJP13 */) {
        	$asocket = $ajp13->makeRequest($requestObject, $socket);
        	if($asocket === false)
        		goto write;
        	$listenSocketsOrig[$asocket] = $asocket;
        	$ajp13Sockets[$asocket] = $ajp13;
        	goto clean;
        }
#.endif

#.ifdef 'SUPPORT_FASTCGI'
    	// FastCGI
    	if($fastCGI = /* .VHOST_FASTCGI */) {
    		if($fastCGI->makeRequest($requestObject, $socket) === false)
    			goto write;
			$listenSocketsOrig[$fastCGI->socket] = $fastCGI->socket;
			$fastCGISockets[$fastCGI->socket] = $fastCGI;
    		goto clean;
    	}
#.endif

#.ifdef 'SUPPORT_PHP'
        // Check for PHP
        if($requestObject->mimeType == 'text/x-php'
#.ifdef 'SUPPORT_MULTIPLE_VHOSTS'
         && /* .VHOST_PHP_WORKERS */
#.endif
        ) {
            if(!($psocket = Socket(/* .constant 'AF_UNIX' */, /* .constant 'SOCK_SEQPACKET' */, 0))) {
            	$requestObject->invalidRequest(new invalidHTTPRequestException('Failed to create communication socket. Probably the server is overladed. Try again later.', 500));
            	goto write;
            }

            SetBlocking($psocket, false);

            if(Connect($psocket, /* .AF_UNIX */, /* .VHOST_SOCKET_NAME */)) {
#.ifdef 'SUPPORT_WAITSLOTS'
      			$waits[$socket]++;

            	if($waits[$socket] > /* .call 'Pancake\Config::get' 'main.waitslotwaitlimit' */) {
            		$requestObject->invalidRequest(new invalidHTTPRequestException('There was no worker available to serve your request. Please try again later.', 500));
            		goto write;
            	}

            	$waitSlotsOrig[$socket] = $socket;

            	goto clean;
#.else
            	$requestObject->invalidRequest(new invalidHTTPRequestException('There was no worker available to serve your request. Please try again later.', 500));
            	goto write;
#.endif
            }

#.ifdef 'SUPPORT_WAITSLOTS'
            unset($waitSlotsOrig[$socket]);
            unset($waits[$socket]);
#.endif

            $data = serialize($requestObject);

            SetBlocking($psocket, true);

            $packages = array();

            if($packageSize = AdjustSendBufferSize($psocket, strlen($data))) {
            	for($i = 0;$i < ceil(strlen($data) / $packageSize);$i++)
            		$packages[] = substr($data, $i * $packageSize, $packageSize);
            } else
            		$packages[] = $data;

            // First transmit the length of the serialized object, then the object itself
            Write($psocket, dechex(count($packages)));
            Write($psocket, dechex(strlen($packages[0])));
            foreach($packages as $data)
            	Write($psocket, $data);

            unset($packages);
            unset($data);

            $listenSocketsOrig[$psocket] = $psocket;
            $phpSockets[$psocket] = $socket;

            goto clean;
        }
#.endif

        // Get time of last modification
        $modified = filemtime(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath);
        // Set Last-Modified-Header as RFC 2822
        $requestObject->setHeader('Last-Modified', date('r', $modified));

        // Check for If-Modified-Since
        if(isset($requestObject->requestHeaders["if-modified-since"]) && strtotime($requestObject->requestHeaders["if-modified-since"]) == $modified) {
        	$requestObject->answerCode = 304;
            goto write;
        }

#.ifdef 'SUPPORT_DIRECTORY_LISTINGS'
        // Check for directory
        if(is_dir(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath)) {
            $files = array();

            foreach(scandir(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath) as $file) {
            	if($file == '.')
            		continue;
            	$isDir = is_dir(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath . $file);
            	$files[] =
            	array('name' => $file,
            			'address' => 
#.ifdef 'SUPPORT_TLS'
                        (isset($TLSConnections[$socket]) ? 'https://' : 'http://')
#.else
            			'http://'
#.endif 
            			. (isset($requestObject->requestHeaders["host"]) ? $requestObject->requestHeaders["host"] : $requestObject->vHost->listen[0]) . $requestObject->requestFilePath . $file . ($isDir ? '/' : ''),
            			'directory' => $isDir,
            			'type' => MIME::typeOf($file),
            			'modified' => filemtime(/* .VHOST_DOCUMENT_ROOT */ .  $requestObject->requestFilePath . $file),
            			'size' => filesize(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath . $file));
            }

            $requestObject->setHeader('Content-Type', 'text/html; charset=utf-8');

            ob_start();

            if(!include(/* .VHOST_DIRECTORY_PAGE_HANDLER */))
            	include 'php/directoryPageHandler.php';

            $requestObject->answerBody = ob_get_clean();
            unset($files);
        } else {
#.endif
            // We have a static file requested.
			$requestObject->setHeader('Content-Type', $requestObject->mimeType);
			$requestObject->setHeader('Accept-Ranges', 'bytes');

#.ifdef 'SUPPORT_GZIP'
            // Check if GZIP compression should be used
            if(isset($requestObject->acceptedCompressions["gzip"]) && /* .VHOST_ALLOW_GZIP_COMPRESSION */ === true && filesize(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath) >= /* .VHOST_GZIP_MINIMUM */
#.ifdef 'SUPPORT_GZIP_MIME_TYPE_LIMIT'
            && ((/* .VHOST */->gzipMimeTypes && in_array($requestObject->mimeType, /* .VHOST */->gzipMimeTypes)) || !/* .VHOST */->gzipMimeTypes)
#.endif
            ) {
                // Set encoding header
                $requestObject->setHeader('Content-Encoding', 'gzip');
                // Create temporary file
                $gzipPath[$socket] = tempnam(/* .call 'Pancake\Config::get' 'main.tmppath' */, 'GZIP');
                $gzipFileHandle = gzopen($gzipPath[$socket], 'w' . /* .VHOST_GZIP_LEVEL */);
                // Load uncompressed requested file
                $requestedFileHandle = fopen(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath, 'r');
                // Compress file
                while(!feof($requestedFileHandle))
                    gzwrite($gzipFileHandle, fread($requestedFileHandle, /* .WRITE_LIMIT */));
                // Close GZIP-resource and open normal file-resource
                gzclose($gzipFileHandle);
                $requestObject->fileHandle = fopen($gzipPath[$socket], 'r');
                // Set Content-Length
                $requestObject->setHeader('Content-Length', filesize($gzipPath[$socket]) - $requestObject->rangeFrom);
            } else {
#.endif
                // No GZIP
                $requestObject->setHeader('Content-Length', filesize(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath) - $requestObject->rangeFrom);
                $requestObject->fileHandle = fopen(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath, 'r');
#.ifdef 'SUPPORT_GZIP'
            }
#.endif

            // Check if a specific range was requested
            if($requestObject->rangeFrom) {
                $requestObject->answerCode = 206;
                fseek($requestObject->fileHandle, $requestObject->rangeFrom);
#.ifdef 'SUPPORT_GZIP'
                if($gzipPath[$socket])
                    $requestObject->setHeader('Content-Range', 'bytes ' . $requestObject->rangeFrom . '-' . (filesize($gzipPath[$socket]) - 1) . '/' . filesize($gzipPath[$socket]));
                else
#.endif
                    $requestObject->setHeader('Content-Range', 'bytes ' . $requestObject->rangeFrom . '-' . (filesize(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath) - 1).'/'.filesize(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath));
            }
#.ifdef 'SUPPORT_DIRECTORY_LISTINGS'
        }
#.endif

        write:

        // Build answer headers and answer body as string
        $requestObject->buildAnswerHeaders();
        
#.if #.Pancake\Config::get("main.prebuffer", 0)
        // Add some data to the buffer to save a write cycle
        if($requestObject->fileHandle) {
            $requestObject->writeBuffer .= fread($requestObject->fileHandle, /* .number #.Pancake\Config::get("main.prebuffer", 0)*/);
        }
#.endif

        #.OUTPUT_TYPE = Pancake\OUTPUT_REQUEST | Pancake\OUTPUT_LOG
        
        // Output request information
        out('REQ ' 
        . $requestObject->answerCode . ' ' 
        . $requestObject->remoteIP . ': ' 
        . $requestObject->requestLine . ' on vHost ' 
        . /* .VHOST_NAME */
        . ' (via ' . $requestObject->requestHeaders["host"] 
        . ' from ' . (isset($requestObject->requestHeaders["referer"]) ? $requestObject->requestHeaders["referer"] : "") . ') - ' 
        . (isset($requestObject->requestHeaders["user-agent"]) ? $requestObject->requestHeaders["user-agent"] : "")
#.ifdef 'SUPPORT_TLS'
        . (isset($TLSConnections[$socket]) ? " - " . TLSCipherName($socket) : "")
#.endif
        , /* .OUTPUT_TYPE */);

	    // Check if user wants keep-alive connection
        if($requestObject->answerHeaders["connection"] == 'keep-alive')
            KeepAlive($socket, true);

#.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
	        // Increment amount of processed requests
	        $processedRequests++;
#.endif

        // Clean some data now to improve RAM usage
        unset($postData[$socket]);
        unset($liveReadSockets[$socket]);

        liveWrite:

        // The buffer should usually only be empty if the hard limit was reached - In this case Pancake won't allocate any buffers except when the client really IS ready to receive data
        if(!strlen($requestObject->writeBuffer) 
#.ifdef 'SUPPORT_TLS'
        && $requestObject->fileHandle
#.endif
        )
        	$requestObject->writeBuffer = fread($requestObject->fileHandle, /* .call 'Pancake\Config::get' 'main.writebuffermin' */);

#.ifdef 'SUPPORT_TLS'
        if(isset($TLSConnections[$socket])) {
            $TLSConnections[$socket] = /* .TLS_WRITE */;
            if(($writtenLength = TLSWrite($socket, $requestObject->writeBuffer)) === false)
                goto close;

            if($writtenLength == -1) {
                // OpenSSL wants read (possible renegotiation)
                $listenSocketsOrig[$socket] = $socket;
                goto clean;
            }
            
            if($writtenLength == 0) {
                // OpenSSL wants write
                $liveWriteSocketsOrig[$socket] = $socket;
                goto clean;
            }
            
            $requestObject->writeBuffer = substr($requestObject->writeBuffer, $writtenLength);
        } else
#.endif

        // Write data to socket
        if(@WriteBuffer($socket, $requestObject->writeBuffer) === false)
            goto close;

        // Add data to buffer if not all data was sent yet
        if(strlen($requestObject->writeBuffer) < #.call 'Pancake\Config::get' 'main.writebuffermin'
        && $requestObject->fileHandle
        && !feof($requestObject->fileHandle)
#.if #.call 'Pancake\Config::get' 'main.writebufferhardmaxconcurrent'
        && count($requests) < #.call 'Pancake\Config::get' 'main.writebufferhardmaxconcurrent'
#.endif
#.if #.call 'Pancake\Config::get' 'main.allowhead'
        && $requestObject->requestType != 'HEAD'
#.endif
       	)
        	$requestObject->writeBuffer .= fread($requestObject->fileHandle,
#.if #.call 'Pancake\Config::get' 'main.writebuffersoftmaxconcurrent'
        			(count($requests) > /* .call 'Pancake\Config::get' 'main.writebuffersoftmaxconcurrent' */ ? /* .call 'Pancake\Config::get' 'main.writebuffermin' */ : /* .WRITE_LIMIT */)
#.else
        			#.WRITE_LIMIT
#.endif
        			- strlen($requestObject->writeBuffer));

        // Check if more data is available and we should store the socket for another write cycle
        if(strlen($requestObject->writeBuffer) || ($requestObject->fileHandle && !feof($requestObject->fileHandle)
#.if #.call 'Pancake\Config::get' 'main.allowhead'
        && $requestObject->requestType != 'HEAD'
#.endif
        )) {
            // Event-based writing - In the time the client is still downloading we can process other requests
            $liveWriteSocketsOrig[$socket] = $socket;
            goto clean;
        }

        close:

        // Close socket
        if(!isset($requestObject) || $requestObject->answerHeaders["connection"] != 'keep-alive') {
#.ifdef 'SUPPORT_TLS'
            if(isset($TLSConnections[$socket])) {
                // Do TLS protocol shutdown
                unset($TLSConnections[$socket]);
                TLSShutdown($socket);
            }
#.endif
            //@socket_shutdown($requestSocket);
            Close($socket);

            unset($listenSocketsOrig[$socket]);
        } else if($requestObject->answerHeaders["connection"] == 'keep-alive') {
            $listenSocketsOrig[$socket] = $socket;
#.ifdef 'SUPPORT_TLS'
            if(isset($TLSConnections[$socket])) {
                // Reset TLS connection status from WRITE to READ
                $TLSConnections[$socket] = /* .TLS_READ */;
            }
#.endif
        }


#.ifdef 'SUPPORT_WAITSLOTS'
        unset($waitSlotsOrig[$socket]);
        unset($waits[$socket]);
#.endif

        unset($socketData[$socket]);
        unset($postData[$socket]);
        unset($liveReadSockets[$socket]);
        unset($requests[$socket]);
#.ifdef 'SUPPORT_GZIP'
        if(isset($gzipPath[$socket])) {
            unlink($gzipPath[$socket]);
            unset($gzipPath[$socket]);
        }
#.endif

        unset($liveWriteSocketsOrig[$socket]);

#.if Pancake\DEBUG_MODE === true
        // Pancake profiler
        if($results = benchmarkFunction(null, true)) {
        	foreach($results as $function => $functionResults) {
        		foreach($functionResults as $result)
        			$total += $result;

        		out('Benchmark of function ' . $function . '(): ' . count($functionResults) . ' calls' . ( $functionResults ? ' - ' . (min($functionResults) * 1000) . ' ms min - ' . ($total / count($functionResults) * 1000) . ' ms ave - ' . (max($functionResults) * 1000) . ' ms max - ' . ($total * 1000) . ' ms total' : "") , OUTPUT_DEBUG | OUTPUT_SYSTEM | OUTPUT_LOG);
        		unset($total);
        	}

        	unset($result);
        	unset($functionResults);
        	unset($results);
        }
#.endif

#.if 0 < #.call 'Pancake\Config::get' 'main.requestworkerlimit'
        // Check if request-limit is reached
        if($processedRequests >= /* .call 'Pancake\Config::get' 'main.requestworkerlimit' */) {
            Write($Pancake_currentThread->socket, "EXPECTED_SHUTDOWN");
            $doShutdown = true;
        }
#.endif

        clean:

#.if #.call 'Pancake\Config::get' 'main.maxconcurrent'
        // Do concurrency limit
        if(isset($decliningNewRequests) && /* .call 'Pancake\Config::get' 'main.maxconcurrent' */ > count($listenSocketsOrig)) {
            foreach($Pancake_sockets as $socket)
                $listenSocketsOrig[$socket] = $socket;
            unset($decliningNewRequests);
        }

        if(/* .call 'Pancake\Config::get' 'main.maxconcurrent' */ < count($listenSocketsOrig) - /* .LISTEN_SOCKET_COUNT */) {
            foreach($Pancake_sockets as $socket)
                unset($listenSocketsOrig[$socket]);
            $decliningNewRequests = true;
        }
#.endif

        // Clean old data
        unset($bytes);
        // We have some checks for whether the requestObject is set so we MUST unset it
        unset($requestObject);

        // If jobs are waiting, execute them before select()ing again
        if($listenSockets || $liveWriteSockets
#.ifdef 'SUPPORT_WAITSLOTS'
        || $waitSlots
#.endif
        )
        	goto cycle;

        // Check if we are ready to shutdown if we should
        if(isset($doShutdown)
        && !$requests) {
        	break;
        }

        $listenSockets = $listenSocketsOrig;
        $liveWriteSockets = $liveWriteSocketsOrig;
#.ifdef 'SUPPORT_WAITSLOTS'
        $waitSlots = $waitSlotsOrig;
#.endif

        // Reset PHP statcache
        clearstatcache();
    }
?>

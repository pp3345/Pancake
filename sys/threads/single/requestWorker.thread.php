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
    
    $fileInfo = new finfo(FILEINFO_MIME);
    
    function stop() {
        exit;
    }
    
    // Set user and group
    Pancake_setUser();
    
    // Set handler for stop-signals
    pcntl_signal(SIGTERM, 'stop');
    
    // Wait for incoming requests     
    while($message = Pancake_IPC::get()) {
        // Set worker unavailable
        $currentThread->setAvailable();
        
        // Inform SocketWorker
        Pancake_IPC::send(PANCAKE_SOCKET_WORKER_TYPE.$message, 'OK');
        
        // Accept connection
        if(!($requestSocket = socket_accept($Pancake_sockets[$message]))) {
            $currentThread->setAvailable();
            continue;
        }
        
        // Firefox sends HTTP-Request in first TCP-segment while Chrome sends HTTP-Request after TCP-Handshake
        // Set timeout - DoS-protection
        socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
        
        // Receive data from client
        while($bytes = socket_read($requestSocket, 16384)) {
            if(!$data)
                socket_set_nonblock($requestSocket);
            $data .= $bytes;
        }
        
        if(!$data) {
            $currentThread->setAvailable();
            continue;
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
        
        //socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
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
        
        // Check for directory
        if(is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath())) {
            $directory = scandir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath());
            
            $body =  '<!doctype html>';
            $body .= '<html>';
            $body .= '<head>';
            $body .= '<title>Directory Listing of '.$request->getRequestFilePath().'</title>';
            $body .= '</head>';
            $body .= '<body>';
            $body .= '<h1>'.$request->getRequestFilePath().'</h1>';
            $body .= '<hr/>';
            foreach($directory as $file) {
                if($file == '.' || $file == '..')
                    continue;
                if(is_dir($request->getvHost()->getDocumentRoot().$request->getRequestFilePath().'/'.$file))
                    $body .= '<a href="http://'.$request->getRequestHeader('Host').$request->getRequestFilePath().'/'.$file.'/">'.$file.'</a><br/>';
                else
                    $body .= '<a href="http://'.$request->getRequestHeader('Host').$request->getRequestFilePath().'/'.$file.'">'.$file.'</a><br/>';
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
        
        // Set blocking, so that we don't try to write when socket is unavailable
        socket_set_block($requestSocket);
        
        // Write answer to socket
        socket_write($requestSocket, $answer);
        
        // Close socket
        socket_shutdown($requestSocket);
        
        // Output request-information
        Pancake_out('REQ '.$request->getAnswerCode().' '.$ip.': '.$request->getRequestLine().' on vHost '.$request->getRequestHeader('Host').' - '.$request->getRequestHeader('User-Agent'), REQUEST);
        
        // Set worker available
        $currentThread->setAvailable();
        
        // Clean old request-data
        unset($data);
        unset($bytes);
        unset($request);
        unset($sentTotal);
    }
?>

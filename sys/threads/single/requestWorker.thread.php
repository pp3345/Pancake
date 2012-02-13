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
    
    function stop() {
        exit;
    }
    
    // Set handler for stop-signals
    pcntl_signal(SIGTERM, 'stop');
    
    // Wait for incoming requests     
    while($message = Pancake_IPC::get()) {
        // Clean old request-data
        unset($data);
        
        // Set worker unavailable
        $currentThread->setAvailable();
        
        // Accept connection
        if(!($requestSocket = socket_accept($Pancake_sockets[$message]))) {
            $currentThread->setAvailable();
            continue;
        }
        
        // Inform SocketWorker
        Pancake_IPC::send(PANCAKE_SOCKET_WORKER_TYPE.$message, 'OK');
        
        // Firefox sends HTTP-Request in first TCP-segment while Chrome sends HTTP-Request after TCP-Handshake
        // Set timeout - DoS-protection
        socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => Pancake_Config::get('main.readtimeout')));
        
        // Receive data from client
        while($bytes = socket_read($requestSocket, 16384)) {
            $data .= $bytes;
            socket_set_nonblock($requestSocket);
        }
        
        if(!$data) {
            $currentThread->setAvailable();
            continue;
        }
        
        // Get information about client
        socket_getPeerName($requestSocket, $ip, $port);
        
        // Output debug-information
        //Pancake_out('Handling request from '.$ip.':'.$port, SYSTEM, false, true);
    
        // Create HTTPRequest
        try {
            $request = new Pancake_HTTPRequest($currentThread);
            $request->init($data);
        } catch(Pancake_InvalidHTTPRequestException $e) {
            goto write; // EVIL! :O
        }
        
        // Output request-information
        Pancake_out('FROM '.$ip.': '.$request->getRequestType().' '.$request->getRequestFilePath().' HTTP/'.$request->getProtocolVersion().' on vHost '.$request->getRequestHeader('Host'), REQUEST);
        
        // Let an available vHostWorker handle this request 
        Pancake_vHostWorker::findAvailable($request->getRequestHeader('Host'))->handleRequest($request);
        
        //socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Wait for answer from vHostWorker
        $request = Pancake_IPC::get();
        
        write:
        
        // Write answer to socket
        socket_write($requestSocket, $request->buildAnswer());
        
        // Close socket
        socket_shutdown($requestSocket);
        
        // Set worker available
        $currentThread->setAvailable();
    }
?>

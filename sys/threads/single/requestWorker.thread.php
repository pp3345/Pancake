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
        
        // Set timeout - DoS-protection
        socket_set_option($requestSocket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => 10000));
        
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
        Pancake_out('Handling request from '.$ip.':'.$port, SYSTEM, false, true);
        
        $x = explode("\r\n", $data);
        foreach($x as $header) {
            $header = explode(':', $header, 2);
            if($header[0] == 'Host') {
                $vHost = trim($header[1]);
                break;
            }
        }
        
        Pancake_vHostWorker::findAvailable($vHost)->handleRequest($currentThread, $data);
        
        //socket_set_option($requestSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        if(!socket_write($requestSocket, Pancake_IPC::get())) {
            $currentThread->setAvailable();
            continue;
        }
        
        // Close socket
        socket_shutdown($requestSocket);
        //socket_close($requestSocket);
        
        // Set worker available
        $currentThread->setAvailable();
    }
?>

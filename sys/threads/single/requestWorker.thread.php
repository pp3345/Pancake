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
        $requestSocket = socket_accept($Pancake_sockets[$message]);
        
        // Inform SocketWorker
        Pancake_IPC::send(PANCAKE_SOCKET_WORKER_TYPE.$message, 'OK');
        
        // Receive data from client
        while($bytes = socket_read($requestSocket, 16)) {
            $data .= $bytes;
            socket_set_nonblock($requestSocket);
        }
        
        // Get information about client
        socket_getpeername($requestSocket, $ip, $port);
        
        // Output debug-information
        Pancake_out('Handling request from '.$ip.':'.$port, SYSTEM, false, true);
        
        // Close socket
        socket_close($requestSocket);
        
        // Set worker available
        $currentThread->setAvailable();
    }
?>

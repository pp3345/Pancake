<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* socketWorker.thread.php                                      */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    global $Pancake_sockets;
    
    function stop() {
        global $socket;
        socket_close($socket);
        exit;
    }
    
    // Set user and group
    Pancake_setUser();
    
    // Set handler for stop-signals
    pcntl_signal(SIGTERM, 'stop');
    
    // Get socket
    $socket = $Pancake_sockets[$currentThread->port];
    
    // Listen for connections on socket
    while(socket_select($read = array($socket), $write = null, $except = null, 31536000) !== false) {
        // Output debug-information
        Pancake_out('Received request on port '.$currentThread->port, SYSTEM, false, true);
        
        // Tell request-worker to handle request
        Pancake_RequestWorker::findAvailable()->handleRequest($currentThread->port);
        
        while(Pancake_IPC::get() !== 'OK')
            continue;
    }
?>

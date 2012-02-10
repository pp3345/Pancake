<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHostWorker.thread.php                                       */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    $currentThread->listen = Pancake_Config::get('vhosts.'.$currentThread->name.'.listen');
    $currentThread->updateSHMEM();
    
    while($message = Pancake_IPC::get()) {
        $currentThread->setAvailable();
        
        Pancake_out('Handling request', SYSTEM, false, true);
        
        $message = explode("\n", $message, 2);     
        
        $header =  'HTTP/1.1 200 OK'."\n";
        $header .= 'Content-type: text/plain'."\n";
        $header .= 'Connection: keep-alive'."\n";
        $header .= 'Server: Pancake/0.1'."\n";
        
        $body = 'Received Data:'."\n";
        $body .= $message[1];
        $body .= "\n\n";
        $body .= "Answer:"."\n";
        $body .= $header;
        
        $header .= 'Content-Length: '.strlen($body)."\n\n";
        
        Pancake_IPC::send($message[0], $header.$body);
        
        $currentThread->setAvailable();
    }
?>

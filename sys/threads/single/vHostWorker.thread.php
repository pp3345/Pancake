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
        
        $message->setHeader('Content-Type', 'text/plain');
        
        $body = 'Received Data:'."\r\n";
        $body .= $message->getRequestHeaders()."\r\n";
        $body .= 'Dump of RequestObject:'."\r\n";
        $body .= print_r($message, true);
        $message->setAnswerBody($body);
        
        Pancake_IPC::send($message->getRequestWorker()->IPCid, $message);
        
        $currentThread->setAvailable();
    }
?>

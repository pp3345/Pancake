<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHostWorkerController.thread.php                             */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
        
    while($message = Pancake_IPC::get()) {
        $vHostWorkers[$message->id] = $message;
        Pancake_SharedMemory::put($vHostWorkers, PANCAKE_VHOST_WORKER_TYPE.'0001');
    }
?>

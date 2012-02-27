<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* phpWorker.thread.php                                         */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;    
    
    // Clean
    foreach(get_defined_vars() as $varName => $varValue) {
        if($varName != 'Pancake_currentThread')
            eval('unset($'.$varName.');');
    }
    
    unset($varName);
    unset($varValue);
    
    // Load ScriptUtils
    require_once 'php/util.php';    
    
    // Set user and group
    Pancake_setUser();                      
    
    function Pancake_PHPExitHandler($exitmsg = null) {
        echo $exitmsg;
        return !defined('PANCAKE_PHP');
    }
    
    // Set exit handler so that Pancake won't die when a script calls exit() oder die()
    dt_set_exit_handler('Pancake_PHPExitHandler');        
    dt_throw_exit_exception(true);
    
    // Wait for requests    
    while($Pancake_message = Pancake_IPC::get()) {
        
        // Get request from Shared Memory
        $Pancake_request = Pancake_SharedMemory::get($Pancake_message);
        if($Pancake_request === false) {
            for($i = 0;$i < 10 && $Pancake_request === false;$i++) {
                usleep(1000);
                $Pancake_request = Pancake_SharedMemory::get($Pancake_message);
            }
            if($Pancake_request === false)
                continue;
        }
        
        // Change directory to DocumentRoot of the vHost / requested file path
        chdir($Pancake_currentThread->vHost->getDocumentRoot() . dirname($Pancake_request->getRequestFilePath()));
        
        // Get currently defined funcs, consts and vars
        $Pancake_funcsPre = get_defined_functions();
        $Pancake_constsPre = get_defined_constants(true);
        $Pancake_varsPre = get_defined_vars();
        
        // Set environment vars
        $_GET = $Pancake_request->getGETParams();
        $_POST = $Pancake_request->getPOSTParams();
        $_COOKIE = $Pancake_request->getCookies();
        $_SERVER = $Pancake_request->createSERVER();
        
        define('PANCAKE_PHP', true);
        
        // Start output buffer
        ob_start();

        // Script will throw an exception when trying to exit, this way we can handle it easily
        try {
            include $Pancake_request->getvHost()->getDocumentRoot() . $Pancake_request->getRequestFilePath();
        } catch(Exception $e) {
        }
        
        // Get contents from output buffer
        $contents = ob_get_contents();
        
        // Update request-object and send it to request-worker
        $Pancake_request->setAnswerBody($contents);
        Pancake_SharedMemory::put($Pancake_request, $Pancake_message);
        Pancake_IPC::send($Pancake_request->getRequestWorker()->IPCid, 1);
        
        // Clean
        ob_end_clean();
        
        $funcsPost = get_defined_functions();
        $constsPost = get_defined_constants(true);
        $varsPost = get_defined_vars();
        
        foreach($funcsPost['user'] as $func) {
            if(!in_array($func, $Pancake_funcsPre['user'])) {
                dt_remove_function($func);
            }
        }
        
        if($constsPost['user'])
            foreach($constsPost['user'] as $const => $constValue) {
                if(!array_key_exists($const, $Pancake_constsPre['user'])) {
                    dt_remove_constant($const);
                }
            }
            
        foreach($varsPost as $var => $varValue) {
            if(!array_key_exists($var, $Pancake_varsPre) && $var != 'Pancake_varsPre') {
                eval('unset($'.$var.');');
            }
        }
        
        unset($contents);
        unset($Pancake_funcsPre);
        unset($Pancake_constsPre);
        unset($Pancake_varsPre);
        unset($funcsPost);
        unset($constsPost);
        unset($varsPost);
        unset($const);
        unset($constValue);
        unset($var);
        unset($varValue);
        unset($func);
        unset($Pancake_request);
        unset($Pancake_message);
        unset($e);
        unset($_GET);
        unset($_SERVER);
        unset($_POST);
        unset($GLOBALS);
    }      
?>

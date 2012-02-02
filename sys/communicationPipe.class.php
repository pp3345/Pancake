<?php
  
    /****************************************************************/
    /* dreamServ                                                    */
    /* communicationPipe.class.php                                  */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    /**
    * A pipe for communication between two threads
    */
    class CommunicationPipe {
        private $child = null;
        private $parent = 0;
        private $filePath = null;
        private $fileStream = null;
        
        /**
        * Creates a new pipe
        * 
        * @param Thread $child An instance of Thread that represents the child
        * @return CommunicationPipe
        */
        public function __construct(Thread $child) {
            $this->child = $child;
            $this->filePath = Config::get('main.pipepath').$this->child->pid.'.pipe';
            $this->fileStream = fopen($this->filePath, 'a+');
        }
        
        /**
        * Sends data over the pipe
        * 
        * @param mixed $data The data to be sent
        * @param bool $wake If true, sends a wakeup-signal (SIGUSR1) to the child
        */
        public function send($data, $wake = true) {
            if(fwrite($this->fileStream, $data) === false)
                return false;
            if($wake)
                var_dump($this->child->signal(SIGUSR1));
            return true;
        }
        
        /**
        * Reads data from the pipe
        */
        public function read() {
            $data = fread($this->fileStream, 16384);
            if($data === false)
                return false;
            return $data;
        }
        
        /**
        * Destroys the pipe
        */
        public function __destruct() {
            fclose($this->fileStream);
            if(!unlink($this->filePath))
                return false;
            return true;
        } 
    }
?>

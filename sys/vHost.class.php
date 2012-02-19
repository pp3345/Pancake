<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHost.class.php                                              */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * Represents a single virtual host in Pancake    
    */
    class Pancake_vHost {
        private $name = null;
        private $documentRoot = null;
        private $listen = array();
        private $phpCodeCache = array();
        private $phpWorkers = 0;
        private $indexFiles = array();
        
        /**
        * Loads a vHost
        * 
        * @param mixed $name Name of the vHost as configured
        * @return Pancake_vHost
        */
        public function __construct($name) {
            $this->name = $name;
            
            // Get configured settings
            $config = Pancake_Config::get('vhosts.'.$this->name);
            $this->documentRoot = $config['docroot'];
            
            // Check if document root exists
            if(!file_exists($this->documentRoot))
                throw new Exception('DocumentRoot does not exist: '.$this->documentRoot);
                
            // Check for Hosts to listen on
            $this->listen = $config['listen'];
            if(count($this->listen) < 1)
                throw new Exception('You need to specify at least one address to listen on');
            $this->phpCodeCache = $config['phpcache'];
            $this->phpWorkers = $config['phpworkers'];
            $this->indexFiles = $config['index'];
            
            // Check PHP-CodeCache
            if($this->phpCodeCache) {
                foreach($this->phpCodeCache as $id => $codeFile)
                    if(!file_exists($codeFile) || !is_readable($codeFile)) {
                        unset($this->phpCodeCache[$id]);
                        throw new Exception('Specified CodeCache-File does not exist or isn\'t readable: '.$codeFile);
                    }
                if(!$this->phpWorkers)
                    throw new Exception('The value for phpworkers must be greater or equal 1 if you want to use the CodeCache.');
            }
        }
        
        /**
        * Returns the name of the vHost
        * 
        */
        public function getName() {
            return $this->name;
        }
        
        /**
        * Get the DocumentRoot of the vHost
        * 
        */
        public function getDocumentRoot() {
            return $this->documentRoot;
        }
        
        /**
        * Get Hosts the vHost listens on
        * 
        */
        public function getListen() {
            return $this->listen;
        }
        
        /**
        * Get files loaded in the PHP-CodeCache of this vHost
        * 
        */
        public function getCodeCacheFiles() {
            return $this->phpCodeCache;
        }
        
        /**
        * Get file-names allowed as directory-indexes
        * 
        */
        public function getIndexFiles() {
            return $this->indexFiles;
        }
    }
?>

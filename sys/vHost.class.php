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
        private static $vHosts = 0;
        private $id = 0;
        private $name = null;
        private $documentRoot = null;
        private $listen = array();
        private $phpCodeCache = array();
        private $phpCodeCacheExcludes = array();
        private $phpWorkers = 0;
        private $phpWorkerLimit = 0;
        private $indexFiles = array();
        private $authDirectories = array();
        private $authFiles = array();
        private $writeLimit = 0;
        private $allowDirectoryListings = false;
        private $gzipMinimum = 0;
        private $gzipLevel = -1;
        private $allowGZIP = false;
        private $isDefault = false;
        static private $defaultvHost = null;
        
        /**
        * Loads a vHost
        * 
        * @param mixed $name Name of the vHost as configured
        * @return Pancake_vHost
        */
        public function __construct($name) {
            $this->name = $name;
            
            // Set ID
            $this->id = self::$vHosts++;
            
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
            $this->phpCodeCacheExcludes = $config['phpcacheexclude'];
            $this->phpWorkers = $config['phpworkers'];
            $this->indexFiles = $config['index'];
            $this->writeLimit = (int) $config['writelimit'];
            $this->allowDirectoryListings = (bool) $config['allowdirectorylistings'];
            $this->gzipMinimum = (int) $config['gzipmin'];
            $this->gzipLevel = (int) $config['gziplevel'];
            $this->phpWorkerLimit = (int) $config['phpworkerlimit'];
            $this->allowGZIP = (bool) $config['enablegzip'];
            $this->isDefault = (bool) $config['isdefault'];
            if($this->isDefault === true)
                self::$defaultvHost = $this;
            
            // Load files and directories that need authentication
            if($config['auth']) {
                foreach($config['auth'] as $authFileConfig) {
                    // Dirty workaround for bug in PECL yaml
                    foreach($authFileConfig as $index => $value) {
                        if($index != 'type' && $index != 'realm' && $index != 'authfiles') {
                            $authFile = $index;
                            break;
                        }
                    }
                    if(!is_array($authFileConfig['authfiles']) || ($authFileConfig['type'] != 'basic' && $authFileConfig['type'] != 'digest')) {
                        trigger_error('Invalid Authentication configuration for "'.$authFile.'"', E_USER_WARNING);
                        continue;
                    }
                    if(is_dir($this->documentRoot.$authFile)) {
                        $this->authDirectories[$authFile] = array(
                                                                    'realm' => $authFileConfig['realm'],
                                                                    'type' => $authFileConfig['type'],
                                                                    'authfiles' => $authFileConfig['authfiles']);
                    } else {
                        $this->authFiles[$authFile] = array(
                                                            'realm' => $authFileConfig['realm'],
                                                            'type' => $authFileConfig['type'],
                                                            'authfiles' => $authFileConfig['authfiles']);
                    }
                }
            }
            
            // Check PHP-CodeCache
            if($this->phpCodeCache) {
                foreach($this->phpCodeCache as $id => $codeFile)
                    if(!file_exists($this->documentRoot . '/' . $codeFile) || !is_readable($this->documentRoot . '/' . $codeFile)) {
                        unset($this->phpCodeCache[$id]);
                        throw new Exception('Specified CodeCache-File does not exist or isn\'t readable: '.$codeFile);
                    }
                if(!$this->phpWorkers)
                    throw new Exception('The value for phpworkers must be greater or equal 1 if you want to use the CodeCache.');
            }
        }
        
        /**
        * Checks whether a specified file requires authentication
        * 
        * @param string $filePath Path to the file, relative to the DocRoot of the vHost
        * @return mixed Returns false or array with realm and type
        */
        public function requiresAuthentication($filePath) {
            if($this->authFiles[$filePath])
                return $this->authFiles[$filePath];
            else if($this->authDirectories[$filePath])
                return $this->authDirectories[$filePath];
            else {
                while($filePath != '/') {
                    $filePath = dirname($filePath);
                    if($this->authDirectories[$filePath])
                        return $this->authDirectories[$filePath];
                }
            }
            return false;
        }
        
        /**
        * Checks if user and password for a file are correct
        * 
        * @param string $filePath Path to the file, relative to the DocRoot of the vHost
        * @param string $user Username
        * @param string $password Password
        * @return bool
        */
        public function isValidAuthentication($filePath, $user, $password) {
            if(!$this->authFiles && !$this->authDirectories)
                return false;
            if($this->authFiles[$filePath]) {
                foreach($this->authFiles[$filePath]['authfiles'] as $authfile) {
                    if(Pancake_AuthenticationFile::get($authfile)->isValid($user, $password))
                        return true;
                }
            } else if($this->authDirectories[$filePath]) {
                foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
                    if(Pancake_AuthenticationFile::get($authfile)->isValid($user, $password))
                        return true;
                }
            } else {
                while($filePath != '/') {
                    $filePath = dirname($filePath);
                    if($this->authDirectories[$filePath])
                        foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
                            if(Pancake_AuthenticationFile::get($authfile)->isValid($user, $password))
                                return true;
                        }
                }
            }
            return false;
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
        
        /**
        * Get maximum size for a single write-action
        * 
        */
        public function getWriteLimit() {
            return $this->writeLimit;
        }
        
        /**
        * Returns whether directory listings are allowed or not
        * 
        */
        public function allowDirectoryListings() {
            return $this->allowDirectoryListings;
        }
        
        /**
        * Returns the minimum filesize for using GZIP-compression   
        *                                                       
        */
        public function getGZIPMimimum() {
            return $this->gzipMinimum;
        }
        
        /**
        * Returns the level of GZIP-compression to use
        * 
        */
        public function getGZIPLevel() {
            return $this->gzipLevel;
        }
        
        /**
        * Returns whether GZIP-compression can be used or not
        * 
        */
        public function allowGZIPCompression() {
            return $this->allowGZIP;
        }
        
        /**
        * Returns the amount of PHP-workers the vHost is running
        * 
        */
        public function getPHPWorkerAmount() {
            return $this->phpWorkers;
        }
        
        /**
        * Returns the unique ID of the vHost
        * 
        */
        public function getID() {
            return $this->id;
        }
        
        /**
        * Returns the limit of requests a PHPWorker may process until it has to be restarted
        * 
        */
        public function getPHPWorkerLimit() {
            return $this->phpWorkerLimit;
        }
        
        /**
        * Returns whether this vHost is the default vHost or not
        * 
        */
        public function isDefault() {
            return $this->isDefault;
        }
        
        /**
        * Checks if the FileName (relative to DocumentRoot) should be excluded from CodeCache
        * 
        * @param string $fileName
        */
        public function isExcludedFile($fileName) {
            return in_array($fileName, (array) $this->phpCodeCacheExcludes);
        }
        
        /**
        * Returns the instance of the default virtual host
        * 
        */
        public static function getDefault() {
            return self::$defaultvHost;
        }
    }
?>

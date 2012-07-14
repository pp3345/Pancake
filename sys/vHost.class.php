<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHost.class.php                                              */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    /**
    * Represents a single virtual host in Pancake    
    */
    class vHost {
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
        private $phpInfoConfig = true;
        private $phpInfovHosts = true;
        private $onEmptyPage204 = true;
        private $rewriteRules = array();
        private $autoDelete = array();
        private $autoDeleteExcludes = array();
        private $forceDeletes = array();
        private $phpSocket = null;
        private $phpSocketName = null;
        private $phpHTMLErrors = true;
        private $phpDisabledFunctions = array();
        private $resetClassObjects = false;
        private $resetClassNonObjects = false;
        private $shouldCompareObjects = false;
        private $resetClassObjectsDestroyDestructor = false;
        private $predefinedConstants = array();
        private $deletePredefinedConstantsAfterCodeCacheLoad = false;
        static private $defaultvHost = null;
        
        /**
        * Loads a vHost
        * 
        * @param string $name Name of the vHost that should be loaded
        * @return vHost
        */
        public function __construct($name) {
            $this->name = $name;
            
            // Set ID
            $this->id = self::$vHosts++;
            
            // Get configured settings
            $config = Config::get('vhosts.'.$this->name);
            
            if(!$config)
            	throw new \InvalidArgumentException('Unknown vHost specified');
            
            $this->documentRoot = $config['docroot'];
            
            // Check if document root exists and is a directory
            if(!file_exists($this->documentRoot) || !is_dir($this->documentRoot))
                throw new \Exception('Document root does not exist or is not a directory: '.$this->documentRoot);
                
            // Resolve exact path to docroot
            $this->documentRoot = realpath($this->documentRoot) . '/';
                                 
            $this->phpCodeCache = (array) $config['phpcache'];
            $this->phpCodeCacheExcludes = (array) $config['phpcacheexclude'];
            $this->phpWorkers = (int) $config['phpworkers'];
            $this->indexFiles = (array) $config['index'];
            $this->writeLimit = (int) $config['writelimit'];
            $this->allowDirectoryListings = (bool) $config['allowdirectorylistings'];
            $this->gzipMinimum = (int) $config['gzipmin'];
            $this->gzipLevel = (int) $config['gziplevel'];
            $this->phpWorkerLimit = (int) $config['phpworkerlimit'];
            $this->allowGZIP = (bool) $config['enablegzip'];
            $this->isDefault = (bool) $config['isdefault'];
            $this->phpInfoConfig = (bool) $config['phpinfopancake'];
            $this->phpInfovHosts = (bool) $config['phpinfopancakevhosts'];
            $this->onEmptyPage204 = (bool) $config['204onemptypage'];
            $this->phpHTMLErrors = (bool) $config['phphtmlerrors'];
            $this->phpDisabledFunctions = (array) $config['phpdisabledfunctions'];
            $this->resetClassObjects = (bool) $config['phpresetclassstaticobjects'];
            $this->resetClassNonObjects = (bool) $config['phpresetclassstaticnonobjects'];
            $this->shouldCompareObjects = (bool) $config['compareobjects'];
            $this->resetClassObjectsDestroyDestructor = (bool) $config['phpresetclassstaticobjectsdestroydestructors'];
            $this->predefinedConstants = (array) $config['phppredefinedconstants'];
            $this->deletePredefinedConstantsAfterCodeCacheLoad = (bool) $config['phpdeletepredefinedconstantsaftercodecacheload'];
            
            // Check for Hosts to listen on
            $this->listen = $config['listen'];
            if(count($this->listen) < 1 && !$this->isDefault)
                throw new \Exception('You need to specify at least one address to listen on');
            
            if($config['autodelete'])
                $this->autoDelete = (array) $config['autodelete'];
            else
                $this->autoDelete = true;
            $this->autoDeleteExcludes = (array) $config['excludedelete'];
            foreach((array) $config['forcedelete'] as $type => $deletes) {
                foreach((array) $deletes as $delete)
                    $this->forceDeletes[] = array('type' => $type, 'name' => $delete);
            }
            if($this->isDefault === true)
                self::$defaultvHost = $this;
                
            // Load rewrite rules
            foreach((array) $config['rewrite'] as $rewriteRule) {
                if(substr($rewriteRule['location'], 0, 1) != '/' && $rewriteRule['location'])
                    $rewriteRule['location'] = '/' . $rewriteRule['location'];
                $rewriteRule['location'] = strtolower($rewriteRule['location']);
                $this->rewriteRules[] = array(  'location' => $rewriteRule['location'],
                                                'if' => $rewriteRule['if'],
                                                'pattern' => $rewriteRule['pattern'],
                                                'replacement' => $rewriteRule['replace']);
            }
            
            // Load files and directories that need authentication
            if($config['auth']) {
            	require_once 'authenticationFile.class.php';
                foreach($config['auth'] as $authFile => $authFileConfig) {
                    if(!is_array($authFileConfig['authfiles']) || ($authFileConfig['type'] != 'basic' && $authFileConfig['type'] != 'digest')) {
                        trigger_error('Invalid authentication configuration for "'.$authFile.'"', \E_USER_WARNING);
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
                    if(!file_exists($this->documentRoot . $codeFile) || !is_readable($this->documentRoot . $codeFile)) {
                        unset($this->phpCodeCache[$id]);
                        throw new \Exception('Specified CodeCache-file does not exist or isn\'t readable: '.$codeFile);
                    }
                if(!$this->phpWorkers)
                    throw new \Exception('The amount of PHPWorkers must be greater or equal 1 if you want to use the CodeCache.');
            }
            
            // Spawn socket for PHPWorkers
            if($this->phpWorkers) {
                $this->phpSocketName = Config::get('main.tmppath') . mt_rand() . '_' . $this->name . '_socket';
                
                $this->phpSocket = socket_create(AF_UNIX, SOCK_SEQPACKET, 0);
                socket_bind($this->phpSocket, $this->phpSocketName);
                socket_listen($this->phpSocket, (int) $config['phpsocketbacklog']);
                
                chown($this->phpSocketName, Config::get('main.user'));
                chgrp($this->phpSocketName, Config::get('main.group'));
            }
        }
        
        /**
        * Checks whether a specified file requires authentication
        * 
        * @param string $filePath Path to the file, relative to the DocRoot of the vHost
        * @return mixed Returns false or array with realm and type
        */
        public function requiresAuthentication($filePath) {
            if(isset($this->authFiles[$filePath]))
                return $this->authFiles[$filePath];
            else if(isset($this->authDirectories[$filePath]))
                return $this->authDirectories[$filePath];
            else {
                while($filePath != '/') {
                    $filePath = dirname($filePath);
                    if(isset($this->authDirectories[$filePath]))
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
                    if(authenticationFile::get($authfile)->isValid($user, $password))
                        return true;
                }
            } else if($this->authDirectories[$filePath]) {
                foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
                    if(authenticationFile::get($authfile)->isValid($user, $password))
                        return true;
                }
            } else {
                while($filePath != '/') {
                    $filePath = dirname($filePath);
                    if($this->authDirectories[$filePath])
                        foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
                            if(authenticationFile::get($authfile)->isValid($user, $password))
                                return true;
                        }
                }
            }
            return false;
        }
        
        /**
        * Applies the vHost's rewrite rules to a URI
        * 
        * @param string $uri
        * @return string
        */
        public function rewrite($uri) {
            foreach($this->rewriteRules as $rule) {
                if($rule['location'] && $rule['if']) {
                    if(strpos(strtolower($uri), $rule['location']) !== 0 && preg_match($rule['if'], $uri))
                        $uri = preg_replace($rule['pattern'], $rule['replacement'], $uri);
                } else if($rule['location']) {
                    if(strpos(strtolower($uri), $rule['location']) !== false)
                        $uri = preg_replace($rule['pattern'], $rule['replacement'], $uri);
                } else if($rule['if']) {
                    if(preg_match($rule['if'], $uri))
                        $uri = preg_replace($rule['pattern'], $rule['replacement'], $uri);
                } else
                    $uri = preg_replace($rule['pattern'], $rule['replacement'], $uri);
            }
            //var_dump($uri);
            return $uri;
        }
        
        /**
        * Determines whether a specific type of PHP elements should be automatically deleted
        * 
        * @param string $type
        * @return bool
        */
        public function shouldAutoDelete($type) {
            return $this->autoDelete === true ? true : (bool) $this->autoDelete[$type];
        }
        
        /**
        * Determines whether a specific element should be excluded from automatic deletion
        * 
        * @param string $name
        * @param string $type
        * @return bool
        */
        public function isAutoDeleteExclude($name, $type) {
            return (bool) $this->autoDeleteExcludes[$type][$name];
        }
        
        /**
        * Returns PHP elements that should be deleted
        * 
        * @return array
        */
        public function getForcedDeletes() {
            return (array) $this->forceDeletes;
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
        * Returns the first host to listen on
        * 
        */
        public function getHost() {
            return $this->listen[0];
        }
        
        /**
        * @return bool
        * 
        */
        public function exposePancakeInPHPInfo() {
            return $this->phpInfoConfig;
        }
        
        /**
        * @return bool
        * 
        */
        public function exposePancakevHostsInPHPInfo() {
            return $this->phpInfovHosts;
        }
        
        /**
        * @return bool
        * 
        */
        public function send204OnEmptyPage() {
            return $this->onEmptyPage204;
        }
        
        /**
        * Returns the socket used by PHPWorkers to listen for requests
        * 
        * @return resource|null
        */
        public function getSocket() {
            return $this->phpSocket;
        }
        
        /**
        * Returns the address to the socket used by PHPWorkers to listen for requests
        * 
        * @return string
        */
        public function getSocketName() {
            return $this->phpSocketName;
        }
        
        /**
         * Should the SAPI error handler use HTML or plain text errors?
         * 
         * @return boolean
         */
        public function useHTMLErrors() {
        	return $this->phpHTMLErrors;
        }
        
        /**
         * Returns all functions that should be disabled inside the PHP-SAPI
         * 
         * @return array
         */
        public function getDisabledFunctions() {
        	return $this->phpDisabledFunctions;
        }
       
        /**
         * Returns true if objects in static class properties should be cleaned after finishing a request
         * 
         * @return boolean
         */
        public function shouldResetStaticClassObjectValues() {
        	return $this->resetClassObjects;
        }
        
        /**
         * Returns true if non-object-values in static class properties should be cleaned after finishing a request
         *
         * @return boolean
         */
        public function shouldResetStaticClassNonObjectValues() {
        	return $this->resetClassNonObjects;
        }
        
        /**
         * Returns true if object destructors should not be executed when destroying an object from a static class property
         * 
         * @return boolean
         */
        public function shouldDestroyDestructorOnObjectDestroy() {
        	return $this->resetClassObjectsDestroyDestructor;	
        }
        
        /**
         * Returns whether the request object should be checked for manipulation after being returned from the PHP-SAPI
         * 
         * @return boolean
         */
        public function shouldCompareObjects() {
        	return $this->shouldCompareObjects;
        }
        
        /**
         * Returns all constants that are automatically predefined by Pancake before loading this vHosts' CodeCache
         * 
         * @return array
         */
        public function getPredefinedConstants() {
        	return $this->predefinedConstants;
        }
        
        /**
         * Returns whether predefined constants should be deleted again after loading the CodeCache
         * 
         * @return boolean
         */
        public function predefineConstantsOnlyForCodeCache() {
        	return $this->deletePredefinedConstantsAfterCodeCacheLoad;
        }
        
        /**
        * Returns the instance of the default virtual host
        * 
        * @return vHost
        */
        public static function getDefault() {
            return self::$defaultvHost;
        }
    }
?>

<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* vHost.class.php                                              */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;
    
    /**
    * Represents a single virtual host in Pancake    
    */
    class vHost {
        private static $vHosts = 0;
        public $id = 0;
        public $name = null;
        public $documentRoot = null;
        public $listen = array();
        public $phpCodeCache = array();
        public $phpCodeCacheExcludes = array();
        public $phpWorkers = 0;
        public $phpWorkerLimit = 0;
        public $indexFiles = array();
        public $authDirectories = array();
        public $authFiles = array();
        public $writeLimit = 0;
        public $allowDirectoryListings = false;
        public $gzipMinimum = 0;
        public $gzipLevel = -1;
        public $allowGZIP = false;
        public $isDefault = false;
        public $phpInfoConfig = true;
        public $phpInfovHosts = true;
        public $onEmptyPage204 = true;
        public $rewriteRules = array();
        public $autoDelete = array();
        public $autoDeleteExcludes = array();
        public $forceDeletes = array();
        public $phpSocket = null;
        public $phpSocketName = null;
        public $phpHTMLErrors = true;
        public $phpDisabledFunctions = array();
        public $phpMaxExecutionTime = 0;
        public $resetClassObjects = false;
        public $resetClassNonObjects = false;
        public $resetFunctionObjects = false;
        public $resetFunctionNonObjects = false;
        public $shouldCompareObjects = false;
        public $resetObjectsDestroyDestructor = false;
        public $predefinedConstants = array();
        public $deletePredefinedConstantsAfterCodeCacheLoad = false;
        public $fixStaticMethodCalls = false;
        public $fastCGI = array();
        public $exceptionPageHandler = "";
        public $directoryPageHandler = "";
        static public $defaultvHost = null;
        
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
            $this->resetFunctionObjects = (bool) $config['phpresetfunctionstaticobjects'];
            $this->resetFunctionNonObjects = (bool) $config['phpresetfunctionstaticnonobjects'];
            $this->shouldCompareObjects = (bool) $config['compareobjects'];
            $this->resetObjectsDestroyDestructor = (bool) $config['phpresetobjectsdestroydestructors'];
            $this->predefinedConstants = (array) $config['phppredefinedconstants'];
            $this->deletePredefinedConstantsAfterCodeCacheLoad = (bool) $config['phpdeletepredefinedconstantsaftercodecacheload'];
            $this->phpMaxExecutionTime = (int) $config['phpmaxexecutiontime'];
            $this->fixStaticMethodCalls = (!$this->phpCodeCache) || ($this->phpCodeCache && $config['phpfixstaticmethodcalls'] === false) ? false : true;
            $this->fastCGI = (array) $config['fastcgi'];
            $this->exceptionPageHandler = $config['exceptionpagehandler'] && is_readable($config['exceptionpagehandler']) ? $config['exceptionpagehandler'] : getcwd() . '/php/exceptionPageHandler.php';
            $this->directoryPageHandler = $config['directorypagehandler'] && is_readable($config['directorypagehandler']) ? $config['directorypagehandler'] : getcwd() . '/php/directoryPageHandler.php';
            
            // Check for Hosts to listen on
            $this->listen = (array) $config['listen'];
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
                foreach($config['auth'] as $authFile => $authFileConfig) {
                    if(!is_array($authFileConfig['authfiles']) || ($authFileConfig['type'] != 'basic' && $authFileConfig['type'] != 'digest')) {
                        trigger_error('Invalid authentication configuration for "'.$authFile.'"', \E_USER_WARNING);
                        continue;
                    }
                    if(is_dir($this->documentRoot.$authFile)) {
                    	if(substr($authFile, -1, 1) == '/')
                    		$authFile = substr($authFile, 0, strlen($authFile) - 1);
                    	
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
        
//         /**
//         * Determines whether a specific type of PHP elements should be automatically deleted
//         * 
//         * @param string $type
//         * @return bool
//         */
//         public function shouldAutoDelete($type) {
//             return $this->autoDelete === true ? true : (bool) $this->autoDelete[$type];
//         }
        
//         /**
//         * Determines whether a specific element should be excluded from automatic deletion
//         * 
//         * @param string $name
//         * @param string $type
//         * @return bool
//         */
//         public function isAutoDeleteExclude($name, $type) {
//             return (bool) $this->autoDeleteExcludes[$type][$name];
//         }
        
//         /**
//         * Returns PHP elements that should be deleted
//         * 
//         * @return array
//         */
//         public function getForcedDeletes() {
//             return (array) $this->forceDeletes;
//         }
        
//         /**
//         * Returns the name of the vHost
//         * 
//         */
//         public function getName() {
//             return $this->name;
//         }
        
//         /**
//         * Get the document root of the vHost
//         * 
//         */
//         public function getDocumentRoot() {
//             return $this->documentRoot;
//         }
        
//         /**
//         * Get hosts the vHost listens on
//         * 
//         */
//         public function getListen() {
//             return $this->listen;
//         }
        
//         /**
//         * Get files loaded in the PHP-CodeCache of this vHost
//         * 
//         */
//         public function getCodeCacheFiles() {
//             return $this->phpCodeCache;
//         }
        
//         /**
//         * Get file-names allowed as directory-indexes
//         * 
//         */
//         public function getIndexFiles() {
//             return $this->indexFiles;
//         }
        
//         /**
//         * Get maximum size for a single write-action
//         * 
//         */
//         public function getWriteLimit() {
//             return $this->writeLimit;
//         }
        
//         /**
//         * Returns whether directory listings are allowed or not
//         * 
//         */
//         public function allowDirectoryListings() {
//             return $this->allowDirectoryListings;
//         }
        
//         /**
//         * Returns the minimum filesize for using GZIP-compression   
//         *                                                       
//         */
//         public function getGZIPMimimum() {
//             return $this->gzipMinimum;
//         }
        
//         /**
//         * Returns the level of GZIP-compression to use
//         * 
//         */
//         public function getGZIPLevel() {
//             return $this->gzipLevel;
//         }
        
//         /**
//         * Returns whether GZIP-compression can be used or not
//         * 
//         */
//         public function allowGZIPCompression() {
//             return $this->allowGZIP;
//         }
        
//         /**
//         * Returns the amount of PHP-workers the vHost is running
//         * 
//         */
//         public function getPHPWorkerAmount() {
//             return $this->phpWorkers;
//         }
        
//         /**
//         * Returns the unique ID of the vHost
//         * 
//         */
//         public function getID() {
//             return $this->id;
//         }
        
//         /**
//         * Returns the limit of requests a PHPWorker may process until it has to be restarted
//         * 
//         */
//         public function getPHPWorkerLimit() {
//             return $this->phpWorkerLimit;
//         }
        
//         /**
//         * Returns whether this vHost is the default vHost or not
//         * 
//         */
//         public function isDefault() {
//             return $this->isDefault;
//         }
        
//         /**
//         * Checks if the FileName (relative to DocumentRoot) should be excluded from CodeCache
//         * 
//         * @param string $fileName
//         */
//         public function isExcludedFile($fileName) {
//             return in_array($fileName, (array) $this->phpCodeCacheExcludes);
//         }
        
//         /**
//         * Returns the first host to listen on
//         * 
//         */
//         public function getHost() {
//             return $this->listen[0];
//         }
        
//         /**
//         * @return bool
//         * 
//         */
//         public function exposePancakeInPHPInfo() {
//             return $this->phpInfoConfig;
//         }
        
//         /**
//         * @return bool
//         * 
//         */
//         public function exposePancakevHostsInPHPInfo() {
//             return $this->phpInfovHosts;
//         }
        
//         /**
//         * @return bool
//         * 
//         */
//         public function send204OnEmptyPage() {
//             return $this->onEmptyPage204;
//         }
        
//         /**
//         * Returns the socket used by PHPWorkers to listen for requests
//         * 
//         * @return resource|null
//         */
//         public function getSocket() {
//             return $this->phpSocket;
//         }
        
//         /**
//         * Returns the address to the socket used by PHPWorkers to listen for requests
//         * 
//         * @return string
//         */
//         public function getSocketName() {
//             return $this->phpSocketName;
//         }
        
//         /**
//          * Should the SAPI error handler use HTML or plain text errors?
//          * 
//          * @return boolean
//          */
//         public function useHTMLErrors() {
//         	return $this->phpHTMLErrors;
//         }
        
//         /**
//          * Returns all functions that should be disabled inside the PHP-SAPI
//          * 
//          * @return array
//          */
//         public function getDisabledFunctions() {
//         	return $this->phpDisabledFunctions;
//         }
       
//         /**
//          * Returns true if objects in static class properties should be cleaned after finishing a request
//          * 
//          * @return boolean
//          */
//         public function shouldResetStaticClassObjectValues() {
//         	return $this->resetClassObjects;
//         }
        
//         /**
//          * Returns true if non-object-values in static class properties should be cleaned after finishing a request
//          *
//          * @return boolean
//          */
//         public function shouldResetStaticClassNonObjectValues() {
//         	return $this->resetClassNonObjects;
//         }
        
//         /**
//          * Returns true if objects in static function variables should be cleaned after finishing a request
//          *
//          * @return boolean
//          */
//         public function shouldResetStaticFunctionObjectValues() {
//         	return $this->resetFunctionObjects;
//         }
        
//         /**
//          * Returns true if non-object-values in static function variables should be cleaned after finishing a request
//          *
//          * @return boolean
//          */
//         public function shouldResetStaticFunctionNonObjectValues() {
//         	return $this->resetFunctionNonObjects;
//         }
        
//         /**
//          * Returns true if object destructors should not be executed when destroying an object from a static class property or a static variable inside a function
//          * 
//          * @return boolean
//          */
//         public function shouldDestroyDestructorOnObjectDestroy() {
//         	return $this->resetObjectsDestroyDestructor;	
//         }
        
//         /**
//          * Returns whether the request object should be checked for manipulation after being returned from the PHP-SAPI
//          * 
//          * @return boolean
//          */
//         public function shouldCompareObjects() {
//         	return $this->shouldCompareObjects;
//         }
        
//         /**
//          * Returns all constants that are automatically predefined by Pancake before loading this vHosts' CodeCache
//          * 
//          * @return array
//          */
//         public function getPredefinedConstants() {
//         	return $this->predefinedConstants;
//         }
        
//         /**
//          * Returns whether predefined constants should be deleted again after loading the CodeCache
//          * 
//          * @return boolean
//          */
//         public function predefineConstantsOnlyForCodeCache() {
//         	return $this->deletePredefinedConstantsAfterCodeCacheLoad;
//         }
        
//         /**
//          * Returns the maximum execution time for PHP scripts
//          *
//          * @return number
//          */
//         public function getMaxExecutionTime() {
//         	return $this->phpMaxExecutionTime;
//         }
        
//         /**
//          * Returns whether dt_fix_static_method_calls() should be enabled or not
//          * 
//          * @return boolean
//          */
//         public function shouldFixStaticMethodCalls() {
//         	return $this->fixStaticMethodCalls;
//         }
        
//         /**
//          * Returns the FastCGI instance for a given mime type, if any
//          *
//          */
//         public function getFastCGI($mimeType) {
//         	if(isset($this->fastCGI[$mimeType]))
//         		return $this->fastCGI[$mimeType];
//         }
        
//         /**
//          * Returns the exception page handler for this vHost
//          * 
//          * @return string
//          */
//         public function getExceptionPageHandler() {
//         	return $this->exceptionPageHandler;
//         }
        
//         /**
//          * Returns the directory page handler for this vHost
//          *
//          * @return string
//          */
//         public function getDirectoryPageHandler() {
//         	return $this->directoryPageHandler;
//         }
        
//         /**
//         * Returns the instance of the default virtual host
//         * 
//         * @return vHost
//         */
//         public static function getDefault() {
//             return self::$defaultvHost;
//         }
    }
?>

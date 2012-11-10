<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* vHostInterface.class.php                                     */
	/* 2012 Yussuf Khalil                                           */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/
	
	#.if 0
	namespace Pancake;
	
	if(PANCAKE !== true)
		exit;
	#.endif
	
	class vHostInterface {
        public $id = 0;
        public $name = "";
        public $documentRoot = "";
		#.if !/* .isDefined 'PHPWORKER' */ || #.isDefined 'EXPOSE_VHOSTS_IN_PHPINFO'
	        public $listen = array();
	        public $phpWorkers = 0;
	        public $indexFiles = array();
			public $allowDirectoryListings = false;
			public $gzipMinimum = 0;
			public $gzipLevel = -1;
			public $allowGZIP = false;
			public $writeLimit = 0;
			public $isDefault = false;
        #.endif
        #.ifndef 'PHPWORKER'
        	#.ifdef 'SUPPORT_AUTHENTICATION'
	        public $authDirectories = array();
	        public $authFiles = array();
	        #.endif
	        public $onEmptyPage204 = true;
	        #.ifdef 'SUPPORT_REWRITE'
	        public $rewriteRules = array();
	        #.endif
	        public $phpSocketName = "";
	        public $shouldCompareObjects = false;
	        #.ifdef 'SUPPORT_FASTCGI'
	        public $fastCGI = array();
	        #.endif
	        public $directoryPageHandler = "";
	        public static $defaultvHost = "";
	    #.endif
	    #.ifdef 'PHPWORKER'
	        public $phpWorkerLimit = 0;
	        public $phpCodeCache = array();
	        public $phpCodeCacheExcludes = array();
	        public $autoDelete = array();
	        public $autoDeleteExcludes = array();
	        public $forceDeletes = array();
	        public $phpInfoConfig = true;
	        public $phpInfovHosts = true;
	        public $phpSocket = 0;
	        public $phpHTMLErrors = true;
	        public $phpDisabledFunctions = array();
	        public $phpMaxExecutionTime = 0;
	        public $resetClassObjects = false;
	        public $resetClassNonObjects = false;
	        public $resetFunctionObjects = false;
	        public $resetFunctionNonObjects = false;
	        public $resetObjectsDestroyDestructor = false;
	        public $predefinedConstants = array();
	        public $deletePredefinedConstantsAfterCodeCacheLoad = false;
	        public $fixStaticMethodCalls = false;
	    #.endif
        public $exceptionPageHandler = "";
	        
	   	public function __construct(vHost $vHost) {
	   		foreach($vHost as $name => $value) {
	   			if(isset($this->$name))
	   				$this->$name = $value;
	   		}
	   		
	   		#.ifndef 'PHPWORKER'
		   		if($this->isDefault)
		   			self::$defaultvHost = $this;
	   		#.endif
	   	}
	   	
	   	#.ifndef 'PHPWORKER'
	   	#.ifdef 'SUPPORT_AUTHENTICATION'
	   	/**
	   	 * Checks whether a specified file requires authentication
	   	 *
	   	 * @param string $filePath Path to the file, relative to the DocRoot of the vHost
	   	 * @return mixed Returns false or array with realm and type
	   	 */
	   	public function requiresAuthentication($filePath) {
	   		if(substr($filePath, -1, 1) == '/' && strlen($filePath) > 1)
	   			$filePath = substr($filePath, 0, strlen($filePath) - 1);
	   		
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
	   		if(substr($filePath, -1, 1) == '/' && strlen($filePath) > 1)
	   			$filePath = substr($filePath, 0, strlen($filePath) - 1);
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
	   	#.endif
	   	
	   	#.ifdef 'SUPPORT_FASTCGI'
	   	/**
	   	 * Initializes the configured FastCGI upstream servers for this vHost
	   	 *
	   	 */
	   	public function initializeFastCGI() {
	   		$fCGIs = array();
	   		 
	   		foreach($this->fastCGI as $fastCGI) {
	   			$fCGIs[] = FastCGI::getInstance($fastCGI);
	   		}
	   		 
	   		$this->fastCGI = array();
	   		 
	   		foreach($fCGIs as $fastCGI) {
	   			foreach($fastCGI->getMimeTypes() as $mime)
	   				$this->fastCGI[$mime] = $fastCGI;
	   		}
	   	}
	   	#.endif
	   	#.endif
	}
	
?>
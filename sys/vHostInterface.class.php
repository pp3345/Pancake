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
	
	#.ifdef 'PHPWORKER'
		#.macro 'p' 'public'
	#.else
		#.macro 'p' 'public'
	#.endif
	
	class vHostInterface {
        /*.p*/ $id = 0;
        /*.p*/ $name = "";
        /*.p*/ $documentRoot = "";
		#.if !/* .isDefined 'PHPWORKER' */ || /* .isDefined 'EXPOSE_VHOSTS_IN_PHPINFO' */
	        /*.p*/ $listen = array();
	        /*.p*/ $phpWorkers = 0;
	        /*.p*/ $indexFiles = array();
			/*.p*/ $allowDirectoryListings = false;
			/*.p*/ $gzipMinimum = 0;
			/*.p*/ $gzipLevel = -1;
			/*.p*/ $allowGZIP = false;
			/*.p*/ $writeLimit = 0;
			/*.p*/ $isDefault = false;
        #.endif
        #.ifndef 'PHPWORKER'
	        public $authDirectories = array();
	        public $authFiles = array();
	        public $onEmptyPage204 = true;
	        public $rewriteRules = array();
	        public $phpSocketName = "";
	        public $shouldCompareObjects = false;
	        public $fastCGI = array();
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
        /*.p*/ $exceptionPageHandler = "";
	        
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

	   		return $uri;
	   	}
	   	
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
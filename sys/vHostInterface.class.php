<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* vHostInterface.class.php                                     */
	/* 2012 - 2013 Yussuf Khalil                                    */
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
	        public $authDirectories = array();
	        public $authFiles = array();
	        public $onEmptyPage204 = true;
	        public $rewriteRules = array();
	        #.ifdef 'SUPPORT_PHP'
	        public $phpSocketName = "";
	        #.endif
	        #.ifdef 'SUPPORT_FASTCGI'
	        public $fastCGI = array();
	        #.endif
	        public $AJP13 = "";
	        #.ifdef 'SUPPORT_DIRECTORY_LISTINGS'
	        public $directoryPageHandler = "";
	        #.endif
	        public $gzipStatic = false;
	        #.ifdef 'SUPPORT_GZIP_MIME_TYPE_LIMIT'
	        public $gzipMimeTypes = array();
	        #.endif
	        public static $defaultvHost = "";
	    #.endif
	    #.ifdef 'PHPWORKER'
	        public $phpWorkerLimit = 0;
	        #.ifdef 'SUPPORT_CODECACHE'
	        public $phpCodeCache = array();
	        public $phpCodeCacheExcludes = array();
	        #.endif
	        public $autoDeleteExcludes = array();
	        #.ifdef 'HAVE_FORCED_DELETES'
	        public $forceDeletes = array();
	        #.endif
	        public $phpSocket = 0;
	        public $phpDisabledFunctions = array();
	        public $phpMaxExecutionTime = 0;
	        public $predefinedConstants = array();
	        #.ifdef 'SUPPORT_CODECACHE'
	        public $deletePredefinedConstantsAfterCodeCacheLoad = false;
	        #.endif
	        public $fixStaticMethodCalls = false;
			#.ifdef 'HAVE_INI_SETTINGS'
			public $phpINISettings = array();
			#.endif
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
	   		if(isset($this->authFiles[$filePath])) {
	   			if($this->authFiles[$filePath]['type'] == "basic-crypted")
					$password = sha1($password);
	   			foreach($this->authFiles[$filePath]['authfiles'] as $authfile) {
	   				if(authenticationFile::get($authfile)->isValid($user, $password))
	   					return true;
	   			}
	   		} else if(isset($this->authDirectories[$filePath])) {
	   			if($this->authDirectories[$filePath]['type'] == "basic-crypted")
					$password = sha1($password);
	   			foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
	   				if(authenticationFile::get($authfile)->isValid($user, $password))
	   					return true;
	   			}
	   		} else {
	   			while($filePath != '/') {
	   				$filePath = dirname($filePath);
	   				if(isset($this->authDirectories[$filePath])) {
	   					$lpassword = $this->authDirectories[$filePath]['type'] == "basic-crypted" ? sha1($password) : $password;
		   				foreach($this->authDirectories[$filePath]['authfiles'] as $authfile) {
		   					if(authenticationFile::get($authfile)->isValid($user, $lpassword))
		   						return true;
		   				}
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

	   	#.ifdef 'SUPPORT_AJP13'
	   	public function initializeAJP13() {
	   		if($this->AJP13)
	   			$this->AJP13 = AJP13::getInstance($this->AJP13);
	   	}
	   	#.endif
	   	#.endif
	}

?>
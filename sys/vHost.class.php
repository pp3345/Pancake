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
        public $enabled = true;
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
        public $AJP13 = null;
        public $exceptionPageHandler = "";
        public $directoryPageHandler = "";
        public $gzipStatic = false;
        public $gzipMimeTypes = array();

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

            if(isset($config['enabled']) && !$config['enabled']) {
                $this->enabled = false;
                return;
            }

            $this->documentRoot = $config['docroot'];
            $this->AJP13 = (string) $config['ajp13'];

            // Check if document root exists and is a directory
            if((!file_exists($this->documentRoot) || !is_dir($this->documentRoot)) && !$this->AJP13)
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
            $this->gzipStatic = (bool) $config['gzipstatic'];
            $this->gzipMimeTypes = (array) $config['gzipmimetypes'];

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

            // Load rewrite rules
            foreach((array) $config['rewrite'] as $rewriteRule) {
                if(substr($rewriteRule['location'], 0, 1) != '/' && $rewriteRule['location'])
                    $rewriteRule['location'] = '/' . $rewriteRule['location'];
                //$rewriteRule['location'] = strtolower($rewriteRule['location']);
                $this->rewriteRules[] = $rewriteRule;
            }

            // Load files and directories that need authentication
            if($config['auth']) {
                foreach($config['auth'] as $authFile => $authFileConfig) {
                	$authFileConfig = arrayIndicesToLower($authFileConfig);

                    if(!is_array($authFileConfig['authfiles']) || ($authFileConfig['type'] != 'basic' && $authFileConfig['type'] != 'basic-crypted'/* && $authFileConfig['type'] != 'digest'*/)) {
                        trigger_error('Invalid authentication configuration for "'.$authFile.'"', \E_USER_WARNING);
                        continue;
                    }
                    if(is_dir($this->documentRoot . $authFile)) {
                    	if(substr($authFile, -1, 1) == '/' && strlen($authFile) > 1)
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

                $this->phpSocket = socket_create(\AF_UNIX, \SOCK_SEQPACKET, 0);
                socket_bind($this->phpSocket, $this->phpSocketName);
                socket_listen($this->phpSocket, (int) $config['phpsocketbacklog']);

                chown($this->phpSocketName, Config::get('main.user'));
                chgrp($this->phpSocketName, Config::get('main.group'));
            }
        }
    }
?>

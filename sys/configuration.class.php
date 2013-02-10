<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* configuration.class.php                                      */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

    namespace Pancake;

    if(PANCAKE !== true)
        exit;

    /**
    * Class for handling the configuration
    */
    class Config {
        const PATH = '../conf/config.yml';                      // Path to main configuration file
        const SKELETON_PATH = '../conf/skeleton.yml';           // Path to skeleton configuration
        const EXAMPLE_PATH = '../conf/config.example.yml';      // Path to example configuration
        const VHOST_EXAMPLE_PATH = '../conf/vhost.example.yml'; // Path to example vHost configuration
        const DEFAULT_VHOST_INCLUDE_DIR = '../conf/vhosts/';    // Path to default vHost include directory
        static private $configuration = array();
        static private $parser = null;

        /**
        * Loads the configuration
        */
        static public function load($file = null) {
        	self::$parser = new sfYamlParser;
        	$caseSensitivePaths = array('phppredefinedconstants', 'auth');

        	if(!$file && !file_exists(self::PATH) && file_exists(self::EXAMPLE_PATH)) {
        		out('It seems that Pancake is being started for the first time - Welcome to Pancake!');
        		out('Using example configuration');

        		copy(self::EXAMPLE_PATH, self::PATH);

        		if(!file_exists(self::DEFAULT_VHOST_INCLUDE_DIR) && file_exists(self::VHOST_EXAMPLE_PATH)) {
        			out('Loading example vHost');

        			if(!file_exists(self::DEFAULT_VHOST_INCLUDE_DIR))
        				mkdir(self::DEFAULT_VHOST_INCLUDE_DIR, 0644);

        			copy(self::VHOST_EXAMPLE_PATH, self::DEFAULT_VHOST_INCLUDE_DIR . 'default.yml');

        			self::loadFile(self::VHOST_EXAMPLE_PATH);
        		}

        		$firstStart = true;
        	}

        	self::$configuration = arrayIndicesToLower(self::$configuration, $caseSensitivePaths);

            if(!self::loadFile(self::SKELETON_PATH) || !self::loadFile($file ? $file : self::PATH)) {
                out('Couldn\'t load configuration');
                abort();
            }

            self::$configuration = arrayIndicesToLower(self::$configuration, $caseSensitivePaths);

            if(!file_exists(self::$configuration['main']['logging']['request']))
                touch(self::$configuration['main']['logging']['request']);
            if(!file_exists(self::$configuration['main']['logging']['system']))
                touch(self::$configuration['main']['logging']['system']);
            if(!file_exists(self::$configuration['main']['logging']['error']))
                touch(self::$configuration['main']['logging']['error']);
            if(!file_exists(self::$configuration['main']['tmppath']))
                touch(self::$configuration['main']['tmppath']);

            if(isset($firstStart)) {
            	if(!@posix_getpwnam(self::get('main.user'))) {
            		out('The default user was not found. Trying to create it automatically');
            		exec('useradd --no-create-home --shell /dev/null ' . self::get('main.user'), $x, $returnValue);
            		if($returnValue != 0) {
            			trigger_error('Failed to create user ' . self::get('main.user') . ' - Please create it yourself', \E_USER_ERROR);
            			abort();
            		}
            	}
            	if(!@posix_getgrnam(self::get('main.group'))) {
            		out('The default group was not found. Trying to create it automatically');
            		exec('groupadd ' . self::get('main.group'), $x, $returnValue);
            		if($returnValue != 0) {
            			trigger_error('Failed to create group ' . self::get('main.group') . ' - Please create it yourself', \E_USER_ERROR);
            			abort();
            		}
            	}
            }

            self::$configuration = arrayIndicesToLower(self::$configuration, $caseSensitivePaths);

            self::$configuration['main']['tmppath'] = realpath(self::$configuration['main']['tmppath']) . '/';
            self::$configuration['main']['logging']['request'] = realpath(self::$configuration['main']['logging']['request']);
            self::$configuration['main']['logging']['system'] = realpath(self::$configuration['main']['logging']['system']);
            self::$configuration['main']['logging']['error'] = realpath(self::$configuration['main']['logging']['error']);

            if(!is_dir(self::get('main.tmppath')) || !is_writable(self::get('main.tmppath'))) {
                trigger_error('The specified path for temporary files ("'.self::get('main.tmppath').'") is not a directory or is not writable', \E_USER_ERROR);
                abort();
            }

            self::$parser = null;
        }

        /**
        * Loads a single configuration file
        *
        * @param string $fileName
        */
        static private function loadFile($fileName) {
            if(is_dir($fileName)) {
                $dir = scandir($fileName);
                foreach($dir as $file) {
                    if($file != '.' && $file != '..')
                        self::loadFile($fileName . '/' . $file);
                }
                return true;
            }
            if(!($data = file_get_contents($fileName)) || !($config = self::$parser->parse($data))) {
                out('Failed to load configuration file ' . $fileName);
                return false;
            }

            if(isset($config['include'])) {
            	foreach($config['include'] as &$include) {
            		$include = realpath($include);
            		self::loadFile($include);
            	}
            }

            self::$configuration = array_merge(self::$configuration, $config);
            return true;
        }

        /**
        * Gets a single value from the configuration
        *
        * @param string $path YAML-path to requested configuration-value
        */
        static public function get($path, $defaultValue = null) {
            $path = explode('.', $path);
            $data = self::$configuration;
            foreach($path as $part)
                $data = $data[$part];
            return $data !== null ? $data : $defaultValue;
        }

        /**
         * Destroys the configuration
         */
        static public function workerDestroy() {
        	unset(self::$configuration['mime'], self::$configuration['include'], self::$configuration['vhosts'], self::$configuration['moody'], self::$configuration['fastcgi'], self::$configuration['ajp13']);
        }
    }
?>

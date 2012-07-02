<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* configuration.class.php                                      */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
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
        
        /**
        * Loads the configuration
        */
        static public function load() {
        	if(!file_exists(self::PATH) && file_exists(self::EXAMPLE_PATH)) {
        		out('It seems that Pancake is being started for the first time - Welcome to Pancake!', SYSTEM, false);
        		out('Using example configuration', SYSTEM, false);
        		
        		copy(self::EXAMPLE_PATH, self::PATH);
        		
        		if(!file_exists(self::DEFAULT_VHOST_INCLUDE_DIR) && file_exists(self::VHOST_EXAMPLE_PATH)) {
        			out('Loading example vHost', SYSTEM, false);
        			 
        			if(!file_exists(self::DEFAULT_VHOST_INCLUDE_DIR))
        				mkdir(self::DEFAULT_VHOST_INCLUDE_DIR, 0644);
        			 
        			copy(self::VHOST_EXAMPLE_PATH, self::DEFAULT_VHOST_INCLUDE_DIR . 'default.yml');
        			 
        			self::loadFile(self::VHOST_EXAMPLE_PATH);
        		}
        	}
        	
            if(!self::loadFile(self::SKELETON_PATH) || !self::loadFile(self::PATH)) {
                out('Couldn\'t load configuration');
                abort();
            }
            
            $includes = self::get('include');
            
            foreach((array) $includes as $include)
                self::loadFile($include);
                
            if(substr(self::get('main.tmppath'), -1, 1) != '/')
                self::$configuration['main']['tmppath'] = self::get('main.tmppath') . '/';
            if(!is_dir(self::get('main.tmppath'))) {
                trigger_error('The specified path for temporary files ("'.self::get('main.tmppath').'") is not a directory', \E_USER_WARNING);
                abort();
            }
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
            if(!($data = file_get_contents($fileName)) || !($config = yaml_parse($data))) {
                out('Failed to load configuration file ' . $fileName);
                return false;
            }
            self::$configuration = array_merge(self::$configuration, $config);
            return true;
        }
        
        /**
        * Gets a single value from the configuration
        * 
        * @param string $path YAML-path to requested configuration-value
        */
        static public function get($path) {
            $path = explode('.', $path);
            $data = self::$configuration;
            foreach($path as $part)
                $data = $data[$part];
            return $data;
        }
    }
?>

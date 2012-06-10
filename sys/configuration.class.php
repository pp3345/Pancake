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
        const PATH = '../conf/config.yml';            // Path to main configuration file
        const SKELETON_PATH = '../conf/skeleton.yml'; // Path to skeleton configuration
        static private $configuration = array();
        
        /**
        * Loads the configuration
        */
        static public function load() {
            if(!self::loadFile(self::SKELETON_PATH) || !self::loadFile(self::PATH)) {
                out('Couldn\'t load configuration');
                abort();
            }
            
            $includes = self::get('include');
            
            foreach((array) $includes as $include)
                self::loadFile($include);
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

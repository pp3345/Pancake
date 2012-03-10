<?php
  
    /****************************************************************/
    /* Pancake                                                    */
    /* configuration.class.php                                      */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;
    
    /**
    * Class for handling configuration
    */
    class Pancake_Config {
        const PATH = '/usr/local/Pancake/conf/config.yml';            // Path to main configuration file
        const SKELETON_PATH = '/usr/local/Pancake/conf/skeleton.yml'; // Path to skeleton configuration
        static private $configuration = array();
        
        /**
        * Loads the configuration
        */
        static public function load() {
            if(!($skeletonData = file_get_contents(self::SKELETON_PATH))
            || !($configData = file_get_contents(self::PATH))
            || !(self::$configuration = yaml_parse($skeletonData)) 
            || !(self::$configuration = Pancake_array_merge(self::$configuration, yaml_parse($configData)))) {
                Pancake_out('Couldn\'t load configuration', SYSTEM, false);
                return false;
            }
            $includes = self::get('include');
            if($includes)
                foreach($includes as $include) {
                    if(is_dir($include)) {
                        $directory = scandir($include);
                        foreach($directory as $file) {
                            if($file != '.' && $file != '..') {
                                if(!($includeData = file_get_contents($include.'/'.$file)) || !(self::$configuration = Pancake_array_merge(self::$configuration, yaml_parse($includeData))))
                                    Pancake_out('Couldn\'t load configuration-include: '.$file);
                            }
                        }
                        continue;
                    }
                    if(!($includeData = file_get_contents($include)) || !(self::$configuration = Pancake_array_merge(self::$configuration, yaml_parse($includeData))))
                        Pancake_out('Couldn\'t load configuration-include: '.$include);
                }
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

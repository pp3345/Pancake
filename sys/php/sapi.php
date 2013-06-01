<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* sapi.php                                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* See LICENSE file for license information                     */
    /****************************************************************/

    /* See php.net for further documentation on the SAPI-functions */

	#.if 0
    if(Pancake\PANCAKE !== true)
        exit;
    #.endif

    #.ifdef 'HAVE_FILTER_EXTENSION'
    function filter_input($type, $variable_name, $filter = /* .constant 'FILTER_DEFAULT' */, $options = /* .constant 'FILTER_FLAG_NONE' */) {
        // Create bitmask of flags
        if(is_array($options)) {
        	$flags = 0;

            if(isset($options['flags'])) {
                if(is_array($options['flags'])) {
                    foreach($options['flags'] as $option)
                        $flags |= $option;
                } else {
                    $flags = $options['flags'];
                }
            }
        } else
            $flags = $options;

        // Get variable
        switch($type) {
            case /* .constant 'INPUT_GET' */:
                $GET = Pancake\vars::$Pancake_request->getGETParams();
                if(!isset($GET[$variable_name]))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $GET[$variable_name];
                break;
            case /* .constant 'INPUT_POST' */:
                $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                if(!isset($POST[$variable_name]))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $POST[$variable_name];
                break;
            case /* .constant 'INPUT_COOKIE' */:
                $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                if(!isset($COOKIE[$variable_name]))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $COOKIE[$variable_name];
                break;
            case /* .constant 'INPUT_SERVER' */:
                $SERVER = Pancake\SAPIFetchSERVER();
                if(!isset($SERVER[$variable_name]))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $SERVER[$variable_name];
                break;
            case /* .constant 'INPUT_ENV' */:
                return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
        }

        return filter_var($var, $filter, $options);
    }

    function filter_input_array($type, $definition = null, $add_empty = true) {
    	$endArray = $data = array();

        if(is_array($definition)) {
            foreach($definition as $key => $options) {                    
                switch($type) {
                    case /* .constant 'INPUT_GET' */:
                        $GET = Pancake\vars::$Pancake_request->getGETParams();
                        if(!isset($GET[$key])) {
                            continue 2;
                        }
                        
                        $data[$key] = $GET[$key];
                        break;
                    case /* .constant 'INPUT_POST' */:
                        $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                        if(!isset($POST[$key])) {
                            continue 2;
                        }

                        $data[$key] = $POST[$key];
                        break;
                    case /* .constant 'INPUT_COOKIE' */:
                        $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                        if(!isset($COOKIE[$key])) {
                            continue 2;
                        }
                        
                        $data[$key] = $COOKIE[$key];
                        break;
                    case /* .constant 'INPUT_SERVER' */:
                        $SERVER = Pancake\SAPIFetchSERVER();
                        if(!isset($SERVER[$key])) {
                            continue 2;
                        }
                        
                        $data[$key] = $SERVER[$key];
                        break;
                    case /* .constant 'INPUT_ENV' */:
                        continue 2;
                }
            }
        }

        return filter_var_array($data, $definition, $add_empty);
    }

    function filter_has_var($type, $variable_name) {
        switch($type) {
            case /* .INPUT_GET */:
                return isset(Pancake\vars::$Pancake_request->getGETParams()[$variable_name]);
            case /* .INPUT_POST */:
                return isset(Pancake\vars::$Pancake_request->getPOSTParams()[$variable_name]);
            case /* .INPUT_COOKIE */:
                return isset(Pancake\vars::$Pancake_request->getCookies()[$variable_name]);
            case /* .INPUT_SERVER */:
                return isset(Pancake\SAPIFetchSERVER()[$variable_name]);
            case /* .INPUT_ENV */:
            default:
                return false;
        }
    }
    #.endif

?>
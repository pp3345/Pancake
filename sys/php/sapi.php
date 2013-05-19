<?php

    /****************************************************************/
    /* Pancake                                                      */
    /* sapi.php                                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
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

            foreach((array) $options['flags'] as $option)
                $flags |= $option;
        } else
            $flags = $options;

        // Get variable
        switch($type) {
            case /* .constant 'INPUT_GET' */:
                $GET = Pancake\vars::$Pancake_request->getGETParams();
                if(!array_key_exists($variable_name, $GET))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $GET[$variable_name];
                break;
            case /* .constant 'INPUT_POST' */:
                $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                if(!array_key_exists($variable_name, $POST))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $POST[$variable_name];
                break;
            case /* .constant 'INPUT_COOKIE' */:
                $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                if(!array_key_exists($variable_name, $COOKIE))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $COOKIE[$variable_name];
                break;
            case /* .constant 'INPUT_SERVER' */:
                $SERVER = Pancake\SAPIFetchSERVER();
                if(!array_key_exists($variable_name, $SERVER))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $SERVER[$variable_name];
                break;
            case /* .constant 'INPUT_ENV' */:
                if(!array_key_exists($variable_name, $_ENV))
                    return $flags & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null;
                $var = $_ENV[$variable_name];
                break;
        }

        return filter_var($var, $filter, $options);
    }

    function filter_input_array($type, $definition = null) {
    	$endArray = $data = $filterOptions = array();

        foreach((array) $definition as $key => $options) {
        	if(is_int($options))
        		$options = array('filter' => $options, 'flags' => 0);
        	if(!isset($options['flags']))
        		$options['flags'] = 0;

            switch($type) {
                case /* .constant 'INPUT_GET' */:
                    $GET = Pancake\vars::$Pancake_request->getGETParams();
                    if(!array_key_exists($key, $GET)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $GET[$key];
                    break;
                case /* .constant 'INPUT_POST' */:
                    $POST = Pancake\vars::$Pancake_request->getPOSTParams();
                    if(!array_key_exists($key, $POST)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $POST[$key];
                    break;
                case /* .constant 'INPUT_COOKIE' */:
                    $COOKIE = Pancake\vars::$Pancake_request->getCookies();
                    if(!array_key_exists($key, $COOKIE)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $COOKIE[$key];
                    break;
                case /* .constant 'INPUT_SERVER' */:
                    $SERVER = Pancake\SAPIFetchSERVER();
                    if(!array_key_exists($key, $SERVER)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $SERVER[$key];
                    break;
                case /* .constant 'INPUT_ENV' */:
                    if(!array_key_exists($key, $_ENV)) {
                        $endArray[$key] = ($options['flags'] & /* .constant 'FILTER_NULL_ON_FAILURE' */ ? false : null);
                        continue 2;
                    }
                    $var = $_ENV[$key];
                    break;
            }

            $data[$key] = $var;
            $filterOptions[$key] = $options;
        }

        return array_merge(filter_var_array($data, $filterOptions), $endArray);
    }

    function filter_has_var($type, $variable_name) {
        switch($type) {
            case /* .INPUT_GET */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getGETParams());
            case /* .INPUT_POST */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getPOSTParams());
            case /* .INPUT_COOKIE */:
                return array_key_exists($variable_name, Pancake\vars::$Pancake_request->getCookies());
            case /* .INPUT_SERVER */:
                return array_key_exists($variable_name, Pancake\SAPIFetchSERVER());
            case /* .INPUT_ENV */:
                return array_key_exists($variable_name, $_ENV);
            default:
                return false;
        }
    }
    #.endif

?>
<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* HTTPRequest.class.php                                        */
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/
    
	#.if 0
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;     
    #.endif                                                  
    
    class HTTPRequest {
        public $requestHeaders = array();
        public $answerHeaders = array();
        public $protocolVersion = '1.0';
        public $requestType = null;
        public $answerCode = 0;
        public $answerBody = null;
        public $requestFilePath = null;
        public $GETParameters = array();
    	#.ifdef 'PHPWORKER'
        public $POSTParameters = array();
        public $cookies = array();
    	#.endif
        public $setCookies = array();
        /**
         * @var vHostInterface
         */
        public $vHost = null;
        public $requestLine = null;
        public $rangeFrom = 0;
        public $rangeTo = 0;
        #.ifdef 'SUPPORT_GZIP'
        public $acceptedCompressions = array();
        #.endif
        public $requestURI = null;
        public $remoteIP = null;
       	public $remotePort = 0;
        public $localIP = null;
        public $localPort = 0;
        public $mimeType = null;
        public $uploadedFiles = array();
        public $uploadedFileTempNames = array();
        public $queryString = null;
        public $requestTime = 0;
        public $requestMicrotime = 0;
    	public $rawPOSTData = "";
        public static $answerCodes = array(
                                            100 => 'Continue',
                                            101 => 'Switching Protocols',
                                            102 => 'Processing',
                                            118 => 'Connection timed out',
                                            200 => 'OK',
                                            201 => 'Created',
                                            202 => 'Accepted',
                                            203 => 'Non-Authoritative Information',
                                            204 => 'No Content',
                                            205 => 'Reset Content',
                                            206 => 'Partial Content',
                                            207 => 'Multi-Status',
                                            300 => 'Multiple Choices',
                                            301 => 'Moved Permanently',
                                            302 => 'Found',
                                            303 => 'See Other',
                                            304 => 'Not Modified',
                                            305 => 'Use Proxy',
                                            307 => 'Temporary Redirect',
                                            400 => 'Bad Request',
                                            401 => 'Unauthorized',
                                            402 => 'Payment Required',
                                            403 => 'Forbidden',
                                            404 => 'Not Found',
                                            405 => 'Method Not Allowed',
                                            406 => 'Not Acceptable',
                                            407 => 'Proxy Authentication Required',
                                            408 => 'Request Timeout',
                                            409 => 'Conflict',
                                            410 => 'Gone',
                                            411 => 'Length Required',
                                            412 => 'Precondition Failed',
                                            413 => 'Request Entity Too Large',
                                            414 => 'Request-URI Too Long',
                                            415 => 'Unsupported Media Type',
                                            416 => 'Requested Range Not Satisfiable',
                                            417 => 'Expectation Failed',
                                            418 => 'I\'m a Pancake',
                                            421 => 'There are too many connections from your internet address',
                                            422 => 'Unprocessable Entity',
                                            423 => 'Locked',
                                            424 => 'Failed Dependency',
                                            500 => 'Internal Server Error',
                                            501 => 'Not Implemented',
                                            502 => 'Bad Gateway',
                                            503 => 'Service Unavailable',
                                            504 => 'Gateway Timeout',
                                            505 => 'HTTP Version not supported',
                                            506 => 'Variant Also Negotiates',
                                            507 => 'Insufficient Storage',
                                            509 => 'Bandwith Limit Exceeded',
                                            510 => 'Not Extended');
        
        #.ifndef 'PHPWORKER'
        /**
        * Creates a new HTTPRequest-object
        * 
        * @param string $remoteIP
        * @param int $remotePort
        * @param string $localIP
        * @param int $localPort
        * @return HTTPRequest
        */
        public function __construct($remoteIP = null, $remotePort = null, $localIP = null, $localPort = null) {
            $this->remoteIP = $remoteIP;
            $this->remotePort = $remotePort;
            $this->localIP = $localIP;
            $this->localPort = $localPort;
            $this->vHost = vHostInterface::$defaultvHost;
        }
        
        /**
        * Initialize request
        *  
        * @param string $requestHeader Headers received from the client
        * @throws invalidHTTPRequestException
        */
        public function init($requestHeader) { 
            // Get single header lines
            $requestHeaders = explode("\r\n", $requestHeader);
            
            // Split first line
            $firstLine = explode(" ", $requestHeaders[0]);
            
            $this->requestLine = $requestHeaders[0];
            
            #.if true === #.call 'Pancake\Config::get' 'main.exposepancake'
            // HyperText CoffeePot Control Protocol :-)
            if($firstLine[0] == 'BREW' || $firstLine[0] == 'WHEN' || $firstLine[2] == 'HTCPCP/1.0')
                throw new invalidHTTPRequestException('No coffee here. I\'m a Pancake. Try again at 1.3.3.7', 418, $requestHeader);
            #.endif
            
            // Check protocol version
            if(strtoupper($firstLine[2]) == 'HTTP/1.1')
                $this->protocolVersion = '1.1';
            else if(strtoupper($firstLine[2]) == 'HTTP/1.0')
                $this->protocolVersion = '1.0';
            else
                throw new invalidHTTPRequestException('Unsupported protocol: ' . $firstLine[2], strpos($firstLine[2], 'HTTP') !== false ? 505 : 400, $requestHeader);
            unset($requestHeaders[0]);
            
            // Check request method
            if($firstLine[0] != 'GET' && $firstLine[0] != 'POST' && $firstLine[0] != 'HEAD' && $firstLine[0] != 'OPTIONS' && $firstLine[0] != 'TRACE')
                throw new invalidHTTPRequestException('Invalid request method: ' . $firstLine[0], 501, $requestHeader);
            $this->requestType = $firstLine[0];
            
            // Check if request method is allowed
            #.if /* .call 'Pancake\Config::get' 'main.allowhead' */ !== true || /* .call 'Pancake\Config::get' 'main.allowtrace' */ !== true || true !== #.call 'Pancake\Config::get' 'main.allowoptions'
            	#.if true !== #.call 'Pancake\Config::get' 'main.allowhead'
            		if($this->requestType == 'HEAD'
            		#.def 'RTYPE_FORBIDDEN' true
            	#.endif
            	#.if true !== #.call 'Pancake\Config::get' 'main.allowtrace'
            		#.ifdef 'RTYPE_FORBIDDEN'
            			|| $this->requestType == 'TRACE'
            		#.else
            			if($this->requestType == 'TRACE'
            			#.def 'RTYPE_FORBIDDEN' true
            		#.endif
            	#.endif
            	#.if true !== #.call 'Pancake\Config::get' 'main.allowoptions'
            		#.ifdef 'RTYPE_FORBIDDEN'
            			|| $this->requestType == 'OPTIONS'
            		#.else
            			if($this->requestType == 'OPTIONS'
            		#.endif
            	#.endif
            	)
                throw new invalidHTTPRequestException('Disallowed request method: '.$this->requestType, 405, $requestHeader); 
            #.endif
            
            // Read Headers
            foreach($requestHeaders as $header) {
                if(trim($header) == null)
                    continue;
                $header = explode(':', $header, 2);
                $this->requestHeaders[$header[0]] = trim($header[1]);
            }
            
            // Check if Content-Length is given and not too large on POST
            if($this->requestType == 'POST') {
                if($this->getRequestHeader('Content-Length') > /* .constant 'POST_MAX_SIZE' */)
                    throw new invalidHTTPRequestException('The uploaded content is too large.', 413, $requestHeader);
                if($this->getRequestHeader('Content-Length') === null)
                    throw new invalidHTTPRequestException('Your request can\'t be processed without a given Content-Length', 411, $requestHeader);
            }
            
            #.if #.call 'Pancake\Config::get' 'main.allowtrace'
	            // Enough information for TRACE gathered
	            if($this->requestType == 'TRACE')
	                return;
	        #.endif
            
            // Check for Host-Header
            if(!$this->getRequestHeader('Host') && $this->protocolVersion == '1.1')
                throw new invalidHTTPRequestException('Missing required header: Host', 400, $requestHeader);
            
            // Search for vHost
            global $Pancake_vHosts;
            if(isset($Pancake_vHosts[$this->getRequestHeader('Host')]))
                $this->vHost = $Pancake_vHosts[$this->getRequestHeader('Host')];
            
            #.ifdef 'SUPPORT_REWRITE'
            foreach($this->vHost->rewriteRules as $rule) {
            	if((isset($rule['location']) && strpos($firstLine[1], $rule['location']) !== 0)
            	|| (isset($rule['if']) && !preg_match($rule['if'], $firstLine[1]))
            	|| (isset($rule['precondition']) && $rule['precondition'] == 404 && file_exists($this->vHost->documentRoot . $firstLine[1]))
            	|| (isset($rule['precondition']) && $rule['precondition'] == 403 && (!file_exists($this->vHost->documentRoot . $firstLine[1]) || is_readable($this->vHost->documentRoot . $firstLine[1])))
            	)
            		continue;

            	if(isset($rule['headers'])) {
            		foreach($rule['headers'] as $headerName => $headerValue)
            			$this->setHeader($headerName, $headerValue);
            	}
            	
            	if(isset($rule['pattern']) && isset($rule['replacement']))
            		$firstLine[1] = preg_replace($rule['pattern'], $rule['replacement'], $firstLine[1]);
            	else if(isset($rule['destination'])) {
            		$this->setHeader('Location', $rule['destination']);
            		throw new invalidHTTPRequestException('Redirecting...', 301/*, $requestHeader*/);
            	} else if(isset($rule['exception']) && is_numeric($rule['exception']))
            		throw new invalidHTTPRequestException(isset($rule['exceptionmessage']) ? $rule['exceptionmessage'] : 'The server was unable to process your request', $rule['exception'], $requestHeader);
            }
            #.endif
            
            $this->requestURI = $firstLine[1];
           	
            // Split address from query string
            $path = explode('?', $firstLine[1], 2);
            $this->requestFilePath = $path[0];
            $this->queryString = $path[1];
            
            // Check if path begins with http://
            if(strtolower(substr($this->requestFilePath, 0, 7)) == 'http://')
            	$this->requestFilePath = substr($this->requestFilePath, strpos($this->requestFilePath, '/', 7));
            
            // Check if path begins with /
            if(substr($this->requestFilePath, 0, 1) != '/')
                $this->requestFilePath = '/' . $this->requestFilePath;
            
            // Do not allow requests to lower paths
            if(strpos($this->requestFilePath, '../'))
                throw new invalidHTTPRequestException('You are not allowed to access the requested file: '.  $this->requestFilePath, 403, $requestHeader);
            
            // Check for index-files
            if(is_dir($this->vHost->documentRoot . $this->requestFilePath)) {
            	if(substr($this->requestFilePath, -1, 1) != '/' && $this->requestType == 'GET') {
            		$this->setHeader('Location', 'http://' . $this->getRequestHeader('Host') . $this->requestFilePath . '/' . $this->queryString);
            		throw new invalidHTTPRequestException('Redirecting...', 301);
            	}
            	
                foreach($this->vHost->indexFiles as $file)
                    if(file_exists($this->vHost->documentRoot . $this->requestFilePath . $file)) {
                        $this->requestFilePath .= $file;
                        goto checkRead;
                    }
                // No index file found, check if vHost allows directory listings
                if($this->vHost->allowDirectoryListings !== true)
                    throw new invalidHTTPRequestException('You\'re not allowed to view the listing of the requested directory: ' . $this->requestFilePath, 403, $requestHeader);
            }

            checkRead:
            
            #.ifdef 'SUPPORT_AJP13'
            if(!$this->vHost->AJP13) {
            #.endif
	            // Check if requested file exists and is accessible
	            if(!file_exists($this->vHost->documentRoot . $this->requestFilePath))
	                throw new invalidHTTPRequestException('File does not exist: ' . $this->requestFilePath, 404, $requestHeader);
	            if(!is_readable($this->vHost->documentRoot . $this->requestFilePath))
	                throw new invalidHTTPRequestException('You\'re not allowed to access the requested file: ' . $this->requestFilePath, 403, $requestHeader);
            #.ifdef 'SUPPORT_AJP13'
            }
            #.endif
            
            // Check if requested path needs authentication
            #.ifdef 'SUPPORT_AUTHENTICATION'
            if($authData = $this->vHost->requiresAuthentication($this->requestFilePath)) {
                if($this->getRequestHeader('Authorization')) {
                    //if($authData['type'] == 'basic') {
                        $auth = explode(" ", $this->getRequestHeader('Authorization'));
                        $userPassword = explode(":", base64_decode($auth[1]));
                        if($this->vHost->isValidAuthentication($this->requestFilePath, $userPassword[0], $userPassword[1]))
                            goto valid;
                    //} else {
                         
                    //}
                }
                //if($authData['type'] == 'basic') {
                    $this->setHeader('WWW-Authenticate', 'Basic realm="'.$authData['realm'].'"');
                    throw new invalidHTTPRequestException('You need to authorize in order to access this file.', 401, $requestHeader);
                //}
            }
            
            valid:
            #.endif
            
            // Check for If-Unmodified-Since
            if($this->getRequestHeader('If-Unmodified-Since')) {
                if(filemtime($this->vHost->documentRoot . $this->requestFilePath) != strtotime($this->getRequestHeader('If-Unmodified-Since')))
                    throw new invalidHTTPRequestException('File was modified since requested time.', 412, $requestHeader);
            }
            
            #.ifdef 'SUPPORT_GZIP'
            // Check for accepted compressions
            if($this->getRequestHeader('Accept-Encoding')) {
                $accepted = explode(',', $this->getRequestHeader('Accept-Encoding'));
                foreach($accepted as $format) {
                    $format = strtolower(trim($format));
                    $this->acceptedCompressions[$format] = true;
                }
            }
            #.endif
            
            // Check for Range-header
            if($this->getRequestHeader('Range')) {
                preg_match('~([0-9]+)-([0-9]+)?~', $this->getRequestHeader('Range'), $range);
                $this->rangeFrom = $range[1];
                $this->rangeTo = $range[2];
            }
            
            // Get MIME-type of the requested file
            $this->mimeType = MIME::typeOf($this->vHost->documentRoot . $this->requestFilePath);
            
            $this->requestTime = time();
            $this->requestMicrotime = microtime(true);
            
            // Set default host
            if(!$this->getRequestHeader('Host'))
                $this->requestHeaders['Host'] = $this->vHost->listen[0];                  
        }
        #.endif
        
        #.ifdef 'PHPWORKER'
        /**
        * Processes the POST request body
        * 
        * @param string $postData The request body received from the client
        */
        public function readPOSTData($postData) {
            // Check for url-encoded parameters
            if(strpos($this->getRequestHeader('Content-Type', false), 'application/x-www-form-urlencoded') !== false) {
                // Split POST-parameters
                $post = explode('&', $postData);

                // Read POST-parameters
                foreach($post as $param) {
                    if($param == null)
                        break;
                    $param = explode('=', $param, 2);
                    $param[0] = urldecode($param[0]);
                    $param[1] = urldecode($param[1]);
                    
                    if(strpos($param[0], '[') < strpos($param[0], ']')) {
                        preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $param[0], $parts);
                        
                        $paramDefinition = '$this->POSTParameters[$parts[1][0]]';
                        foreach((array) $parts[2] as $index => $arrayKey) {
                            if($arrayKey == null)
                                $paramDefinition .= '[]';
                            else
                                $paramDefinition .= '[$parts[2]['.$index.']]';
                        }
                        
                        $paramDefinition .= ' = $param[1];';
                        eval($paramDefinition);
                    } else
                        $this->POSTParameters[$param[0]] = $param[1];
                }
            // Check for multipart-data
            } else if(strpos($this->getRequestHeader('Content-Type', false), 'multipart/form-data') !== false) {
                // Get boundary string that splits the dispositions
                preg_match('~boundary=(.*)~', $this->getRequestHeader('Content-Type'), $boundary);
                if(!($boundary = $boundary[1]))
                    return false;
                
                // For some strange reason the actual boundary string is -- + the specified boundary string
                $postData = str_replace("\r\n--" . $boundary . "--\r\n", null, $postData);
                
                $dispositions = explode("\r\n--" . $boundary, $postData);
                
                // The first disposition will have a boundary string at its beginning
                $disposition[0] = substr($disposition[0], strlen('--' . $boundary . "\r\n"));

                $paramDefinitions = array();
                
                foreach($dispositions as $disposition) {
                    $dispParts = explode("\r\n\r\n", $disposition, 2);
                    preg_match('~Content-Disposition: form-data;[ ]?name="(.*?)";?[ ]?(?:filename="(.*?)")?(?:\r\n)?(?:Content-Type: (.*))?~', $dispParts[0], $data);
                    // [ 0 => string, 1 => name, 2 => filename, 3 => Content-Type ]
                    if(isset($data[2]) && isset($data[3])) {
                        $tmpFileName = tempnam(/* .call 'Pancake\Config::get' 'main.tmppath' */, 'UPL');
                        file_put_contents($tmpFileName, $dispParts[1]);
                        
                        $dataArray = array(
                                            'name' => $data[2],
                                            'type' => $data[3],
                                            'error' => /* .constant 'UPLOAD_ERR_OK' */,
                                            'size' => strlen($dispParts[1]),
                                            'tmp_name' => $tmpFileName);
                        
                        if(strpos($data[1], '[') < strpos($data[1], ']')) {
                            preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $data[1], $parts);
                            
                            $paramDefinition = '$file[$parts[1][0]]';
                            foreach((array) $parts[2] as $index => $arrayKey) {
                                if($arrayKey)
                                    $paramDefinition .= '[$parts[2]['.$index.']]';
                                else
                                    $multi = true;
                            }
                            
                            if($multi) {
                                $paramDefinitions[$paramDefinition]++;
                                $dataArray = array( 'name' => array( $paramDefinitions[$paramDefinition] - 1 => $data[2]),
                                                    'type' => array( $paramDefinitions[$paramDefinition] - 1 => $data[3]),
                                                    'error' => array( $paramDefinitions[$paramDefinition] - 1 => /* .constant 'UPLOAD_ERR_OK' */),
                                                    'size' => array( $paramDefinitions[$paramDefinition] - 1 => strlen($dispParts[1])),
                                                    'tmp_name' => array( $paramDefinitions[$paramDefinition] - 1 => $tmpFileName));
                            }
                            
                            $paramDefinition .= ' = $dataArray;';
                            eval($paramDefinition);
                            
                            $this->uploadedFiles = array_merge($file, $this->uploadedFiles);
                        } else
                            $this->uploadedFiles[$data[1]] = $dataArray;  
                             
                        $this->uploadedFileTempNames[] = $tmpFileName;
                    } else {
                        if(strpos($data[1], '[') < strpos($data[1], ']')) {
                            preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $data[1], $parts);
                            
                            $paramDefinition = '$this->POSTParameters[$parts[1][0]]';
                            foreach((array) $parts[2] as $index => $arrayKey) {
                                if($arrayKey == null)
                                    $paramDefinition .= '[]';
                                else
                                    $paramDefinition .= '[$parts[2]['.$index.']]';
                            }
                            
                            $paramDefinition .= ' = $dispParts[1];';
                            eval($paramDefinition);
                        } else
                            $this->POSTParameters[$data[1]] = $dispParts[1];
                    }
                    
                    unset($multi);
                }
            }
            return true;
        }
        #.endif
        
        /**
        * Set answer on invalid request
        * 
        * @param invalidHTTPRequestException $exception
        */
        public function invalidRequest(invalidHTTPRequestException $exception) {
        	$requestObject = $this;
        	
        	$this->answerCode = $exception->getCode();
        	$this->setHeader('Content-Type', MIME::typeOf($this->vHost->exceptionPageHandler));
        	
        	ob_start();
        	
        	if(!include($this->vHost->exceptionPageHandler))
        		include 'php/exceptionPageHandler.php';
        	
        	$this->answerBody = ob_get_clean();
        }
       
        #.ifndef 'PHPWORKER'
        /**
        * Build answer headers
        *  
        */
        public function buildAnswerHeaders() {
        	#.if #.call 'Pancake\Config::get' 'main.allowtrace'
            // Check for TRACE
            if($this->requestType == 'TRACE') {
                $answer = $this->requestLine . "\r\n";
                $answer .= $this->requestHeaders . "\r\n";
                return $answer;
            }
            #.endif
            
            // Set answer code if not set
            if(!$this->answerCode)
                (!$this->getAnswerHeader('Content-Length') && !$this->answerBody && $this->vHost->onEmptyPage204) ? $this->answerCode = 204 : $this->answerCode = 200;
            // Set Connection-Header
            if($this->answerCode >= 200 && $this->answerCode < 400 && strtolower($this->getRequestHeader('Connection')) == 'keep-alive')
                $this->setHeader('Connection', 'keep-alive');
            else
                $this->setHeader('Connection', 'close');
            // Add Server-Header
            #.if true === #.call 'Pancake\Config::get' 'main.exposepancake'
                $this->setHeader('Server', /* .eval "return 'Pancake/' . Pancake\VERSION;" false */);
            #.endif
            // Set cookies
            foreach($this->setCookies as $cookie)
                $setCookie .= ($setCookie) ? "\r\nSet-Cookie: " . $cookie : $cookie;
            
            if(isset($setCookie))
                $this->setHeader('Set-Cookie', $setCookie);
            // Set Content-Length
            if(!$this->getAnswerHeader('Content-Length'))
                $this->setHeader('Content-Length', strlen($this->answerBody));
            // Set Content-Type if not set
            if(!$this->getAnswerHeader('Content-Type', false) && $this->getAnswerHeader('Content-Length'))  
                $this->setHeader('Content-Type', 'text/html');                                              
            // Set Date
            if(!$this->getAnswerHeader('Date'))
                $this->setHeader('Date', date('r'));
            
            // Build Answer
            $answer = 'HTTP/' . $this->protocolVersion . ' ' . $this->answerCode . ' ' . self::$answerCodes[$this->answerCode] . "\r\n";
            $answer .= $this->getAnswerHeaders();
            $answer .= "\r\n";
            
            return $answer;
        }
        #.endif
        
        /**
        * Set answer header
        * 
        * @param string $headerName
        * @param string $headerValue
        * @param boolean $replace
        */
        public function setHeader($headerName, $headerValue, $replace = true) {
            if($replace) {
            	if(isset($this->answerHeaders[$headerName]))
                	unset($this->answerHeaders[$headerName]);
                $this->answerHeaders[$headerName] = $headerValue;
            } else {
                if(isset($this->answerHeaders[$headerName]) && $value = $this->answerHeaders[$headerName] && !is_array($this->answerHeaders[$headerName])) {
                    unset($this->answerHeaders[$headerName]);
                    $this->answerHeaders[$headerName] = array($value);
                }
                $this->answerHeaders[$headerName][] = $headerValue;
            }
            return true;
        }
        
        #.ifdef 'PHPWORKER'
        /**
        * Sets a cookie. Parameters similar to PHPs function setcookie()
        * 
        */
        public function setCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false, $raw = false) {
            $cookie = $name.'='.($raw ? $value : urlencode($value));
            if($expire)
                $cookie .= '; Expires='.date('r', $expire);    // RFC 2822 Timestamp
            if($path)
                $cookie .= '; Path='.$path;
            if($domain)
                $cookie .= '; Domain='.$domain;
            if($secure)
                $cookie .= '; Secure';
            if($httpOnly)
                $cookie .= '; HttpOnly';
            $this->setCookies[] = $cookie;
            return true;
        }
        
        /**
        * Creates the $_SERVER-variable
        * 
        * @return array $_SERVER
        */
        public function createSERVER() {
        	$appendSlash = "";
        	
            if(is_dir($this->vHost->documentRoot . $this->requestFilePath) && substr($this->requestFilePath, -1, 1) != '/')
                $appendSlash = '/';
            
            $_SERVER['REQUEST_TIME'] = $this->requestTime;
            $_SERVER['REQUEST_TIME_FLOAT'] = $this->requestMicrotime;
            $_SERVER['USER'] = Config::get('main.user');
            $_SERVER['REQUEST_METHOD'] = $this->requestType;
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/' . $this->protocolVersion;
            $_SERVER['SERVER_SOFTWARE'] = 'Pancake/' . VERSION;
            $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['DOCUMENT_URI'] = $this->requestFilePath . $appendSlash;
            $_SERVER['REQUEST_URI'] = $this->requestURI;
            $_SERVER['SCRIPT_FILENAME'] = (substr($this->vHost->documentRoot, -1, 1) == '/' ? substr($this->vHost->documentRoot, 0, strlen($this->vHost->documentRoot) - 1) : $this->vHost->documentRoot) . $this->requestFilePath . $appendSlash;
            $_SERVER['REMOTE_ADDR'] = $this->remoteIP;
            $_SERVER['REMOTE_PORT'] = $this->remotePort;
            $_SERVER['QUERY_STRING'] = $this->queryString;         
            $_SERVER['DOCUMENT_ROOT'] = $this->vHost->documentRoot;
            $_SERVER['SERVER_NAME'] = $this->getRequestHeader('Host') ? $this->getRequestHeader('Host') : $this->vHost->listen[0];
            $_SERVER['SERVER_ADDR'] = $this->localIP;
            $_SERVER['SERVER_PORT'] = $this->localPort;

            foreach($this->requestHeaders as $name => $value)
                $_SERVER['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
            
            return $_SERVER;
        }
        #.endif
        
        /**
        * Get the value of a single Request-Header
        * 
        * @param string $headerName
        * @param bool $caseSensitive
        * @return mixed value of the Header
        */
        public function getRequestHeader($headerName, $caseSensitive = true) {
            if($caseSensitive || isset($this->requestHeaders[$headerName]))
            	return isset($this->requestHeaders[$headerName]) ? $this->requestHeaders[$headerName] : null;
            else {
            	$headerName = strtolower($headerName);
            	foreach($this->requestHeaders as $name => $value) {
            		if(strtolower($name) == $headerName)
            			return $this->requestHeaders[$name];
            	}
            }
        }
        
        #.ifndef 'PHPWORKER'
        /**
        * Get formatted RequestHeaders
        * 
        */
        public function getRequestHeaders() {
            foreach($this->requestHeaders as $headerName => $headerValue) {
                $headers .= $headerName.': '.$headerValue."\r\n";
            }
            return $headers;
        }
        #.endif
        
        /**
        * Get formatted AnswerHeaders
        * 
        */
        public function getAnswerHeaders() {
        	$headers = "";
        	
            foreach($this->answerHeaders as $headerName => $headerValue) {
                if(is_array($headerValue)) {
                    foreach($headerValue as $value)
                        $headers .= $headerName . ': ' . $value . "\r\n";
                } else
                    $headers .= $headerName . ': ' . $headerValue . "\r\n";
            }
            return $headers;
        }
        
        /**
        * Returns all AnswerHeaders as an array
        * 
        */
        public function getAnswerHeadersArray() {
        	$headers = array();
        	
            foreach($this->answerHeaders as $headerName => $headerValue) {
                if(is_array($headerValue)) {
                    foreach($headerValue as $value)
                        $headers[] = $headerName . ': ' . $value;
                } else
                    $headers[] = $headerName . ': ' . $headerValue;
            }
            return $headers;
        }
        
        /**
        * Get a single AnswerHeader
        * 
        * @param string $headerName
        * @param boolean $caseSensitive
        */
        public function getAnswerHeader($headerName, $caseSensitive = true) {
            if($caseSensitive || isset($this->answerHeaders[$headerName]))
                return $this->answerHeaders[$headerName];
            else {
                $headerName = strtolower($headerName);
                foreach($this->answerHeaders as $name => $value) {
                    if(strtolower($name) == $headerName) 
                        return $this->answerHeaders[$name];
                }
            }
        }
        
        #.if /* .call 'ini_get' 'expose_php' */ || /* .isDefined 'PHPWORKER' */ || Pancake\DEBUG_MODE === true
        /**
        * Returns all GET-parameters of this request
        * 
        */
        public function getGETParams() {
        	if(!$this->GETParameters && $this->queryString) {
        		// Split GET-parameters
        		$get = explode('&', $this->queryString);
        		
        		// Read GET-parameters
        		foreach($get as $param) {
        			if($param == null)
        				break;
        			$param = explode('=', $param, 2);
        			$param[0] = urldecode($param[0]);
        			$param[1] = urldecode($param[1]);
        		
        			if(strpos($param[0], '[') < strpos($param[0], ']')) {
        				// Split array dimensions
        				preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $param[0], $parts);
        		
        				// Build and evaluate parameter definition
        				$paramDefinition = '$this->GETParameters[$parts[1][0]]';
        				foreach((array) $parts[2] as $index => $arrayKey) {
        					if($arrayKey == null)
        						$paramDefinition .= '[]';
        					else
        						$paramDefinition .= '[$parts[2]['.$index.']]';
        				}
        		
        				$paramDefinition .= ' = $param[1];';
        				eval($paramDefinition);
        			} else
        				$this->GETParameters[$param[0]] = $param[1];
        		}
        	}
        	
            return $this->GETParameters;
        }
        #.endif
        
        #.ifdef 'PHPWORKER'
        /**
        * Returns all POST-parameters of this request
        * 
        */
        public function getPOSTParams() {
        	if($this->rawPOSTData) {
        		$this->readPOSTData($this->rawPOSTData);
        		$this->rawPOSTData = "";
        	}
            return $this->POSTParameters;
        }
        
        /**
        * Returns all Cookies the client sent in this request
        * 
        */
        public function getCookies() {
        	// Check for cookies
        	if($this->getRequestHeader('Cookie') && !$this->cookies) {
        		// Split cookies
        		$cookies = explode(';', $this->getRequestHeader('Cookie'));
        	
        		// Read cookies
        		foreach($cookies as $cookie) {
        			if($cookie == null)
        				break;
        	
        			$param = explode('=', trim($cookie), 2);
        			$param[0] = urldecode($param[0]);
        			$param[1] = urldecode($param[1]);
        	
        			if(strpos($param[0], '[') < strpos($param[0], ']')) {
        				preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $param[0], $parts);
        	
        				$paramDefinition = '$this->cookies[$parts[1][0]]';
        				foreach((array) $parts[2] as $index => $arrayKey) {
        					if($arrayKey == null)
        						$paramDefinition .= '[]';
        					else
        						$paramDefinition .= '[$parts[2]['.$index.']]';
        				}
        	
        				$paramDefinition .= ' = $param[1];';
        				eval($paramDefinition);
        			} else
        				$this->cookies[$param[0]] = $param[1];
        		}
        	}
        	
            return $this->cookies;
        }
        #.endif
        
        #.ifndef 'PHPWORKER'
        /**
         * Returns a (not human-readable) string representation of the object
         * 
         * This function's return value can be compared with a similar object's
         * return value, especially to check if it was manipulated, for example
         * after being returned from the PHP-SAPI
         * 
         * @return string
         */
        public function __toString() {
        	return serialize($this->requestHeaders)
        	. serialize($this->remoteIP)
        	. serialize($this->remotePort)
        	. serialize($this->requestURI)
        	. serialize($this->requestType)
        	. serialize($this->requestLine);
        }
        #.endif
    }                             
?>

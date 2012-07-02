<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* HTTPRequest.class.php                                        */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    namespace Pancake;
    
    if(PANCAKE !== true)
        exit;                                                       

    class HTTPRequest {
        private $requestHeaders = array();
        private $answerHeaders = array();
        private $protocolVersion = '1.0';
        private $requestType = null;
        private $answerCode = 0;
        private $answerBody = null;
        private $requestFilePath = null;
        private $GETParameters = array();
        private $POSTParameters = array();
        private $cookies = array();
        private $setCookies = array();
        private $requestWorker = null;
        private $vHost = null;
        private $requestLine = null;
        private $rangeFrom = 0;
        private $rangeTo = 0;
        private $acceptedCompressions = array();
        private $requestURI = null;
        private $remoteIP = null;
        private $remotePort = 0;
        private $localIP = null;
        private $localPort = 0;
        private $mimeType = null;
        private $uploadedFiles = array();
        private $uploadedFileTempNames = array();
        private $queryString = null;
        private $requestTime = 0;
        private $requestMicrotime = 0;
        private static $answerCodes = array(
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
        
        /**
        * Creates a new HTTPRequest-Object
        * 
        * @param RequestWorker $worker
        * @param string $remoteIP
        * @param int $remotePort
        * @param string $localIP
        * @param int $localPort
        * @return HTTPRequest
        */
        public function __construct(RequestWorker $worker, $remoteIP = null, $remotePort = null, $localIP = null, $localPort = null) {
            $this->requestWorker = $worker;
            $this->remoteIP = $remoteIP;
            $this->remotePort = $remotePort;
            $this->localIP = $localIP;
            $this->localPort = $localPort;
            $this->vHost = vHost::getDefault();
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
            
            // HyperText CoffeePot Control Protocol :-)
            if(($firstLine[0] == 'BREW' || $firstLine[0] == 'WHEN' || $firstLine[2] == 'HTCPCP/1.0') && Config::get('main.exposepancake') === true)
                throw new invalidHTTPRequestException('No coffee here. I\'m a Pancake. Try again at 1.3.3.7', 418, $requestHeader);
            
            // Check protocol version
            if(strtoupper($firstLine[2]) == 'HTTP/1.1')
                $this->protocolVersion = '1.1';
            else if(strtoupper($firstLine[2]) == 'HTTP/1.0')
                $this->protocolVersion = '1.0';
            else
                throw new invalidHTTPRequestException('Unsupported protocol: '.$firstLine[2], strpos($firstLine[2], 'HTTP') !== false ? 505 : 400, $requestHeader);
            unset($requestHeaders[0]);
            
            // Check request method
            if($firstLine[0] != 'GET' && $firstLine[0] != 'POST' && $firstLine[0] != 'HEAD' && $firstLine[0] != 'OPTIONS' && $firstLine[0] != 'TRACE')
                throw new invalidHTTPRequestException('Invalid request method: '.$firstLine[0], 501, $requestHeader);
            $this->requestType = $firstLine[0];
            
            // Check if request method is allowed
            if(($this->requestType == 'HEAD'    && Config::get('main.allowhead')    !== true)
            || ($this->requestType == 'TRACE'   && Config::get('main.allowtrace')   !== true)
            || ($this->requestType == 'OPTIONS' && Config::get('main.allowoptions') !== true)) 
                throw new invalidHTTPRequestException('The request method is not allowed: '.$this->requestType, 405, $requestHeader); 
            
            // Read Headers
            foreach($requestHeaders as $header) {
                if(trim($header) == null)
                    continue;
                $header = explode(':', $header, 2);
                $this->requestHeaders[$header[0]] = trim($header[1]);
            }
            
            // Check if Content-Length is given and not too large on POST
            if($this->requestType == 'POST') {
                global $Pancake_postMaxSize;
                
                if($this->getRequestHeader('Content-Length') > $Pancake_postMaxSize)
                    throw new invalidHTTPRequestException('The uploaded content is too large.', 413, $requestHeader);
                if($this->getRequestHeader('Content-Length') === null)
                    throw new invalidHTTPRequestException('Your request can\'t be processed without a given Content-Length', 411, $requestHeader);
            } 
            
            // Enough informations for TRACE gathered
            if($this->requestType == 'TRACE')
                return;
            
            // Check for Host-Header
            if(!$this->getRequestHeader('Host') && $this->protocolVersion == '1.1')
                throw new invalidHTTPRequestException('Missing required header: Host', 400, $requestHeader);
            
            // Search for vHost
            global $Pancake_vHosts;
            if(isset($Pancake_vHosts[$this->getRequestHeader('Host')]))
                $this->vHost = $Pancake_vHosts[$this->getRequestHeader('Host')];
            
            $this->requestURI = $firstLine[1] = $this->vHost->rewrite($firstLine[1]);
             
            // Split address from query string
            $path = explode('?', $firstLine[1], 2);
            $this->requestFilePath = $path[0];
            $this->queryString = $path[1];
            
            // Check if path begins with /
            if(substr($this->requestFilePath, 0, 1) != '/')
                $this->requestFilePath = '/' . $this->requestFilePath;
            
            // Do not allow requests to lower paths
            if(strpos($this->requestFilePath, '../'))
                throw new invalidHTTPRequestException('You are not allowed to access the requested file: '.$this->requestFilePath, 403, $requestHeader);
            
            // Check for index-files    
            if(is_dir($this->vHost->getDocumentRoot().$this->requestFilePath)) {
                foreach($this->vHost->getIndexFiles() as $file)
                    if(file_exists($this->vHost->getDocumentRoot().$this->requestFilePath.'/'.$file)) {
                        $this->requestFilePath .= (substr($this->requestFilePath, -1, 1) == '/' ? null : '/') . $file;
                        goto checkRead;
                    }
                // No index file found, check if vHost allows directory listings
                if($this->vHost->allowDirectoryListings() !== true)
                    throw new invalidHTTPRequestException('You\'re not allowed to view the listing of the requested directory: '.$this->requestFilePath, 403, $requestHeader);
            }

            checkRead:
            
            // Check if requested file exists and is accessible
            if(!file_exists($this->vHost->getDocumentRoot() . $this->requestFilePath))
                throw new invalidHTTPRequestException('File does not exist: '.$this->requestFilePath, 404, $requestHeader);
            if(!is_readable($this->vHost->getDocumentRoot() . $this->requestFilePath))
                throw new invalidHTTPRequestException('You\'re not allowed to access the requested file: '.$this->requestFilePath, 403, $requestHeader);
            
            // Check if requested path needs authentication
            if($authData = $this->vHost->requiresAuthentication($this->requestFilePath)) {
                if($this->getRequestHeader('Authorization')) {
                    if($authData['type'] == 'basic') {
                        $auth = explode(" ", $this->getRequestHeader('Authorization'));
                        $userPassword = explode(":", base64_decode($auth[1]));
                        if($this->vHost->isValidAuthentication($this->requestFilePath, $userPassword[0], $userPassword[1]))
                            goto valid;
                    } //else {
                         
                    //}
                }
                if($authData['type'] == 'basic') {
                    $this->setHeader('WWW-Authenticate', 'Basic realm="'.$authData['realm'].'"');
                    throw new invalidHTTPRequestException('You need to authorize in order to access this file.', 401, $requestHeader);
                }
            }
            
            valid:
            
            // Check for If-Unmodified-Since
            if($this->getRequestHeader('If-Unmodified-Since')) {
                if(filemtime($this->vHost->getDocumentRoot().$this->requestFilePath) != strtotime($this->getRequestHeader('If-Unmodified-Since')))
                    throw new invalidHTTPRequestException('File was modified since requested time.', 412, $requestHeader);
            }
            
            // Check for accepted compressions
            if($this->getRequestHeader('Accept-Encoding')) {
                $accepted = explode(',', $this->getRequestHeader('Accept-Encoding'));
                foreach($accepted as $format) {
                    $format = strtolower(trim($format));
                    $this->acceptedCompressions[$format] = true;
                }
            }
            
            // Check for Range-header
            if($this->getRequestHeader('Range')) {
                preg_match('~([0-9]+)-([0-9]+)?~', $this->getRequestHeader('Range'), $range);
                $this->rangeFrom = $range[1];
                $this->rangeTo = $range[2];
            }
            
            // Get MIME-type of the requested file
            $this->mimeType = MIME::typeOf($this->vHost->getDocumentRoot() . $this->requestFilePath);
            
            // Split GET-parameters
            $get = explode('&', $path[1]);
            
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
             
            // Check for cookies
            if($this->getRequestHeader('Cookie')) {
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
            
            $this->requestTime = time();
            $this->requestMicrotime = microtime(true);
            
            // Set default host
            if(!$this->getRequestHeader('Host'))
                $this->requestHeaders['Host'] = $this->vHost->getHost();                  
        }
        
        /**
        * Processes the POST request body
        * 
        * @param string $postData The request body received from the client
        */
        public function readPOSTData($postData) {
            // Check for url-encoded parameters
            if(strpos($this->getRequestHeader('Content-Type'), 'application/x-www-form-urlencoded') !== false) {
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
            } else if(strpos($this->getRequestHeader('Content-Type'), 'multipart/form-data') !== false) {
                // Get boundary string that splits the dispositions
                preg_match('~boundary=(.*)~', $this->getRequestHeader('Content-Type'), $boundary);
                if(!($boundary = $boundary[1]))
                    return false;
                
                // For some strange reason the actual boundary string is -- + the specified boundary string
                $postData = str_replace("\r\n--" . $boundary . '--', null, $postData);
                
                $dispositions = explode("\r\n--" . $boundary, $postData);
                
                // The first disposition will have a boundary string at its beginning
                $disposition[0] = substr($disposition[0], strlen('--' . $boundary . "\r\n"));

                foreach($dispositions as $disposition) {
                    $dispParts = explode("\r\n\r\n", $disposition, 2);
                    preg_match('~Content-Disposition: form-data;[ ]?name="(.*?)";?[ ]?(?:filename="(.*?)")?(?:\r\n)?(?:Content-Type: (.*))?~', $dispParts[0], $data);
                    // [ 0 => string, 1 => name, 2 => filename, 3 => Content-Type ]
                    if(isset($data[2]) && isset($data[3])) {
                        $tmpFileName = tempnam(Config::get('main.tmppath'), 'UPL');
                        file_put_contents($tmpFileName, $dispParts[1]);
                        
                        $dataArray = array(
                                            'name' => $data[2],
                                            'type' => $data[3],
                                            'error' => UPLOAD_ERR_OK,
                                            'size' => strlen($dispParts[1]),
                                            'tmp_name' => $tmpFileName);
                        
                        if(strpos($data[1], '[') < strpos($data[1], ']')) {
                            preg_match_all('~(.*?)(?:\[([^\]]*)\])~', $data[1], $parts);
                            
                            $paramDefinition = '$file[$parts[1][0]]';
                            foreach((array) $parts[2] as $index => $arrayKey) {
                                if($arrayKey != null)
                                    $paramDefinition .= '[$parts[2]['.$index.']]';
                                else
                                    $multi = true;
                            }
                            
                            if($multi) {
                                $paramDefinitions[$paramDefinition]++;
                                $dataArray = array( 'name' => array( $paramDefinitions[$paramDefinition] - 1 => $data[2]),
                                                    'type' => array( $paramDefinitions[$paramDefinition] - 1 => $data[3]),
                                                    'error' => array( $paramDefinitions[$paramDefinition] - 1 => UPLOAD_ERR_OK),
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
        
        /**
        * Set answer on invalid request
        * 
        * @param invalidHTTPRequestException $exception
        */
        public function invalidRequest(invalidHTTPRequestException $exception) {
            $this->setHeader('Content-Type', 'text/html; charset=utf-8');
            $this->answerCode = $exception->getCode();
            $this->answerBody = '<!doctype html>';
            $this->answerBody .= '<html>';
            $this->answerBody .= '<head>';
                $this->answerBody .= '<title>'.$this->answerCode.' '.$this->getCodeString($this->answerCode).'</title>';
                $this->answerBody .= '<style>';
                    $this->answerBody .= 'body{font-family:"Arial"}';
                    $this->answerBody .= 'hr{border:1px solid #000}';
                $this->answerBody .= '</style>';
            $this->answerBody .= '</head>';
            $this->answerBody .= '<body>';
                $this->answerBody .= '<h1>'.$this->answerCode.' '.$this->getCodeString($this->answerCode).'</h1>';
                $this->answerBody .= '<hr/>';
                $this->answerBody .= '<strong>'.($this->answerCode >= 500 ? 'Your HTTP-Request could not be processed' : 'Your HTTP-Request was invalid').'.</strong> Error description:<br/>';
                $this->answerBody .= $exception->getMessage().'<br/><br/>';
                if($exception->getHeader()) {
                    $this->answerBody .= "<strong>Headers:</strong><br/>";
                    $this->answerBody .= nl2br($exception->getHeader());
                }
                if(Config::get('main.exposepancake') === true) {
                    $this->answerBody .= '<hr/>';
                    $this->answerBody .= 'Pancake ' . VERSION;
                }
            $this->answerBody .= '</body>';
            $this->answerBody .= '</html>';
        }
       
        /**
        * Build complete answer
        *  
        */
        public function buildAnswerHeaders() {
            // Check for TRACE
            if($this->getRequestType() == 'TRACE' && $this->getAnswerCode() != 405) {
                $answer = $this->getRequestLine()."\r\n";
                $answer .= $this->getRequestHeaders()."\r\n";
                return $answer;
            }
            
            // Set AnswerCode if not set
            if(!$this->getAnswerCode())
                (!$this->getAnswerHeader('Content-Length') && !$this->getAnswerBody() && $this->vHost->send204OnEmptyPage()) ? $this->setAnswerCode(204) : $this->setAnswerCode(200);
            // Set Connection-Header
            if($this->getAnswerCode() >= 200 && $this->getAnswerCode() < 400 && strtolower($this->getRequestHeader('Connection')) == 'keep-alive')
                $this->setHeader('Connection', 'keep-alive');
            else
                $this->setHeader('Connection', 'close');
            // Add Server-Header
            if(Config::get('main.exposepancake') === true)
                $this->setHeader('Server', 'Pancake/' . VERSION);
            // Set cookies
            foreach($this->setCookies as $cookie)
                $setCookie .= ($setCookie) ? "\r\nSet-Cookie: ".$cookie : $cookie;
            
            if($setCookie)
                $this->setHeader('Set-Cookie', $setCookie);
            // Set Content-Length
            if(!$this->getAnswerHeader('Content-Length'))
                $this->setHeader('Content-Length', strlen($this->getAnswerBody()));
            // Set Content-Type if not set
            if(!$this->getAnswerHeader('Content-Type', false) && $this->getAnswerHeader('Content-Length'))  
                $this->setHeader('Content-Type', 'text/html');                                              
            // Set Date
            if(!$this->getAnswerHeader('Date'))
                $this->setHeader('Date', date('r'));
            
            // Build Answer
            $answer = 'HTTP/'.$this->getProtocolVersion().' '.$this->getAnswerCode().' '.self::getCodeString($this->getAnswerCode())."\r\n";
            $answer .= $this->getAnswerHeaders();
            $answer .= "\r\n";
            
            return $answer;
        }
        
        /**
        * Set Answer Header
        * 
        * @param string $headerName
        * @param string $headerValue
        * @param boolean $replace
        */
        public function setHeader($headerName, $headerValue, $replace = true) {
            if($replace) {
                unset($this->answerHeaders[$headerName]);
                $this->answerHeaders[$headerName] = $headerValue;
            } else {
                if($value = $this->answerHeaders[$headerName] && !is_array($this->answerHeaders[$headerName])) {
                    unset($this->answerHeaders[$headerName]);
                    $this->answerHeaders[$headerName][] = $value;
                }
                $this->answerHeaders[$headerName][] = $headerValue;
            }
            return true;
        }
        
        /**
        * Remove Answer Header
        * 
        * @param string $headerName
        */
        public function removeHeader($headerName) {
            unset($this->answerHeaders[$headerName]);
        }
        
        /**
        * Removes all Headers to be sent
        * 
        */
        public function removeAllHeaders() {
            $this->answerHeaders = array();
        }
        
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
            if(is_dir($this->vHost->getDocumentRoot() . $this->requestFilePath) && substr($this->requestFilePath, -1, 1) != '/')
                $appendSlash = '/';
            
            $_SERVER['REQUEST_TIME'] = $this->requestTime;
            $_SERVER['REQUEST_TIME_FLOAT'] = $this->requestMicrotime;
            $_SERVER['USER'] = Config::get('main.user');
            $_SERVER['REQUEST_METHOD'] = $this->requestType;
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/' . $this->protocolVersion;
            $_SERVER['SERVER_SOFTWARE'] = 'Pancake/' . VERSION;
            $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['DOCUMENT_URI'] = $this->requestFilePath . $appendSlash;
            $_SERVER['REQUEST_URI'] = $this->requestURI;
            $_SERVER['SCRIPT_FILENAME'] = (substr($this->vHost->getDocumentRoot(), -1, 1) == '/' ? substr($this->vHost->getDocumentRoot(), 0, strlen($this->vHost->getDocumentRoot()) - 1) : $this->vHost->getDocumentRoot()) . $this->requestFilePath . $appendSlash;
            $_SERVER['REMOTE_ADDR'] = $this->remoteIP;
            $_SERVER['REMOTE_PORT'] = $this->remotePort;
            $_SERVER['QUERY_STRING'] = $this->queryString;         
            $_SERVER['DOCUMENT_ROOT'] = $this->vHost->getDocumentRoot();
            $_SERVER['SERVER_NAME'] = $this->getRequestHeader('Host') ? $this->getRequestHeader('Host') : $this->vHost->getHost();
            $_SERVER['SERVER_ADDR'] = $this->localIP;
            $_SERVER['SERVER_PORT'] = $this->localPort;

            foreach($this->requestHeaders as $name => $value)
                $_SERVER['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
            
            return $_SERVER;
        }
        
        /**
        * Get the value of a single Request-Header
        * 
        * @param string $headerName
        * @return Value of the Header
        */
        public function getRequestHeader($headerName) {
            return $this->requestHeaders[$headerName];
        }
        
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
        
        /**
         * Returns an array with all headers of the request
         * 
         * @return array
         */
        public function getRequestHeadersArray() {
        	return $this->requestHeaders;
        }
        
        /**
        * Get the HTTP-Version used for this Request
        * 
        * @return 1.0 or 1.1
        */
        public function getProtocolVersion() {
            return $this->protocolVersion;
        }
        
        /**
        * Get the HTTP-type of this request
        * 
        */
        public function getRequestType() {
            return $this->requestType;
        }
        
        /**
        * Get the path of the requested file
        * 
        */
        public function getRequestFilePath() {
            return $this->requestFilePath;
        }
        
        /**
        * Get formatted AnswerHeaders
        * 
        */
        public function getAnswerHeaders() {
            foreach($this->answerHeaders as $headerName => $headerValue) {
                if(is_array($headerValue)) {
                    foreach($headerValue as $value)
                        $headers .= $headerName.': '.$value."\r\n";
                } else
                    $headers .= $headerName.': '.$headerValue."\r\n";
            }
            return $headers;
        }
        
        /**
        * Returns all AnswerHeaders as an array
        * 
        */
        public function getAnswerHeadersArray() {
            foreach($this->answerHeaders as $headerName => $headerValue) {
                if(is_array($headerValue)) {
                    foreach($headerValue as $value)
                        $headers[] = $headerName.': '.$value;
                } else
                    $headers[] = $headerName.': '.$headerValue;
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
            if($caseSensitive)
                return $this->answerHeaders[$headerName];
            else {
                $headerName = strtolower($headerName);
                foreach($this->answerHeaders as $name => $value) {
                    if(strtolower($name) == $headerName) 
                        return $this->answerHeaders[$name];
                }
            }
        }
        
        /**
        * Set answer code
        * 
        * @param int $value A valid HTTP answer code, for example 200 or 404
        */
        public function setAnswerCode($value) {
            if(!array_key_exists($value, self::$answerCodes))
                return false;
            return $this->answerCode = (int) $value;
        }
        
        /**
        * Get answer code
        * 
        */
        public function getAnswerCode() {
            return $this->answerCode;
        }
        
        /**
        * Get answer body
        * 
        */
        public function getAnswerBody() {
            return $this->answerBody;
        }
        
        /**
        * Set answer body
        * 
        * @param string $value
        */
        public function setAnswerBody($value) {
            return $this->answerBody = $value;
        }
        
        /**
        * Get the RequestWorker-instance handling this request
        * 
        */
        public function getRequestWorker() {
            return $this->requestWorker;
        }
        
        /**
        * Get the vHost for this request
        * 
        * @return vHost
        */
        public function getvHost() {
            return $this->vHost;
        }
        
        /**
        * Returns all GET-parameters of this request
        * 
        */
        public function getGETParams() {
            return $this->GETParameters;
        }
        
        /**
        * Returns all POST-parameters of this request
        * 
        */
        public function getPOSTParams() {
            return $this->POSTParameters;
        }
        
        /**
        * Returns all Cookies the client sent in this request
        * 
        */
        public function getCookies() {
            return $this->cookies;
        }
        
        /**
        * Returns the first line of the request
        * 
        */
        public function getRequestLine() {
            return $this->requestLine;
        }
        
        /**
        * Returns the start of the requested range
        * 
        */
        public function getRangeFrom() {
            return $this->rangeFrom;
        }
        
        /**
        * Returns the end of the requested range
        * 
        */
        public function getRangeTo() {
            return $this->rangeTo;
        }
        
        /**
        * Returns the IP of the client
        * 
        */
        public function getRemoteIP() {
            return $this->remoteIP;
        }
        
        /**
        * Returns the port the client listens on
        * 
        */
        public function getRemotePort() {
            return $this->remotePort;
        }
        
        /**
        * Returns the MIME-type of the requested file
        * 
        */
        public function getMIMEType() {
            return $this->mimeType;
        }
        
        /**
        * Returns an array with the uploaded files (similar to $_FILES)
        * 
        */
        public function getUploadedFiles() {
            return $this->uploadedFiles;
        } 
        
        /**
        * Returns an array with the temporary names of all uploaded files
        * 
        */
        public function getUploadedFileNames() {
            return $this->uploadedFileTempNames;
        }
        
        /**
        * Check if client accepts a specific compression format
        * 
        * @param string $compression Name of the compression, e. g. gzip, deflate, etc.
        */
        public function acceptsCompression($compression) {
            return $this->acceptedCompressions[strtolower($compression)] === true;
        }
        
        /**
        * Get message corresponding to an answer code
        * 
        * @param int $code Valid answer code, for example 200 or 404
        * @return string The corresponding string, for example "OK" or "Not found"
        */
        public static function getCodeString($code) {
            return self::$answerCodes[$code];
        }
    }                             
?>

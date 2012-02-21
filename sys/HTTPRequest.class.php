<?php
  
    /****************************************************************/
    /* Pancake                                                      */
    /* HTTPRequest.class.php                                        */
    /* 2012 Yussuf "pp3345" Khalil                                  */
    /* License: http://creativecommons.org/licenses/by-nc-sa/3.0/   */
    /****************************************************************/
    
    if(PANCAKE_HTTP !== true)
        exit;                                                       

    class Pancake_HTTPRequest {
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
        private static $answerCodes = array(
                                            100 => 'Continue',
                                            101 => 'Switching Protocols',
                                            102 => 'Processing',
                                            118 => 'Connection timed out',
                                            200 => 'OK',
                                            201 => 'Created',
                                            202 => 'Accepted',
                                            203 => 'Non-Authoritative Information',
                                            204 => 'No content',
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
                                            400 => 'Bad request',
                                            401 => 'Unauthorized',
                                            402 => 'Payment required',
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
        * @param Pancake_RequestWorker $worker
        * @return Pancake_HTTPRequest
        */
        public function __construct(Pancake_RequestWorker $worker) {
            $this->requestWorker = $worker;
        }
        
        /**
        * Initialize RequestObject
        *  
        * @param string $requestHeader Headers of the request
        */
        public function init($requestHeader) {
            try {
                // Split headers from body
                $requestParts = explode("\r\n\r\n", $requestHeader, 2);
                
                // Get single header lines
                $requestHeaders = explode("\r\n", $requestParts[0]);
                
                // Split first line
                $firstLine = explode(" ", $requestHeaders[0]);
                
                $this->requestLine = $requestHeaders[0];
                
                // HyperText CoffeePot Control Protocol :-)
                if(($firstLine[0] == 'BREW' || $firstLine[0] == 'WHEN') && Pancake_Config::get('main.exposepancake') === true)
                    throw new Pancake_InvalidHTTPRequestException('It seems like you were trying to make coffee via HTCPCP, but I\'m a Pancake, not a Coffee Pot.', 418, $requestHeader);
                
                // Check request-method
                if($firstLine[0] != 'GET' && $firstLine[0] != 'POST' && $firstLine[0] != 'HEAD' && $firstLine[0] != 'OPTIONS' && $firstLine[0] != 'TRACE')
                    throw new Pancake_InvalidHTTPRequestException('Invalid request-method: '.$firstLine[0], 501, $requestHeader);
                $this->requestType = $firstLine[0];
                
                // Check if request-method is allowed
                if(($this->requestType == 'HEAD'    && Pancake_Config::get('main.allowhead')    !== true)
                || ($this->requestType == 'TRACE'   && Pancake_Config::get('main.allowtrace')   !== true)
                || ($this->requestType == 'OPTIONS' && Pancake_Config::get('main.allowoptions') !== true)) 
                    throw new Pancake_InvalidHTTPRequestException('The request-method you are trying to use is not allowed: '.$this->requestType, 405, $requestHeader); 
                
                // Check protocol version
                if(strtoupper($firstLine[2]) == 'HTTP/1.1')
                    $this->protocolVersion = '1.1';
                else if(strtoupper($firstLine[2]) == 'HTTP/1.0')
                    $this->protocolVersion = '1.0';
                else
                    throw new Pancake_InvalidHTTPRequestException('Unsupported protocol: '.$firstLine[2], 505, $requestHeader);
                unset($requestHeaders[0]);
                
                // Read Headers
                foreach($requestHeaders as $header) {
                    if(trim($header) == null)
                        continue;
                    $header = explode(':', $header, 2);
                    $this->requestHeaders[$header[0]] = trim($header[1]);
                }
                
                // Enough informations for TRACE gathered
                if($this->requestType == 'TRACE')
                    return;
                
                // Check for Host-Header
                if(!$this->getRequestHeader('Host'))
                    throw new Pancake_InvalidHTTPRequestException('Missing required header: Host', 400, $requestHeader);
                
                // Search for vHost
                global $Pancake_vHosts;
                if(!isset($Pancake_vHosts[$this->getRequestHeader('Host')]))
                    throw new Pancake_InvalidHTTPRequestException('No vHost for host "'.$this->getRequestHeader('Host').'"', 400, $requestHeader);
                else
                    $this->vHost = $Pancake_vHosts[$this->getRequestHeader('Host')];
                 
                // Split address from request-parameters
                $path = explode('?', $firstLine[1], 2);
                $this->requestFilePath = $path[0];
                
                // Check if path begins with /
                if(substr($this->requestFilePath, 0, 1) != '/')
                    $this->requestFilePath = '/' . $this->requestFilePath;
                
                // Do not allow requests to lower paths
                if(strpos($this->requestFilePath, '../'))
                    throw new Pancake_InvalidHTTPRequestException('You\'re not allowed to see the requested file: '.$this->requestFilePath, 403, $requestHeader);
                
                // Check for index-files    
                if(is_dir($this->vHost->getDocumentRoot().$this->requestFilePath)) {
                    foreach($this->vHost->getIndexFiles() as $file)
                        if(file_exists($this->vHost->getDocumentRoot().$this->requestFilePath.'/'.$file)) {
                            $this->requestFilePath .= '/'.$file;
                            goto checkRead;
                        }
                    // No index file found, check if vHost allows directory listings
                    if($this->vHost->allowDirectoryListings() !== true)
                        throw new Pancake_InvalidHTTPRequestException('You\'re not allowed to view the listing of the requested directory: '.$this->requestFilePath, 403, $requestHeader);
                }

                checkRead:
                
                // Check if requested file exists and is accessible
                if(!file_exists($this->vHost->getDocumentRoot() . $this->requestFilePath))
                    throw new Pancake_InvalidHTTPRequestException('File does not exist: '.$this->requestFilePath, 404, $requestHeader);
                if(!is_readable($this->vHost->getDocumentRoot() . $this->requestFilePath))
                    throw new Pancake_InvalidHTTPRequestException('You\'re not allowed to see the requested file: '.$this->requestFilePath, 403, $requestHeader);
                
                // Check if requested path needs authentication
                if($authData = $this->vHost->requiresAuthentication($this->requestFilePath)) {
                    if($this->getRequestHeader('Authorization')) {
                        if($authData['type'] == 'basic') {
                            $auth = explode(" ", $this->getRequestHeader('Authorization'));
                            $userPassword = explode(":", base64_decode($auth[1]));
                            if($this->vHost->isValidAuthentication($this->requestFilePath, $userPassword[0], $userPassword[1]))
                                goto valid;
                        } else {
                             
                        }
                    }
                    if($authData['type'] == 'basic') {
                        $this->setHeader('WWW-Authenticate', 'Basic realm="'.$authData['realm'].'"');
                        throw new Pancake_InvalidHTTPRequestException('You need to authorize in order to view this file.', 401, $requestHeader);
                    }
                }
                
                valid:
                
                // Check for Range-header
                if($this->getRequestHeader('Range')) {
                    preg_match('~([0-9]+)-([0-9]+)?~', $this->getRequestHeader('Range'), $range);
                    $this->rangeFrom = $range[1];
                    $this->rangeTo = $range[2];
                }
                
                // Split GET-parameters
                $get = explode('&', $path[1]);
                
                // Read GET-parameters
                foreach($get as $param) {
                    if($param == null)
                        break;
                    $param = explode('=', $param, 2);
                    $this->GETParameters[urldecode($param[0])] = urldecode($param[1]);
                }
                
                // Check for POST-parameters
                if($this->requestType == 'POST') {
                    // Check for url-encoded parameters
                    if(strpos($this->getRequestHeader('Content-Type'), 'application/x-www-form-urlencoded') !== false) {
                        // Split POST-parameters
                        $post = explode('&', $requestParts[1]);
                        
                        // Read POST-parameters
                        foreach($post as $param) {
                            if($param == null)
                                break;
                            $param = explode('=', $param, 2);
                            $this->POSTParameters[urldecode($param[0])] = urldecode($param[1]);
                        }
                    }
                }
                 
                // Check for cookies
                if($this->getRequestHeader('Cookie')) {
                    // Split cookies
                    $cookies = explode(';', $this->getRequestHeader('Cookie'));
                    
                    // Read cookies
                    foreach($cookies as $cookie) {
                        if($cookie == null)
                            break;
                        $cookie = trim($cookie);
                        $cookie = explode('=', $cookie, 2);
                        $this->cookies[urldecode($cookie[0])] = urldecode($cookie[1]);
                    }
                }
            } catch (Pancake_InvalidHTTPRequestException $e) {
                $this->invalidRequest($e);
                throw $e;
            }                          
        }
        
        /**
        * Set answer on invalid request
        * 
        * @param Pancake_InvalidHTTPRequestException $exception
        */
        private function invalidRequest(Pancake_InvalidHTTPRequestException $exception) {
            $this->setHeader('Content-Type', 'text/html');
            $this->answerCode = $exception->getCode();
            $this->answerBody = '<!doctype html>';
            $this->answerBody .= '<html>';
            $this->answerBody .= '<head>';
                $this->answerBody .= '<title>'.$this->answerCode.' '.$this->getCodeString($this->answerCode).'</title>';
                $this->answerBody .= '<style>';
                    $this->answerBody .= 'body{font-family:"Arial"}';
                    $this->answerBody .= 'hr{border:1px solid #000}';
                $this->answerBody .= '</style>';
                $this->answerBody .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            $this->answerBody .= '</head>';
            $this->answerBody .= '<body>';
                $this->answerBody .= '<h1>'.$this->answerCode.' '.$this->getCodeString($this->answerCode).'</h1>';
                $this->answerBody .= '<hr/>';
                $this->answerBody .= '<strong>Your HTTP-Request was invalid.</strong> Error:<br/>';
                $this->answerBody .= $exception->getMessage().'<br/><br/>';
                $this->answerBody .= "<strong>Headers:</strong><br/>";
                $this->answerBody .= nl2br($exception->getHeader());
                if(Pancake_Config::get('main.exposepancake') === true) {
                    $this->answerBody .= '<hr/>';
                    $this->answerBody .= 'Pancake ' . PANCAKE_VERSION;
                }
            $this->answerBody .= '</body>';
            $this->answerBody .= '</html>';
        }
       
        /**
        * Build complete answer
        *  
        */
        public function buildAnswer() {
            // Check for TRACE
            if($this->getRequestType() == 'TRACE' && $this->getAnswerCode() != 405) {
                $answer = $this->getRequestLine()."\r\n";
                $answer .= $this->getRequestHeaders()."\r\n";
                return $answer;
            }
            
            // Set AnswerCode if not set
            if(!$this->getAnswerCode())
                ($this->getAnswerBody()) ? $this->setAnswerCode(200) : $this->setAnswerCode(204);
            // Set Connection-Header
            if($this->getAnswerCode() >= 200 && $this->getAnswerCode() < 400 && strtolower($this->getRequestHeader('Connection')) == 'keep-alive')
                $this->setHeader('Connection', 'keep-alive');
            else
                $this->setHeader('Connection', 'close');
            // Add Server-Header
            if(Pancake_Config::get('main.exposepancake') === true)
                $this->setHeader('Server', 'Pancake/' . PANCAKE_VERSION);
            // Set cookies
            foreach($this->setCookies as $cookie) {
                $setCookie .= ($setCookie) ? "\r\nSet-Cookie: ".$cookie : $cookie;
            }
            if($setCookie)
                $this->setHeader('Set-Cookie', $setCookie);
            // Set Content-Length
            if(!$this->getAnswerHeader('Content-Length'))
                $this->setHeader('Content-Length', strlen($this->getAnswerBody()));
            // Set Content-Type if not set
            if(!$this->getAnswerHeader('Content-Type') && $this->getAnswerHeader('Content-Length'))
                $this->setHeader('Content-Type', 'text/html');
            // Set Date
            if(!$this->getAnswerHeader('Date'))
                $this->setHeader('Date', date('r'));
            
            // Build Answer
            $answer = 'HTTP/'.$this->getProtocolVersion().' '.$this->getAnswerCode().' '.self::getCodeString($this->getAnswerCode())."\r\n";
            $answer .= $this->getAnswerHeaders();
            $answer .= "\r\n";
            if($this->getRequestType() != 'HEAD')
                $answer .= $this->getAnswerBody();
            
            return $answer;
        }
        
        /**
        * Set Answer Header
        * 
        * @param string $headerName
        * @param string $headerValue
        */
        public function setHeader($headerName, $headerValue) {
            return $this->answerHeaders[$headerName] = $headerValue;
        }
        
        /**
        * Sets a cookie. Parameters similar to PHPs function setcookie()
        * 
        */
        public function setCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false) {
            $cookie = urlencode($name).'='.urlencode($value);
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
                $headers .= $headerName.': '.$headerValue."\r\n";
            }
            return $headers;
        }
        
        /**
        * Get a single AnswerHeader
        * 
        * @param string $headerName
        */
        public function getAnswerHeader($headerName) {
            return $this->answerHeaders[$headerName];
        }
        
        /**
        * Set AnswerCode
        * 
        * @param int $value A valid HTTP-Answer-Code, for example 200 or 404
        */
        public function setAnswerCode($value) {
            return $this->answerCode = $value;
        }
        
        /**
        * Get AnswerCode
        * 
        */
        public function getAnswerCode() {
            return $this->answerCode;
        }
        
        /**
        * Get AnswerBody
        * 
        */
        public function getAnswerBody() {
            return $this->answerBody;
        }
        
        /**
        * Set AnswerBody
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
        * @return Pancake_vHost
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
        * Get Message corresponding to an AnswerCode
        * 
        * @param int $code Valid AnswerCode, for example 200 or 404
        * @return string The corresponding string, for example "OK" or "Not found"
        */
        public static function getCodeString($code) {
            return self::$answerCodes[$code];
        }
    }                             
?>

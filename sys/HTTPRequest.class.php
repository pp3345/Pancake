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
        private $requestWorker = null;
        private $vHost = null;
        private $requestLine = null;
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
                                            404 => 'Not found',
                                            405 => 'Method not allowed',
                                            406 => 'Not Acceptable',
                                            407 => 'Proxy Authentication Required',
                                            408 => 'Request Time-out',
                                            409 => 'Conflict',
                                            410 => 'Gone',
                                            411 => 'Length Required',
                                            412 => 'Precondition Failed',
                                            413 => 'Request Entity Too Large',
                                            414 => 'Request-URI Too Long',
                                            415 => 'Unsupported Media Type',
                                            416 => 'Requested range not satisfiable',
                                            417 => 'Expectation Failed',
                                            418 => 'I\'m a pancake',
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
                // Get single header lines
                $requestHeaders = explode("\r\n", $requestHeader);
                
                // Split first line
                $firstLine = explode(" ", $requestHeaders[0]);
                
                // Check request-method
                if($firstLine[0] != 'GET' && $firstLine[0] != 'POST' && $firstLine[0] != 'HEAD' && $firstLine[0] != 'OPTIONS' && $firstLine[0] != 'TRACE')
                    throw new Pancake_InvalidHTTPRequestException('Invalid request-method: '.$firstLine[0], 501, $requestHeader);
                $this->requestType = $firstLine[0];
                
                // Check protocol version
                if($firstLine[2] == 'HTTP/1.0')
                    $this->protocolVersion = '1.0';
                else if($firstLine[2] == 'HTTP/1.1')
                    $this->protocolVersion = '1.1';
                else
                    throw new Pancake_InvalidHTTPRequestException('Unsupported protocol: '.$firstLine[2], 505, $requestHeader);
                $this->requestLine = $requestHeaders[0];
                unset($requestHeaders[0]);
                
                // Read Headers
                foreach($requestHeaders as $header) {
                    if(trim($header) == null)
                        continue;
                    $header = explode(':', $header, 2);
                    $this->requestHeaders[$header[0]] = trim($header[1]);
                }
                
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
                
                // Do not allow requests to lower paths
                if(strpos($this->requestFilePath, '../'))
                    throw new Pancake_InvalidHTTPRequestException('You\'re not allowed to see the requested file: '.$this->requestFilePath, 403, $requestHeader);
                
                // Check for index-files    
                if(is_dir($this->vHost->getDocumentRoot().$this->requestFilePath)) {
                    foreach($this->vHost->getIndexFiles() as $file)
                        if(file_exists($this->vHost->getDocumentRoot().$this->requestFilePath.'/'.$file)) {
                            $this->requestFilePath .= '/'.$file;
                            break;
                        }
                }
                
                // Check if requested file exists and is accessible
                if(!file_exists($this->vHost->getDocumentRoot() . $this->requestFilePath))
                    throw new Pancake_InvalidHTTPRequestException('File does not exist: '.$this->requestFilePath, 404, $requestHeader);
                if(!is_readable($this->vHost->getDocumentRoot() . $this->requestFilePath))
                    throw new Pancake_InvalidHTTPRequestException('You\'re not allowed to see the requested file: '.$this->requestFilePath, 403, $requestHeader);
            
                // Split GET-parameters
                $get = explode('&', $path[1]);
                
                // Read GET-parameters
                foreach($get as $param) {
                    if($param == null)
                        break;
                    $param = explode('=', $param);
                    $this->GETParameters[urldecode($param[0])] = urldecode($param[1]);
                }
                
                // Read POST-parameters
                if($this->requestType == 'POST') {
                    // To be implemented
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
            $this->setHeader('Content-Type', 'text/plain');
            $this->answerCode = $exception->getCode();
            $this->answerBody = $this->answerCode.' '.$this->getCodeString($this->answerCode)."\r\n\r\n";
            $this->answerBody .= 'Your HTTP-Request was invalid. Error:'."\r\n";
            $this->answerBody .= $exception->getMessage();
            $this->answerBody .= "\r\n\r\nHeaders:\r\n";
            $this->answerBody .= $exception->getHeader();
        }
       
        /**
        * Build complete answer
        *  
        */
        public function buildAnswer() {
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
            // Set Content-Length
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

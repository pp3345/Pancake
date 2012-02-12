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
        private $requestWorker = null;
        private static $answerCodes = array(
                                            200 => 'OK',
                                            204 => 'No content',
                                            400 => 'Bad request',
                                            403 => 'Forbidden',
                                            404 => 'Not found',
                                            500 => 'Internal Server Error');
        
        
        public function __construct(Pancake_RequestWorker $worker) {
            $this->requestWorker = $worker;
        }
         
        public function init($requestHeader) {
            try {
                $requestHeaders = explode("\r\n", $requestHeader);
                $firstLine = explode(" ", $requestHeaders[0]);
                if($firstLine[0] != 'GET' && $firstLine[0] != 'POST' && $firstLine[0] != 'HEAD' && $firstLine[0] != 'OPTIONS' && $firstLine[0] != 'TRACE')
                    throw new Pancake_InvalidHTTPRequestException('Invalid request-method: '.$firstLine[0], $requestHeader);
                $this->requestType = $firstLine[0];
                if($firstLine[2] == 'HTTP/1.0')
                    $this->protocolVersion = '1.0';
                else if($firstLine[2] == 'HTTP/1.1')
                    $this->protocolVersion = '1.1';
                else
                    throw new Pancake_InvalidHTTPRequestException('Unsupported protocol: '.$firstLine[2], $requestHeader);
                unset($requestHeaders[0]);
                foreach($requestHeaders as $header) {
                    if(trim($header) == null)
                        continue;
                    $header = explode(':', $header, 2);
                    $this->requestHeaders[$header[0]] = trim($header[1]);
                }
                $path = explode('?', $firstLine[1], 2);
                $this->requestFilePath = $path[0];
                $get = explode('&', $path[1]);
                foreach($get as $param) {
                    if($param == null)
                        break;
                    $param = explode('=', $param);
                    $this->GETParameters[urldecode($param[0])] = urldecode($param[1]);
                }
                if($this->requestType == 'POST') {
                    // To be implemented
                }
            } catch (Pancake_InvalidHTTPRequestException $e) {
                $this->invalidRequest($e);
                throw $e;
            }                          
        }
        
        private function invalidRequest(Pancake_InvalidHTTPRequestException $exception) {
            $this->setHeader('Content-Type', 'text/plain');
            $this->answerCode = 400;
            $this->answerBody = 'Your HTTP-Request was invalid. Error:'."\r\n";
            $this->answerBody .= $exception->getMessage();
            $this->answerBody .= "\r\n\r\nHeaders:\r\n";
            $this->answerBody .= $exception->getHeader();
        }
        
        public function buildAnswer() {
            if(!$this->getAnswerCode())
                ($this->getAnswerBody()) ? $this->setAnswerCode(200) : $this->setAnswerCode(204);
            if($this->getAnswerCode() >= 200 && $this->getAnswerCode() < 300 && $this->getRequestHeader('Connection') != 'close')
                $this->setHeader('Connection', 'keep-alive');
            else
                $this->setHeader('Connection', 'close');
            if(Pancake_Config::get('main.exposepancake') === true)
                $this->setHeader('Server', 'Pancake/' . PANCAKE_VERSION);
            if(!$this->getAnswerHeader('Content-Type'))
                $this->setHeader('Content-Type', 'text/html');
            $this->setHeader('Content-Length', strlen($this->getAnswerBody()));
            
            $answer = 'HTTP/'.$this->getProtocolVersion().' '.$this->getAnswerCode().' '.self::getCodeString($this->getAnswerCode())."\r\n";
            $answer .= $this->getAnswerHeaders();
            $answer .= "\r\n";
            $answer .= $this->getAnswerBody();
            
            return $answer;
        }
        
        public function setHeader($headerName, $headerValue) {
            $this->answerHeaders[$headerName] = $headerValue;
        }
        
        public function getRequestHeader($headerName) {
            return $this->requestHeaders[$headerName];
        }
        
        public function getRequestHeaders() {
            foreach($this->requestHeaders as $headerName => $headerValue) {
                $headers .= $headerName.': '.$headerValue."\r\n";
            }
            return $headers;
        }
        
        public function getProtocolVersion() {
            return $this->protocolVersion;
        }
        
        public function getAnswerHeaders() {
            foreach($this->answerHeaders as $headerName => $headerValue) {
                $headers .= $headerName.': '.$headerValue."\r\n";
            }
            return $headers;
        }
        
        public function getAnswerHeader($headerName) {
            return $this->answerHeaders[$headerName];
        }
        
        public function setAnswerCode($value) {
            return $this->answerCode = $value;
        }
        
        public function getAnswerCode() {
            return $this->answerCode;
        }
        
        public function getAnswerBody() {
            return $this->answerBody;
        }
        
        public function setAnswerBody($value) {
            return $this->answerBody = $value;
        }
        
        public function getRequestWorker() {
            return $this->requestWorker;
        }
        
        public static function getCodeString($code) {
            return self::$answerCodes[$code];
        }
    }                             
?>

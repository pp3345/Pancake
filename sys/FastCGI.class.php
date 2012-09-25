<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* FastCGI.class.php                                            */
	/* 2012 Yussuf Khalil                                           */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/
	
	#.if 0
	namespace Pancake;
	
	if(PANCAKE !== true)
		exit;
	#.endif
	
	#.define 'FCGI_BEGIN_REQUEST'       1
	#.define 'FCGI_ABORT_REQUEST'       2
	#.define 'FCGI_END_REQUEST'         3
	#.define 'FCGI_PARAMS'              4
	#.define 'FCGI_STDIN'               5
	#.define 'FCGI_STDOUT'              6
	#.define 'FCGI_STDERR'              7
	#.define 'FCGI_DATA'                8
	#.define 'FCGI_GET_VALUES'          9
	#.define 'FCGI_GET_VALUES_RESULT'  10
	#.define 'FCGI_UNKNOWN_TYPE'       11
	
	#.define 'FCGI_RESPONDER'  1
	#.define 'FCGI_AUTHORIZER' 2
	#.define 'FCGI_FILTER'     3
	#.define 'FCGI_KEEP_CONN'  1
	
	#.define 'FCGI_REQUEST_COMPLETE' 0
	#.define 'FCGI_CANT_MPX_CONN'    1
	#.define 'FCGI_OVERLOADED'       2
	#.define 'FCGI_UNKNOWN_ROLE'     3
	
	#.define 'FCGI_APPEND_DATA' 1048576
	
	class FastCGI {
		private static $instances = array();
		private $mimeTypes = array();
		public $socket;
		private $requestID = 0;
		private $requests = array();
		private $requestSockets = array();
		private $lastHeaders = array();
		
		public static function getInstance($name) {
			if(!isset(self::$instances[$name]))
				self::$instances[$name] = new self($name);
			return self::$instances[$name];
		}
		
		private function __construct($name) {
			$config = Config::get('fastcgi.' . $name);
			
			if(!$config)
				throw new \Exception('Undefined FastCGI configuration: ' . $name);
			
			$this->mimeTypes = $config['mimetypes'];
			
			switch($config['type']) {
				case 'ipv6':
					$this->socket = socket_create(/* .constant 'AF_INET6' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(!socket_connect($this->socket, $config['address'], $config['port']))
						throw new \Exception('Unable to connect to FastCGI upstream server');
					break;
				case 'ipv4':
					$this->socket = socket_create(/* .constant 'AF_INET' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(!socket_connect($this->socket, $config['address'], $config['port']))
						throw new \Exception('Unable to connect to FastCGI upstream server');
					break;
				default:
					$this->socket = socket_create(/* .constant 'AF_UNIX' */, /* .constant 'SOCK_STREAM' */, 0);
					if(!socket_connect($this->socket, $config['address']))
						throw new \Exception('Unable to connect to FastCGI upstream server');
			}
			
			socket_set_option($this->socket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_KEEPALIVE' */, 1);
		}
		
		public function getMimeTypes() {
			return $this->mimeTypes;
		}
		
		public function makeRequest(HTTPRequest $requestObject, $requestSocket) {
			/* FCGI_BEGIN_REQUEST */
			$requestIDInt = $this->requestID++;
			$requestID = ($requestIDInt < 256 ? "\0" . chr($requestIDInt) : chr($requestIDInt >> 8) . chr($requestIDInt));
			
			/* VERSION . TYPE . REQUEST_ID (2) . CONTENT_LENGTH (2) . PADDING_LENGTH . RESERVED . ROLE (2) . FLAG . RESERVED (5) */
			socket_write($this->socket, "\1\1" .  $requestID . "\0\x8\0\0\0\1\1\0\0\0\0\0");
			
			/* FCGI_PARAMS */
			$body = "\xf" . chr(strlen(/* .VHOST */->getDocumentRoot() . /* .REQUEST_FILE_PATH */)) . "SCRIPT_FILENAME" . /* .VHOST */->getDocumentRoot() . /* .REQUEST_FILE_PATH */;
			$body .= "\xc" . chr(strlen(/* .QUERY_STRING */)) . "QUERY_STRING" . /* .QUERY_STRING */;
			$body .= "\xe" . chr(strlen(/* .REQUEST_TYPE */)) . "REQUEST_METHOD" . /* .REQUEST_TYPE */;
			$body .= "\xb" . chr(strlen(/* .REQUEST_FILE_PATH */)) . "SCRIPT_NAME" . /* .REQUEST_FILE_PATH */;
			$body .= "\xf\x8SERVER_PROTOCOLHTTP/" . /* .PROTOCOL_VERSION */;
			$body .= "\x11\x7GATEWAY_INTERFACECGI/1.1";
			$body .= "\xb" . chr(strlen(/* .REQUEST_URI */)) . "REQUEST_URI" . /* .REQUEST_URI */;
			$body .= "\xb" . chr(strlen(/* .REMOTE_IP */)) . "REMOTE_ADDR" . /* .REMOTE_IP */;
			$body .= "\xb" . chr(strlen(/* .VHOST */->getHost())) . "SERVER_NAME" . /* .VHOST */->getHost();
			$body .= "\xb" . chr(strlen(/* .LOCAL_PORT */)) . "SERVER_PORT" . /* .LOCAL_PORT */;
			$body .= /* .eval 'return "\xf" . chr(strlen("Pancake\\\" . \Pancake\VERSION)) . "SERVER_SOFTWAREPancake\\\" . \Pancake\VERSION;' */;
			$body .= "\xb" . chr(strlen(/* .LOCAL_IP */)) . "SERVER_ADDR" . /* .LOCAL_IP */;

			if(/* .RAW_POST_DATA */) {
				$body .= "\xc" . chr(strlen($requestObject->getRequestHeader('Content-Type', false))) . "CONTENT_TYPE" . $requestObject->getRequestHeader('Content-Type', false);
				$body .= "\xe" . chr(strlen(/* .SIMPLE_GET_REQUEST_HEADER '"Content-Length"' */)) . "CONTENT_LENGTH" . /* .SIMPLE_GET_REQUEST_HEADER '"Content-Length"' */;
			}
			
			// HTTP header data
			foreach($requestObject->requestHeaders as $headerName => $headerValue) {
				$headerName = 'HTTP_' . str_replace('-', '_', strtoupper($headerName));
				$strlenName = strlen($headerName);
				$strlenValue = strlen($headerValue);
				if($strlenName < 128 && $strlenValue < 128)
					$body .= chr($strlenName) . chr($strlenValue) . $headerName . $headerValue;
				else 
					$body .= chr(($strlenName >> 24) | 128) . chr($strlenName >> 16) . chr($strlenName >> 8) . chr($strlenName) . chr(($strlenValue >> 24) | 128) . chr($strlenValue >> 16) . chr($strlenValue >> 8) . chr($strlenValue) . $headerName  . $headerValue;
			}
			
			$strlen = strlen($body);
			$strlen = ($strlen < 256 ? ("\0" . chr($strlen)) : (chr($strlen >> 8) . chr($strlen)));
			socket_write($this->socket, "\1\4" . $requestID . $strlen . "\0\0" . $body);
			
			/* Empty FCGI_PARAMS */
			socket_write($this->socket, "\1\4" . $requestID . "\0\0\0\0");
			
			if(/* .RAW_POST_DATA */) {
				$rawPostData = str_split(/* .RAW_POST_DATA */, 65535);
				
				foreach($rawPostData as $recordData) {
					/* FCGI_STDIN */
					$strlen = strlen($recordData);
					$contentLength = ($strlen < 256 ? ("\0" . $strlen) : (chr($strlen >> 8) . chr(strlen($recordData))));
					socket_write($this->socket, "\1\5" . $requestID . $contentLength . "\0\0" . $recordData);
				}
				
				/* Empty FCGI_STDIN */
				socket_write($this->socket, "\1\5" . $requestID . "\0\0\0\0");
			}
			
			$this->requests[$requestIDInt] = $requestObject;
			$this->requestSockets[$requestIDInt] = $requestSocket;
			
			if($this->requestID == 65536)
				$this->requestID = 0;
		}
		
		public function upstreamRecord($data) {
			if(strlen($data) < 8)
				return /* .constant 'FCGI_APPEND_DATA' */ | (8 - strlen($data));
			
			$contentLength = (int) ((ord($data[4]) << 8) + ord($data[5]));
			$requestID = (int) ((ord($data[2]) << 8) + ord($data[3]));
			$paddingLength = ord($data[6]);
			
			if(strlen($data) < (8 + $contentLength + $paddingLength))
				return /* .constant 'FCGI_APPEND_DATA' */ | (8 + $contentLength + $paddingLength - strlen($data));
			
			$type = (int) ord($data[1]);
			
			$data = substr($data, 8, $contentLength);
			
			switch($type) {
				case /* .constant 'FCGI_STDOUT' */:
					if(!$this->requests[$requestID]->anserBody && strpos($data, "\r\n\r\n")) {
						$contentBody = explode("\r\n\r\n", $data, 2);
						$this->requests[$requestID]->rawAnswerHeaderData .= $contentBody[0];
					}
					if(isset($contentBody) && isset($contentBody[1]))
						$this->requests[$requestID]->answerBody .= $contentBody[1];
					else if(!isset($contentBody))
						$this->requests[$requestID]->answerBody .= $data;
					return 8;
				case /* .constant 'FCGI_END_REQUEST' */:
					switch(ord($data[4])) {
						case /* .constant 'FCGI_REQUEST_COMPLETE' */:
							return array($this->requestSockets[$requestID], $this->requests[$requestID]);
					}
			}
		}
	}
?>
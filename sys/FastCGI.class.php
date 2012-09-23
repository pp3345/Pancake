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
	
	#.define 'FCGI_APPEND_DATA' 32768
	
	/*
typedef struct {
    unsigned char version;
    unsigned char type;
    unsigned char requestIdB1;
    unsigned char requestIdB0;
    unsigned char contentLengthB1;
    unsigned char contentLengthB0;
    unsigned char paddingLength;
    unsigned char reserved;
} FCGI_Header;
typedef struct {
            unsigned char version;
            unsigned char type;
            unsigned char requestIdB1;
            unsigned char requestIdB0;
            unsigned char contentLengthB1;
            unsigned char contentLengthB0;
            unsigned char paddingLength;
            unsigned char reserved;
            unsigned char contentData[contentLength];
            unsigned char paddingData[paddingLength];
        } FCGI_Record;
typedef struct {
            unsigned char roleB1;
            unsigned char roleB0;
            unsigned char flags;
            unsigned char reserved[5];
        } FCGI_BeginRequestBody;
	 */
	
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
			
			/*socket_set_block($this->socket);
			
			$body = "\0\1\1\0\0\0\0\0";
			
			socket_write($this->socket, "\1\1\0\1\0\x8\0\0" . $body);
			
			$body = "\xf\x22SCRIPT_FILENAME/var/vhosts/pancake/mybb/index.php";
			$body .= "\xc\x1QUERY_STRING?";
			$body .= "\xe\x3REQUEST_METHODGET";
			$body .= "\xb\xaSCRIPT_NAME/index.php";
			$body .= "\xf\x8SERVER_PROTOCOLHTTP/1.1";
			$body .= "\x11\x7GATEWAY_INTERFACECGI/1.1";
			$body .= "\xb\x1REQUEST_URI/";
			$body .= "\xb\x9REMOTE_ADDR127.0.0.1";
			$body .= "\xb\xdSERVER_NAME84.200.43.132";
			$body .= "\xb\x2SERVER_PORT90";
			$body .= "\xf\x11SERVER_SOFTWAREPancake/1.1-devel";
			$body .= "\xc\x0" . "CONTENT_TYPE";
			$body .= "\xe\x0" . "CONTENT_LENGTH";
			
			var_dump(strlen($body));

			socket_write($this->socket, "\1\4\0\1\x1\x2c\0\0" . $body);
			
			socket_write($this->socket, "\1\4\0\1\0\0\0\0");
			
			while(var_dump(socket_read($this->socket, 1024)));*/
		}
		
		public function getMimeTypes() {
			return $this->mimeTypes;
		}
		
		public function makeRequest(HTTPRequest $requestObject, $requestSocket) {
			/* FCGI_BEGIN_REQUEST */
			$requestIDInt = $this->requestID++;
			$requestID = ($requestIDInt < 256 ? "\0" . chr($requestIDInt) : "\0\0");
			
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

			// HTTP header data
			foreach($requestObject->requestHeaders as $headerName => $headerValue) {
				$headerName = 'HTTP_' . str_replace('-', '_', strtoupper($headerName));
				$headerValue = str_split($headerValue, 254);
				foreach($headerValue as $headerValuePart)
					$body .= chr(strlen($headerName)) . chr(strlen($headerValuePart)) . $headerName . $headerValuePart;
			}
			
			if(/* .RAW_POST_DATA */) {
				$body .= "\xc" . chr(strlen($requestObject->getRequestHeader('Content-Type', false))) . "CONTENT_TYPE" . $requestObject->getRequestHeader('Content-Type', false);
				$body .= "\xe" . chr(strlen(/* .SIMPLE_GET_REQUEST_HEADER '"Content-Length"' */)) . "CONTENT_LENGTH" . /* .SIMPLE_GET_REQUEST_HEADER '"Content-Length"' */;
			}

			$strlen = (strlen($body) < 256 ? ("\0" . chr(strlen($body))) : (/* .eval 'return chr(256 >> 8);' */ . chr(strlen($body) & 255)));
			var_dump($body);
			socket_write($this->socket, "\1\4" . $requestID . $strlen . "\0\0" . $body);
			
			/* Empty FCGI_PARAMS */
			socket_write($this->socket, "\1\4" . $requestID . "\0\0\0\0");
			
			if(/* .RAW_POST_DATA */) {
				$rawPostData = str_split(/* .RAW_POST_DATA */, 65534);
				
				foreach($rawPostData as $recordData) {
					/* FCGI_STDIN */
					$contentLength = (strlen($recordData) < 256 ? ("\0" . chr(strlen($recordData))) : (/* .eval 'return chr(256 >> 8);' */ . chr(strlen($recordData) & 255)));
					socket_write($this->socket, "\1\5" . $requestID . $contentLength . "\0\0" . $recordData);
				}
				
				/* Empty FCGI_STDIN */
				socket_write($this->socket, "\1\5" . $requestID . "\0\0\0\0");
			}
			
			$this->requests[$requestIDInt] = $requestObject;
			$this->requestSockets[$requestIDInt] = $requestSocket;
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
						/*
						$headers = explode("\r\n", $contentBody[0]);
						foreach($headers as $header) {
							$header = explode(":", $header, 2);
							$this->requests[$requestID]->answerHeaders[trim($header[0])] = trim($header[1]);
						}
						*/
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
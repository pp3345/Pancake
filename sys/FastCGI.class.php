<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* FastCGI.class.php                                            */
	/* 2012 - 2013 Yussuf Khalil                                    */
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
		private $address = "";
		private $port = 0;
		private $type = "";

		public static function getInstance($name) {
			if(!isset(self::$instances[$name]))
				self::$instances[$name] = new self($name);
			return self::$instances[$name];
		}

		private function __construct($name) {
			$config = Config::get('fastcgi.' . strtolower($name));

			if(!$config)
				throw new \Exception('Undefined FastCGI configuration: ' . $name);

			$this->mimeTypes = $config['mimetypes'];
			$this->address = $config['address'];
			$this->port = $config['port'];
			$this->type = strtolower($config['type']);

			$this->connect();
		}

		private function connect() {
			$this->requestID = 0;

			switch($this->type) {
				case 'ipv6':
					$this->socket = Socket(/* .constant 'AF_INET6' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(Connect($this->socket, /* .constant 'AF_INET6' */, $this->address, $this->port)) {
					   trigger_error('Unable to connect to FastCGI upstream server at ipv6:' . $this->address . ':' . $this->port, /* .constant 'E_USER_ERROR' */);
						return false;
					}
					break;
				case 'ipv4':
					$this->socket = Socket(/* .constant 'AF_INET' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(Connect($this->socket, /* .AF_INET */, $this->address, $this->port)) {
						trigger_error('Unable to connect to FastCGI upstream server at ipv4:' . $this->address . ':' . $this->port, /* .constant 'E_USER_ERROR' */);
						return false;
					}
					break;
				default:
					$this->socket = Socket(/* .constant 'AF_UNIX' */, /* .constant 'SOCK_STREAM' */, 0);
					if(Connect($this->socket, /* .AF_UNIX */, $this->address)) {
						trigger_error('Unable to connect to FastCGI upstream server at unix:' . $this->address, /* .constant 'E_USER_ERROR' */);
						return false;
					}
			}

			return true;
		}

		public function getMimeTypes() {
			return $this->mimeTypes;
		}

		public function makeRequest(HTTPRequest $requestObject, $requestSocket) {
			/* FCGI_BEGIN_REQUEST */
			$requestIDInt = ++$this->requestID;
			$requestID = ($requestIDInt < 256 ? "\0" . chr($requestIDInt) : chr($requestIDInt >> 8) . chr($requestIDInt));

			if(!$this->socket)
				$this->connect();

			/* VERSION . TYPE . REQUEST_ID (2) . CONTENT_LENGTH (2) . PADDING_LENGTH . RESERVED . ROLE (2) . FLAG . RESERVED (5) */
			if(!@Write($this->socket, "\1\1" .  $requestID . "\0\x8\0\0\0\1\1\0\0\0\0\0")) {
				if(!$this->connect()) {
					$requestObject->invalidRequest(new invalidHTTPRequestException("Failed to connect to FastCGI upstream server", 502));
					return false;
				} else
					Write($this->socket, "\1\1" .  $requestID . "\0\x8\0\0\0\1\1\0\0\0\0\0");
			}

			/* FCGI_PARAMS */
			$body = "\xf" . chr(strlen(/* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath)) . "SCRIPT_FILENAME" . /* .VHOST_DOCUMENT_ROOT */ . $requestObject->requestFilePath;
			$body .= "\xc" . chr(strlen($requestObject->queryString)) . "QUERY_STRING" . $requestObject->queryString;
			$body .= "\xe" . chr(strlen($requestObject->requestType)) . "REQUEST_METHOD" . $requestObject->requestType;
			$body .= "\xb" . chr(strlen($requestObject->requestFilePath)) . "SCRIPT_NAME" . $requestObject->requestFilePath;
			$body .= "\xf\x8SERVER_PROTOCOLHTTP/" . $requestObject->protocolVersion;
			$body .= "\x11\x7GATEWAY_INTERFACECGI/1.1";
			$body .= "\xb" . chr(strlen($requestObject->originalRequestURI)) . "REQUEST_URI" . $requestObject->originalRequestURI;
			$body .= "\xc" . chr(strlen($requestObject->requestURI)) . "DOCUMENT_URI" . $requestObject->requestURI;
			$body .= "\xb" . chr(strlen($requestObject->remoteIP)) . "REMOTE_ADDR" . $requestObject->remoteIP;
			$body .= "\xb" . chr(strlen(/* .VHOST */->listen[0])) . "SERVER_NAME" . /* .VHOST */->listen[0];
			$body .= "\xb" . chr(strlen($requestObject->localPort)) . "SERVER_PORT" . $requestObject->localPort;
			$body .= /* .eval 'return "\xf" . chr(strlen("Pancake/" . \Pancake\VERSION)) . "SERVER_SOFTWAREPancake/" . \Pancake\VERSION;' */;
			$body .= "\xb" . chr(strlen($requestObject->localIP)) . "SERVER_ADDR" . $requestObject->localIP;
			if($requestObject->pathInfo) {
				$body .= "\x9" . chr(strlen($requestObject->pathInfo)) . "PATH_INFO" . $requestObject->pathInfo;
				$body .= "\xf" . chr(strlen($requestObject->vHost->documentRoot . $requestObject->pathInfo)) . "PATH_TRANSLATED" . $requestObject->vHost->documentRoot . $requestObject->pathInfo;
			}

			if($requestObject->rawPOSTData) {
				$body .= "\xc" . chr(strlen($requestObject->requestHeaders["content-type"])) . "CONTENT_TYPE" . $requestObject->requestHeaders["content-type"];
				$body .= "\xe" . chr(strlen($requestObject->requestHeaders["content-length"])) . "CONTENT_LENGTH" . $requestObject->requestHeaders["content-length"];
			}

			// HTTP header data
			foreach($requestObject->requestHeaders as $headerName => $headerValue) {
				$headerName = 'HTTP_' . str_replace('-', '_', strtoupper($headerName));
				if($headerName == 'HTTP_CONNECTION'
				|| $headerName == 'HTTP_CONTENT_TYPE'
				|| $headerName == 'HTTP_CONTENT_LENGTH'
				|| $headerName == 'HTTP_AUTHORIZATION')
					continue;
				$strlenName = strlen($headerName);
				$strlenValue = strlen($headerValue);
				if($strlenName < 128 && $strlenValue < 128)
					$body .= chr($strlenName) . chr($strlenValue) . $headerName . $headerValue;
				else
					$body .= chr(($strlenName >> 24) | 128) . chr($strlenName >> 16) . chr($strlenName >> 8) . chr($strlenName) . chr(($strlenValue >> 24) | 128) . chr($strlenValue >> 16) . chr($strlenValue >> 8) . chr($strlenValue) . $headerName  . $headerValue;
			}

			$strlen = strlen($body);
			$strlen = ($strlen < 256 ? ("\0" . chr($strlen)) : (chr($strlen >> 8) . chr($strlen)));
			Write($this->socket, "\1\4" . $requestID . $strlen . "\0\0" . $body);

			/* Empty FCGI_PARAMS */
			Write($this->socket, "\1\4" . $requestID . "\0\0\0\0");

			if($requestObject->rawPOSTData) {
				$rawPostData = str_split($requestObject->rawPOSTData, 65535);

				foreach($rawPostData as $recordData) {
					/* FCGI_STDIN */
					$strlen = strlen($recordData);
					$contentLength = ($strlen < 256 ? ("\0" . chr($strlen)) : (chr($strlen >> 8) . chr($strlen)));
					Write($this->socket, "\1\5" . $requestID . $contentLength . "\0\0" . $recordData);
				}

				/* Empty FCGI_STDIN */
				Write($this->socket, "\1\5" . $requestID . "\0\0\0\0");
			}

			$requestObject->fCGISocket = (int) $this->socket;

			$this->requests[$requestIDInt] = $requestObject;
			$this->requestSockets[$requestIDInt] = $requestSocket;

			if($this->requestID == 65535)
				$this->requestID = 0;
		}

		public function upstreamRecord($data, $socketID) {
			if($data === "" || $data === false) {
				/* Upstream server closed connection */
				foreach($this->requests as $requestID => $requestObject) {
					if($requestObject->fCGISocket != $socketID)
						continue;

					$requestObject->invalidRequest(new invalidHTTPRequestException("The FastCGI upstream server unexpectedly closed the network connection.", 502));

					$retval = array($this->requestSockets[$requestID], $requestObject, true);
					unset($this->requestSockets[$requestID], $this->requests[$requestID]);
					return $retval;
				}

				$this->connect();
				return 0;
			}
			if(strlen($data) < 8)
				return /* .constant 'FCGI_APPEND_DATA' */ | (8 - strlen($data));

			$contentLength = (ord($data[4]) << 8) + ord($data[5]);
			$requestID = (ord($data[2]) << 8) + ord($data[3]);
			$requestObject = $this->requests[$requestID];
			$paddingLength = ord($data[6]);

			if(strlen($data) < (8 + $contentLength + $paddingLength))
				return /* .constant 'FCGI_APPEND_DATA' */ | (8 + $contentLength + $paddingLength - strlen($data));

			$type = ord($data[1]);

			$data = substr($data, 8, $contentLength);

			switch($type) {
				case /* .constant 'FCGI_STDOUT' */:
					if(!$requestObject->headerDataCompleted) {
						if(strpos($data, "\r\n\r\n"))
							$requestObject->headerDataCompleted = true;
						$contentBody = explode("\r\n\r\n", $data, 2);
						foreach(explode("\r\n", $contentBody[0]) as $header) {
							list($headerName, $headerValue) = explode(":", $header, 2);
							if($headerName == 'Status') {
								$requestObject->answerCode = (int) $headerValue;
								continue;
							}
							$requestObject->setHeader(trim($headerName), trim($headerValue), false);
						}

						if(isset($contentBody[1]))
							$requestObject->answerBody .= $contentBody[1];
						return 8;
					}

					$requestObject->answerBody .= $data;

					return 8;
				case /* .constant 'FCGI_END_REQUEST' */:
					switch(ord($data[4])) {
						default:
							$requestObject->invalidRequest(new invalidHTTPRequestException('The upstream server is currently overloaded.', 502));
							// fallthrough
						case /* .constant 'FCGI_REQUEST_COMPLETE' */:
							$retval = array($this->requestSockets[$requestID], $requestObject);
							unset($this->requestSockets[$requestID], $this->requests[$requestID]);
							return $retval;
					}
			}
		}
	}
?>
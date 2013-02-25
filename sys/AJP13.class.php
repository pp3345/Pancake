<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* AJP13.class.php                                            	*/
	/* 2012 - 2013 Yussuf Khalil                                    */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/

	#.if 0
	namespace Pancake;

	if(PANCAKE !== true)
		exit;
	#.endif

	#.AJP13_HTTP_OPTIONS = "\x1"
	#.AJP13_HTTP_GET = "\x2"
	#.AJP13_HTTP_HEAD = "\x3"
	#.AJP13_HTTP_POST = "\x4"

	#.AJP13_FORWARD_REQUEST = "\x2"

	#.AJP13_SEND_BODY_CHUNK = "\x3"
	#.AJP13_SEND_HEADERS = "\x4"
	#.AJP13_END_RESPONSE = "\x5"
	#.AJP13_GET_BODY_CHUNK = "\x6"
	#.AJP13_CPONG = "\x9"

	#.AJP13_APPEND_DATA = 1048576

	class AJP13 {
		private static $instances = array();
		private $sockets = array();
		private $freeSockets = array();
		private $address = "";
		private $port = 0;
		private $type = "";
		private $maxConcurrent = null;
		private $requests = array();
		private $requestSockets = array();

		public static function getInstance($name) {
			if(!isset(self::$instances[$name]))
				self::$instances[$name] = new self($name);
			return self::$instances[$name];
		}

		private function __construct($name) {
			$config = Config::get('ajp13.' . $name);

			if(!$config)
				throw new \Exception('Undefined AJP13 configuration: ' . $name);

			$this->address = $config['address'];
			$this->port = $config['port'];
			$this->type = strtolower($config['type']);
			if($config['maxconcurrent'])
				$this->maxConcurrent = (int) $config['maxconcurrent'];
		}

		private function connect() {
			if($this->maxConcurrent && count($this->sockets) >= $this->maxConcurrent)
				return false;

			switch($this->type) {
				case 'ipv6':
					$socket = socket_create(/* .constant 'AF_INET6' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(!socket_connect($socket, $this->address, $this->port)) {
						trigger_error('Unable to connect to AJP13 upstream server at ipv6:' . $this->address . ':' . $this->port, /* .constant 'E_USER_ERROR' */);
						return false;
					}
					break;
				case 'ipv4':
					$socket = socket_create(/* .constant 'AF_INET' */, /* .constant 'SOCK_STREAM' */, /* .constant 'SOL_TCP' */);
					if(!socket_connect($socket, $this->address, $this->port)) {
						trigger_error('Unable to connect to AJP13 upstream server at ipv4:' . $this->address . ':' . $this->port, /* .constant 'E_USER_ERROR' */);
						return false;
					}
					break;
				default:
					$socket = socket_create(/* .constant 'AF_UNIX' */, /* .constant 'SOCK_STREAM' */, 0);
					if(!socket_connect($socket, $this->address)) {
						trigger_error('Unable to connect to AJP13 upstream server at unix:' . $this->address, /* .constant 'E_USER_ERROR' */);
						return false;
					}
			}

			socket_set_option($socket, /* .constant 'SOL_SOCKET' */, /* .constant 'SO_KEEPALIVE' */, 1);
			$this->sockets[(int) $socket] = $socket;
			return $socket;
		}

		public function makeRequest(HTTPRequest $requestObject, $requestSocket) {
			if($this->freeSockets) {
				foreach($this->freeSockets as $id => $socket) {
					unset($this->freeSockets[$id]);
					break;
				}
			} else if(!($socket = $this->connect())) {
				$requestObject->invalidRequest(new invalidHTTPRequestException('Failed to connect to AJP13 upstream server', 502));
				return false;
			}

			switch($requestObject->requestType) {
				case 'GET':
					$method = /* .AJP13_HTTP_GET */;
					break;
				case 'POST':
					$method = /* .AJP13_HTTP_POST */;
					break;
				#.if true === #.Pancake\Config::get('main.allowhead')
				case 'HEAD':
					$method = /* .AJP13_HTTP_HEAD */;
					break;
				#.endif
				#.if true === #.Pancake\Config::get('main.allowoptions')
				case 'OPTIONS':
					$method = /* .AJP13_HTTP_OPTIONS */;
				#.endif
			}

			$strlenURI = strlen($requestObject->requestURI);
			$strlenAddr = strlen($requestObject->remoteIP);
			$strlenServerName = strlen($requestObject->vHost->listen[0]);
			$headers = "";
			$headerCount = 0;

			foreach($requestObject->requestHeaders as $headerName => $headerValue) {
				$strlenValue = strlen($headerValue);
				switch(strtolower($headerName)) {
					case 'accept':
						$headers .= "\xa0\x01";
						break;
					case 'accept-charset':
						$headers .= "\xa0\x02";
						break;
					case 'accept-encoding':
						$headers .= "\xa0\x03";
						break;
					case 'accept-language':
						$headers .= "\xa0\x04";
						break;
					case 'authorization':
						$headers .= "\xa0\x05";
						break;
					case 'connection':
						$headers .= "\xa0\x06";
						break;
					case 'content-type':
						$headers .= "\xa0\x07";
						break;
					case 'content-length':
						$headers .= "\xa0\x08";
						break;
					case 'cookie':
						$headers .= "\xa0\x09";
						break;
						/* cookie2? */
					case 'host':
						$headers .= "\xa0\x0b";
						break;
					case 'pragma':
						$headers .= "\xa0\x0c";
						break;
					case 'referer':
					case 'referrer':
						$headers .= "\xa0\x0d";
						break;
					case 'user-agent':
						$headers .= "\xa0\x0e";
						break;
					default:
						$strlenHeaderName = strlen($headerName);
						$headers .= chr($strlenHeaderName >> 8) . chr($strlenHeaderName) . $headerName . "\x0";
				}
				$headerCount++;
				$strlenValue = strlen($headerValue);
				$headers .= chr($strlenValue >> 8) . chr($strlenValue) . $headerValue . "\x0";
			}

			$body = $method 																// method byte
			. "\x0\x8HTTP/" . $requestObject->protocolVersion . "\x0" 						// protocol string + terminating \0
			. chr($strlenURI >> 8) . chr($strlenURI) . $requestObject->requestURI . "\x0"	// URI string + terminating \0
			. chr($strlenAddr >> 8) . chr($strlenAddr) . $requestObject->remoteIP . "\x0"	// Remote addr string + terminating \0
			. chr($strlenAddr >> 8) . chr($strlenAddr) . $requestObject->remoteIP . "\x0"	// Remote host string + terminating \0 - I send the IP address again for performance reasons
			. chr($strlenServerName >> 8) . chr($strlenServerName) . $requestObject->vHost->listen[0] . "\x0"	// Server name string + terminating \0
			. chr($requestObject->localPort >> 8) . chr($requestObject->localPort)			// Local port integer
			. "\x0"																			// Is SSL? boolean
			. chr($headerCount >> 8) . chr($headerCount)									// Header count integer
			. $headers;

			$strlenBody = strlen($body) + 2;

			doWrite:
			if(socket_write($socket, "\x12\x34" . chr($strlenBody >> 8) . chr($strlenBody) . "\x02" . $body . "\xff") === false) {
				unset($this->sockets[(int) $socket]);
				if($this->freeSockets) {
					foreach($this->freeSockets as $id => $socket) {
						unset($this->freeSockets[$id]);
						break;
					}
					goto doWrite;
				} else if(!($socket = $this->connect())) {
					$requestObject->invalidRequest(new invalidHTTPRequestException('Failed to connect to AJP13 upstream server', 502));
					return false;
				}
			}

			if($requestObject->rawPOSTData) {
				$string = substr($requestObject->rawPOSTData, 0, 8186);
				$strlen = strlen($string);
				socket_write($socket, "\x12\x34" . chr(($strlen + 2) >> 8) . chr($strlen + 2) . chr($strlen >> 8) . chr($strlen) . $string);
				$requestObject->rawPOSTData = substr($requestObject->rawPOSTData, $strlen);
			}

			$this->requests[(int) $socket] = $requestObject;
			$this->requestSockets[(int) $socket] = $requestSocket;

			return $socket;
		}

		public function upstreamRecord($data, $socket) {
			$socketID = (int) $socket;
			$requestObject = $this->requests[$socketID];

			if($data === "") {
				/* Upstream server closed connection */

				if($requestObject) {
					$requestObject->invalidRequest(new invalidHTTPRequestException("The AJP13 upstream server unexpectedly closed the network connection.", 502));

					$retval = array($this->requestSockets[$socketID], $requestObject);
					unset($this->requestSockets[$socketID], $this->requests[$socketID]);
					unset($this->sockets[$socketID], $this->freeSockets[$socketID]);
					return $retval;
				}

				$this->connect();
				return 0;
			}

			if(strlen($data) < 5)
				return /* .AJP13_APPEND_DATA */ | (5 - strlen($data));

			$contentLength = (ord($data[2]) << 8) + ord($data[3]);

			if(strlen($data) < $contentLength + 4)
				return /* .AJP13_APPEND_DATA */ | ($contentLength + 4 - strlen($data));

			switch($data[4]) {
				case /* .AJP13_GET_BODY_CHUNK */:
					$length = (ord($data[5]) << 8) + ord($data[6]);
					if($requestObject->rawPOSTData) {
						$string = substr($requestObject->rawPOSTData, 0, $length);
						$strlen = strlen($string);
						socket_write($socket, "\x12\x34" . chr(($strlen + 2) >> 8) . chr($strlen + 2) . chr($strlen >> 8) . chr($strlen) . $string);
						$requestObject->rawPOSTData = substr($requestObject->rawPOSTData, $strlen);
					} else
						socket_write($socket, "\x12\x34\x0\x0");
                    
					return 5;
				case /* .AJP13_SEND_BODY_CHUNK */:
					$requestObject->answerBody .= substr($data, 7, (ord($data[5]) << 8) + ord($data[6]));
					return 5;
				case /* .AJP13_END_RESPONSE */:
					if($data[5] == "\x01") {
						$this->freeSockets[$socketID] = $socket;
					} else {
						@socket_shutdown($socket);
						socket_close($socket);
						unset($this->sockets[$socketID]);
					}
					$retval = array($this->requestSockets[$socketID], $requestObject);
					unset($this->requestSockets[$socketID], $this->requests[$socketID]);
					return $retval;
				case /* .AJP13_SEND_HEADERS */:
					// Get HTTP status code
					$requestObject->answerCode = (ord($data[5]) << 8) + ord($data[6]);

					// Read headers
					$pos = 12 + (ord($data[7]) << 8) + ord($data[8]);
					$totalLength = $contentLength + 4;
					while($pos < $totalLength) {
						if($data[$pos] == "\xa0") {
							$valueLength = (ord($data[$pos + 2]) << 8) + ord($data[$pos + 3]);
							$value = substr($data, $pos + 4, $valueLength);
							switch($data[$pos + 1]) {
								case "\x01":
									$requestObject->setHeader('Content-Type', $value);
									break;
								case "\x02":
									$requestObject->setHeader('Content-Language', $value);
									break;
								case "\x03":
									$requestObject->setHeader('Content-Length', $value);
									break;
								case "\x04":
									$requestObject->setHeader('Date', $value);
									break;
								case "\x05":
									$requestObject->setHeader('Last-Modified', $value);
									break;
								case "\x06":
									$requestObject->setHeader('Location', $value);
									break;
								case "\x07":
									$requestObject->setHeader('Set-Cookie', $value);
									break;
								case "\x08":
									$requestObject->setHeader('Set-Cookie2', $value);
									break;
								case "\x09":
									$requestObject->setHeader('Servlet-Engine', $value);
									break;
								case "\x0a":
									$requestObject->setHeader('Status', $value);
									break;
								case "\x0b":
									$requestObject->setHeader('WWW-Authenticate', $value);
									break;
							}

							$pos += $valueLength + 5;
						} else {
							$headerLength = (ord($data[$pos]) << 8) + ord($data[$pos + 1]);
							$headerName = substr($data, $pos + 2, $headerLength);
							$pos += $headerLength + 3;
							$valueLength = (ord($data[$pos]) << 8) + ord($data[$pos + 1]);
							$value = substr($data, $pos + 2, $valueLength);
							$requestObject->setHeader($headerName, $value);
							$pos += $valueLength + 3;
						}
					}
					return 5;
			}
		}
	}
?>
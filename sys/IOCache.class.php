<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* IOCache.class.php                                        	*/
	/* 2012 - 2013 Yussuf Khalil                                    */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/
	
	#.if 0
	namespace Pancake;
	
	if(PANCAKE !== true)
		exit;
	#.endif
	
	#.define 'IOCACHE_REMOTE_ORIGIN' 1
	#.define 'IOCACHE_LOCAL_ORIGIN' 2
	
	#.define 'IOCACHE_SOCKET_BUFFER_PRIORITY' 10
	#.define 'IOCACHE_POST_BUFFER_PRIORITY' 9
	#.define 'IOCACHE_FILE_BUFFER_PRIORITY' 8
	
	#.if #.bool #.call 'Pancake\Config::get' 'main.debugiocache'
		#.define 'IOCACHE_DEBUG' true
	#.endif
	
	#.define 'IOCACHE_MAX_RAM' #.number #.call 'Pancake\Config::get' 'main.iocacheram'
	
	#.macro 'IOCACHE_HAS_FREE_RAM', '($this->allocatedMemory < ' IOCACHE_MAX_RAM ')'
	#.macro 'IOCACHE_HAS_NO_FREE_RAM', '($this->allocatedMemory >= ' IOCACHE_MAX_RAM ')'
	#.macro 'IOCACHE_FREE_RAM', '(' IOCACHE_MAX_RAM ' - $this->allocatedMemory)'
	
	#.longDefine 'MACRO_CODE'
	$this->lowestPriorityBuffer = null;
	if($this->buffers) {
		foreach($this->buffers[min(array_keys($this->buffers))] as $lowPriorityBuffer) {
			#.if IOCACHE_MAX_RAM >= 16384
			if($buffer->cachedBytes > 256) {
			#.else
			if($buffer->cachedBytes) {
			#.endif
				$this->lowestPriorityBuffer = $lowPriorityBuffer;
				break;
			}
		}
	}
	#.endLongDefine
	
	#.macro 'IOCACHE_RESET_LOWEST_PRIORITY_BUFFER' MACRO_CODE

	#.longDefine 'MACRO_CODE'
	if($buffer->cachedBytes > $bytes) {
		#.IOCACHE_DEALLOCATE_BUFFER_TO_DISK "\$buffer" "\$bytes"
	} else {
		if($buffer->originFile) {
			#.IOCACHE_DEALLOCATE_BUFFER "\$buffer"
		} else {
			#.IOCACHE_DEALLOCATE_BUFFER_TO_DISK "\$buffer" "\$buffer->cachedBytes"
		}
	}
	#.endLongDefine
	
	#.macro 'IOCACHE_DEALLOCATE_RAM_FROM_BUFFER' MACRO_CODE '$buffer' '$bytes'
	
	#.longDefine 'MACRO_CODE'
	$this->allocatedMemory -= $buffer->cachedBytes;
	unset($this->buffers[$buffer->priority][array_search($buffer, $this->buffers[$buffer->priority])]);
	#.endLongDefine
	
	#.macro 'IOCACHE_DEALLOCATE_BUFFER' MACRO_CODE '$buffer'
	
	#.longDefine 'MACRO_CODE'
	if(isset($buffer->fileCacheLength) && $buffer->fileCacheLength) {
		fseek($this->bufferFileHandle, $buffer->fileCacheBeginOffset);
		$bufferedBytes = fread($this->bufferFileHandle, $buffer->fileCacheLength);
		fseek($this->bufferFileHandle, $this->bufferFileEnd);
	}
	$buffer->fileCacheBeginOffset = $this->bufferFileEnd;
	$buffer->fileCacheLength += $bytes;
	$this->bufferFileEnd += $buffer->fileCacheLength;
	$deallocate = $bytes;
	$this->allocatedMemory -= $bytes;
	$buffer->cachedBytes -= $deallocate;
	fwrite($this->bufferFileHandle, substr($buffer->bytes, $buffer->cachedBytes) . $bufferedBytes);
	$buffer->bytes = substr($buffer->bytes, 0, $buffer->cachedBytes);
	#.endLongDefine
	
	#.macro 'IOCACHE_DEALLOCATE_BUFFER_TO_DISK' MACRO_CODE '$buffer' '$bytes'
	
	#.longDefine 'MACRO_CODE'
	$buffer->cachedBytes -= $bytes;
	if(!$buffer->cachedBytes)
		$this->allocatedMemory -= $bytes;
	$buffer->bytes = substr($buffer->bytes, 0, $buffer->cachedBytes);
	#.endLongDefine
	
	#.macro 'IOCACHE_DEALLOCATE_BUFFER_BYTES' MACRO_CODE '$buffer' '$bytes'
	
	#.macro 'IOCACHE_BUFFER_TOTAL_BYTES' '($buffer->cachedBytes + $buffer->fileCacheLength)' '$buffer'
	
	class IOCache {
		private $buffers = array();
		private $lowestPriorityBuffer = null;
		private $allocatedMemory = 0;
		private $cachedOrigins = array();
		private $bufferFileHandle = null;
		private $bufferFileName = "";
		private $bufferFileEnd = 0;
		
		public function __construct() {
			$this->bufferFileName = tempnam(/* .call 'Pancake\Config::get' 'main.tmppath' */, 'IOCACHE');
			$this->bufferFileHandle = fopen($this->bufferFileName, 'r+');
		}
		
		public function allocateBuffer($priority = 0, $bytes = "", $originFile = null) {
			$this->buffers[$priority][] = $buffer = new \stdClass;
			$buffer->priority = $priority;
			$buffer->originFile = $originFile;
			#.ifdef 'IOCACHE_DEBUG'
				out('Allocate new buffer with priority ' . $priority . ', ' . strlen($bytes) . ' bytes given, originFile ' . var_export($originFile, true));
			#.endif
			
			if($bytes) {
				$this->addBytes($buffer, $bytes);
			}
			
			if(!$this->lowestPriorityBuffer)
				$this->lowestPriorityBuffer = $buffer;
			
			#.ifdef 'IOCACHE_DEBUG'
				out('Allocate finished - Free: ' . /* .IOCACHE_FREE_RAM */ . ' bytes - Total allocated: ' . $this->allocatedMemory . ' bytes');
			#.endif
			
			return $buffer;
		}
		
		public function deallocateBuffer(\stdClass $buffer) {
			#.ifdef 'IOCACHE_DEBUG'
				out('Deallocate buffer');
			#.endif
			#.IOCACHE_DEALLOCATE_BUFFER '$buffer'
			if($buffer == $this->lowestPriorityBuffer || !$this->lowestPriorityBuffer) {
				#.IOCACHE_RESET_LOWEST_PRIORITY_BUFFER
			}
			#.ifdef 'IOCACHE_DEBUG'
				out('Deallocate finished - Free: ' . /* .IOCACHE_FREE_RAM */ . ' bytes - Total allocated: ' . $this->allocatedMemory . ' bytes');
			#.endif
		}
		
		public function addBytes(\stdClass $buffer, $bytes) {
			$bytesLength = strlen($bytes);
			#.ifdef 'IOCACHE_DEBUG'
				out('Add ' . $bytesLength . ' bytes to buffer');
			#.endif
			
			if(/* .IOCACHE_FREE_RAM */ < $bytesLength) {
				#.ifdef 'IOCACHE_DEBUG'
					out('Not enough RAM free for full buffering');
				#.endif
				// Try to free RAM
				if($this->lowestPriorityBuffer && $this->lowestPriorityBuffer->priority <= $buffer->priority) {
					$requiredBytes = $bytesLength - /* .IOCACHE_FREE_RAM */;
					if($this->lowestPriorityBuffer->cachedBytes < $requiredBytes) {
						$overBytes = $requiredBytes - $this->lowestPriorityBuffer->cachedBytes;
						#.ifdef 'IOCACHE_DEBUG'
							out($overBytes . ' bytes over after freeing RAM from lowest priority buffer');
						#.endif
					}
					
					#.IOCACHE_DEALLOCATE_RAM_FROM_BUFFER '$this->lowestPriorityBuffer' '$requiredBytes'
					
					if(isset($overBytes)) {
						if($this->lowestPriorityBuffer->priority == $buffer->priority)
							$this->lowestPriorityBuffer = $buffer;
						else {
							#.ifdef 'IOCACHE_DEBUG'
								out('Reset lowest priority buffer');
							#.endif
							#.IOCACHE_RESET_LOWEST_PRIORITY_BUFFER
						}
						
						$buffer->bytes = $bytes;
						$buffer->cachedBytes = $bytesLength;
						$this->allocatedMemory += $bytesLength;
						#.IOCACHE_DEALLOCATE_BUFFER_TO_DISK '$buffer' '$overBytes'
					} else {
						$buffer->bytes = $bytes;
						$buffer->cachedBytes = $bytesLength;
						$this->allocatedMemory += $bytesLength;
					}
				} else {
					$buffer->bytes = $bytes;
					$buffer->cachedBytes = $bytesLength;
					$this->allocatedMemory += $bytesLength;
					#.IOCACHE_DEALLOCATE_BUFFER_TO_DISK '$buffer' 'abs(/* .IOCACHE_FREE_RAM */)'
				}
			} else {
				$buffer->bytes = $bytes;
				$buffer->cachedBytes = $bytesLength;
				$this->allocatedMemory += $bytesLength;
			}
			
			#.ifdef 'IOCACHE_DEBUG'
				out('Add ' . $bytesLength . ' bytes finished - Free: ' . /* .IOCACHE_FREE_RAM */ . ' bytes - Total allocated: ' . $this->allocatedMemory . ' bytes');
			#.endif
		}
		
		public function getBytes(\stdClass $buffer, $bytes = 0, $offset = 0) {
			#.ifdef 'IOCACHE_DEBUG'
			out('Get ' . $bytes . ' bytes from buffer');
			#.endif
		
			if(!$bytes) {
				if($buffer->bytes)
					return $buffer->bytes;
				else if($buffer->fileCacheLength) {
					fseek($this->bufferFileHandle, $buffer->fileCacheBeginOffset);
					return fread($this->bufferFileHandle, $buffer->fileCacheLength);
				}
			} else if($bytes == -1) {
				if($buffer->fileCacheLength) {
					fseek($this->bufferFileHandle, $buffer->fileCacheBeginOffset);
					return $buffer->bytes . fread($this->bufferFileHandle, $buffer->fileCacheLength);
				} else
					return $buffer->bytes;
			} else {
				if($buffer->cachedBytes >= $bytes)
					return substr($buffer->bytes, 0, $bytes);
				else if($buffer->fileCacheLength) {
					fseek($this->bufferFileHandle, $buffer->fileCacheBeginOffset);
					return $buffer->bytes . fread($this->bufferFileHandle, $bytes - $buffer->cachedBytes);
				} else if($buffer->bytes)
					return $buffer->bytes;
			}
		
			return null;
		}
		
		public function setBytes(\stdClass $buffer, $bytes) {
			#.ifdef 'IOCACHE_DEBUG'
				out('Set ' . strlen($bytes) . ' bytes to buffer');
			#.endif
			$buffer->bytes = "";
			$this->allocatedMemory -= $buffer->cachedBytes;
			$buffer->cachedBytes = 0;
			$this->addBytes($buffer, $bytes);
		}
	}
	
	/*
	class IOCacheBuffer {
		public $cachedBytesOffset = 0;
		public $cachedBytes = 0;
		public $fileCacheBeginOffset = 0;
		public $fileCacheLength = 0;
		public $priority = 0;
		public $bytes = "";
		public $calls = 0;
		public $originFile = "";
	}
	*/
?>
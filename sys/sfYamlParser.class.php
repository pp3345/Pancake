<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* sfYamlParser.class.php                                       */
	/* Fabien Potencier                                             */
	/* 2012 - 2013 Yussuf Khalil                                    */
	/* See LICENSE file for license information                     */
	/****************************************************************/

	// YAML-Parser from Symfony project
	// Modified for Pancake

	namespace Pancake;

/*
 * Copyright (c) 2008-2009 Fabien Potencier Permission is hereby granted, free
 * of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to
 * the following conditions: The above copyright notice and this permission
 * notice shall be included in all copies or substantial portions of the
 * Software. THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO
 * EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
 * OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class sfYamlInline {
	const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*(?:\'\'[^\']*)*)\')';
	
	static public function load($value) {
		$value = trim ( $value );
		
		if (0 == strlen ( $value )) {
			return '';
		}
		
		if (function_exists ( 'mb_internal_encoding' ) && (( int ) ini_get ( 'mbstring.func_overload' )) & 2) {
			$mbEncoding = mb_internal_encoding ();
			mb_internal_encoding ( 'ASCII' );
		}
		
		switch ($value [0]) {
			case '[' :
				$result = self::parseSequence ( $value );
				break;
			case '{' :
				$result = self::parseMapping ( $value );
				break;
			default :
				$result = self::parseScalar ( $value );
		}
		
		if (isset ( $mbEncoding )) {
			mb_internal_encoding ( $mbEncoding );
		}
		
		return $result;
	}
	
	static public function parseScalar($scalar, $delimiters = null, $stringDelimiters = array('"', "'"), &$i = 0, $evaluate = true) {
		if (in_array ( $scalar [$i], $stringDelimiters )) {
			// quoted scalar
			$output = self::parseQuotedScalar ( $scalar, $i );
		} else {
			// "normal" string
			if (! $delimiters) {
				$output = substr ( $scalar, $i );
				$i += strlen ( $output );
				
				// remove comments
				if (false !== $strpos = strpos ( $output, ' #' )) {
					$output = rtrim ( substr ( $output, 0, $strpos ) );
				}
			} else if (preg_match ( '/^(.+?)(' . implode ( '|', $delimiters ) . ')/', substr ( $scalar, $i ), $match )) {
				$output = $match [1];
				$i += strlen ( $output );
			} else {
				throw new \InvalidArgumentException ( sprintf ( 'Malformed inline YAML string (%s).', $scalar ) );
			}
			
			$output = $evaluate ? self::evaluateScalar ( $output ) : $output;
		}
		
		return $output;
	}
	
	static protected function parseQuotedScalar($scalar, &$i) {
		if (! preg_match ( '/' . self::REGEX_QUOTED_STRING . '/Au', substr ( $scalar, $i ), $match )) {
			throw new \InvalidArgumentException ( sprintf ( 'Malformed inline YAML string (%s).', substr ( $scalar, $i ) ) );
		}
		
		$output = substr ( $match [0], 1, strlen ( $match [0] ) - 2 );
		
		if ('"' == $scalar [$i]) {
			// evaluate the string
			$output = str_replace ( array (
					'\\"',
					'\\n',
					'\\r' 
			), array (
					'"',
					"\n",
					"\r" 
			), $output );
		} else {
			// unescape '
			$output = str_replace ( '\'\'', '\'', $output );
		}
		
		$i += strlen ( $match [0] );
		
		return $output;
	}
	
	static protected function parseSequence($sequence, &$i = 0) {
		$output = array ();
		$len = strlen ( $sequence );
		$i += 1;
		
		// [foo, bar, ...]
		while ( $i < $len ) {
			switch ($sequence [$i]) {
				case '[' :
					// nested sequence
					$output [] = self::parseSequence ( $sequence, $i );
					break;
				case '{' :
					// nested mapping
					$output [] = self::parseMapping ( $sequence, $i );
					break;
				case ']' :
					return $output;
				case ',' :
				case ' ' :
					break;
				default :
					$isQuoted = in_array ( $sequence [$i], array (
							'"',
							"'" 
					) );
					$value = self::parseScalar ( $sequence, array (
							',',
							']' 
					), array (
							'"',
							"'" 
					), $i );
					
					if (! $isQuoted && false !== strpos ( $value, ': ' )) {
						// embedded mapping?
						try {
							$value = self::parseMapping ( '{' . $value . '}' );
						} catch ( \InvalidArgumentException $e ) {
							// no, it's not
						}
					}
					
					$output [] = $value;
					
					-- $i;
			}
			
			++ $i;
		}
		
		throw new \InvalidArgumentException ( sprintf ( 'Malformed inline YAML string %s', $sequence ) );
	}
	
	static protected function parseMapping($mapping, &$i = 0) {
		$output = array ();
		$len = strlen ( $mapping );
		$i += 1;
		
		// {foo: bar, bar:foo, ...}
		while ( $i < $len ) {
			switch ($mapping [$i]) {
				case ' ' :
				case ',' :
					++ $i;
					continue 2;
				case '}' :
					return $output;
			}
			
			// key
			$key = self::parseScalar ( $mapping, array (
					':',
					' ' 
			), array (
					'"',
					"'" 
			), $i, false );
			
			// value
			$done = false;
			while ( $i < $len ) {
				switch ($mapping [$i]) {
					case '[' :
						// nested sequence
						$output [$key] = self::parseSequence ( $mapping, $i );
						$done = true;
						break;
					case '{' :
						// nested mapping
						$output [$key] = self::parseMapping ( $mapping, $i );
						$done = true;
						break;
					case ':' :
					case ' ' :
						break;
					default :
						$output [$key] = self::parseScalar ( $mapping, array (
								',',
								'}' 
						), array (
								'"',
								"'" 
						), $i );
						$done = true;
						-- $i;
				}
				
				++ $i;
				
				if ($done) {
					continue 2;
				}
			}
		}
		
		throw new \InvalidArgumentException ( sprintf ( 'Malformed inline YAML string %s', $mapping ) );
	}
	
	static protected function evaluateScalar($scalar) {
		$scalar = trim ( $scalar );
		
		$trueValues = array (
				'true' 
		);
		$falseValues = array (
				'false' 
		);
		
		switch (true) {
			case 'null' == strtolower ( $scalar ) :
			case '' == $scalar :
			case '~' == $scalar :
				return null;
			case 0 === strpos ( $scalar, '!str' ) :
				return ( string ) substr ( $scalar, 5 );
			case 0 === strpos ( $scalar, '! ' ) :
				return intval ( self::parseScalar ( substr ( $scalar, 2 ) ) );
			case 0 === strpos ( $scalar, '!!php/object:' ) :
				return unserialize ( substr ( $scalar, 13 ) );
			case ctype_digit ( $scalar ) :
				$raw = $scalar;
				$cast = intval ( $scalar );
				return '0' == $scalar [0] ? octdec ( $scalar ) : ((( string ) $raw == ( string ) $cast) ? $cast : $raw);
			case in_array ( strtolower ( $scalar ), $trueValues ) :
				return true;
			case in_array ( strtolower ( $scalar ), $falseValues ) :
				return false;
			case is_numeric ( $scalar ) :
				return '0x' == $scalar [0] . $scalar [1] ? hexdec ( $scalar ) : floatval ( $scalar );
			case 0 == strcasecmp ( $scalar, '.inf' ) :
			case 0 == strcasecmp ( $scalar, '.NaN' ) :
				return - log ( 0 );
			case 0 == strcasecmp ( $scalar, '-.inf' ) :
				return log ( 0 );
			case preg_match ( '/^(-|\+)?[0-9,]+(\.[0-9]+)?$/', $scalar ) :
				return floatval ( str_replace ( ',', '', $scalar ) );
			case preg_match ( self::getTimestampRegex (), $scalar ) :
				return strtotime ( $scalar );
			default :
				return ( string ) $scalar;
		}
	}
	static protected function getTimestampRegex() {
		return <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
    -(?P<month>[0-9][0-9]?)
    -(?P<day>[0-9][0-9]?)
    		(?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
    $~x
EOF;
	}
}

class sfYamlParser {
	protected $offset = 0, $lines = array (), $currentLineNb = - 1, $currentLine = '', $refs = array ();
	
	public function __construct($offset = 0) {
		$this->offset = $offset;
	}
	
	public function parse($value) {
		$this->currentLineNb = - 1;
		$this->currentLine = '';
		$this->lines = explode ( "\n", $this->cleanup ( $value ) );
		
		if (function_exists ( 'mb_internal_encoding' ) && (( int ) ini_get ( 'mbstring.func_overload' )) & 2) {
			$mbEncoding = mb_internal_encoding ();
			mb_internal_encoding ( 'UTF-8' );
		}
		
		$data = array ();
		while ( $this->moveToNextLine () ) {
			if ($this->isCurrentLineEmpty ()) {
				continue;
			}
			
			// tab?
			if (preg_match ( '#^\t+#', $this->currentLine )) {
				throw new \InvalidArgumentException ( sprintf ( 'A YAML file cannot contain tabs as indentation at line %d (%s).', $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
			}
			
			$isRef = $isInPlace = $isProcessed = false;
			if (preg_match ( '#^\-((?P<leadspaces>\s+)(?P<value>.+?))?\s*$#u', $this->currentLine, $values )) {
				if (isset ( $values ['value'] ) && preg_match ( '#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values ['value'], $matches )) {
					$isRef = $matches ['ref'];
					$values ['value'] = $matches ['value'];
				}
				
				// array
				if (! isset ( $values ['value'] ) || '' == trim ( $values ['value'], ' ' ) || 0 === strpos ( ltrim ( $values ['value'], ' ' ), '#' )) {
					$c = $this->getRealCurrentLineNb () + 1;
					$parser = new sfYamlParser ( $c );
					$parser->refs = & $this->refs;
					$data [] = $parser->parse ( $this->getNextEmbedBlock () );
				} else {
					if (isset ( $values ['leadspaces'] ) && ' ' == $values ['leadspaces'] && preg_match ( '#^(?P<key>' . sfYamlInline::REGEX_QUOTED_STRING . '|[^ \'"\{].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $values ['value'], $matches )) {
						// this is a compact notation element, add to next block
						// and parse
						$c = $this->getRealCurrentLineNb ();
						$parser = new sfYamlParser ( $c );
						$parser->refs = & $this->refs;
						
						$block = $values ['value'];
						if (! $this->isNextLineIndented ()) {
							$block .= "\n" . $this->getNextEmbedBlock ( $this->getCurrentLineIndentation () + 2 );
						}
						
						$data [] = $parser->parse ( $block );
					} else {
						$data [] = $this->parseValue ( $values ['value'] );
					}
				}
			} else if (preg_match ( '#^(?P<key>' . sfYamlInline::REGEX_QUOTED_STRING . '|[^ \'"].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $this->currentLine, $values )) {
				$key = sfYamlInline::parseScalar ( $values ['key'] );
				
				if ('<<' === $key) {
					if (isset ( $values ['value'] ) && '*' === substr ( $values ['value'], 0, 1 )) {
						$isInPlace = substr ( $values ['value'], 1 );
						if (! array_key_exists ( $isInPlace, $this->refs )) {
							throw new \InvalidArgumentException ( sprintf ( 'Reference "%s" does not exist at line %s (%s).', $isInPlace, $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
						}
					} else {
						if (isset ( $values ['value'] ) && $values ['value'] !== '') {
							$value = $values ['value'];
						} else {
							$value = $this->getNextEmbedBlock ();
						}
						$c = $this->getRealCurrentLineNb () + 1;
						$parser = new sfYamlParser ( $c );
						$parser->refs = & $this->refs;
						$parsed = $parser->parse ( $value );
						
						$merged = array ();
						if (! is_array ( $parsed )) {
							throw new \InvalidArgumentException ( sprintf ( "YAML merge keys used with a scalar value instead of an array at line %s (%s)", $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
						} else if (isset ( $parsed [0] )) {
							// Numeric array, merge individual elements
							foreach ( array_reverse ( $parsed ) as $parsedItem ) {
								if (! is_array ( $parsedItem )) {
									throw new \InvalidArgumentException ( sprintf ( "Merge items must be arrays at line %s (%s).", $this->getRealCurrentLineNb () + 1, $parsedItem ) );
								}
								$merged = array_merge ( $parsedItem, $merged );
							}
						} else {
							// Associative array, merge
							$merged = array_merge ( $merged, $parsed );
						}
						
						$isProcessed = $merged;
					}
				} else if (isset ( $values ['value'] ) && preg_match ( '#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values ['value'], $matches )) {
					$isRef = $matches ['ref'];
					$values ['value'] = $matches ['value'];
				}
				
				if ($isProcessed) {
					// Merge keys
					$data = $isProcessed;
				} 				// hash
				else if (! isset ( $values ['value'] ) || '' == trim ( $values ['value'], ' ' ) || 0 === strpos ( ltrim ( $values ['value'], ' ' ), '#' )) {
					// if next line is less indented or equal, then it means
					// that the current value is null
					if ($this->isNextLineIndented ()) {
						$data [$key] = null;
					} else {
						$c = $this->getRealCurrentLineNb () + 1;
						$parser = new sfYamlParser ( $c );
						$parser->refs = & $this->refs;
						$data [$key] = $parser->parse ( $this->getNextEmbedBlock () );
					}
				} else {
					if ($isInPlace) {
						$data = $this->refs [$isInPlace];
					} else {
						$data [$key] = $this->parseValue ( $values ['value'] );
					}
				}
			} else {
				// 1-liner followed by newline
				if (2 == count ( $this->lines ) && empty ( $this->lines [1] )) {
					$value = sfYamlInline::load ( $this->lines [0] );
					if (is_array ( $value )) {
						$first = reset ( $value );
						if ('*' === substr ( $first, 0, 1 )) {
							$data = array ();
							foreach ( $value as $alias ) {
								$data [] = $this->refs [substr ( $alias, 1 )];
							}
							$value = $data;
						}
					}
					
					if (isset ( $mbEncoding )) {
						mb_internal_encoding ( $mbEncoding );
					}
					
					return $value;
				}
				
				switch (preg_last_error ()) {
					case PREG_INTERNAL_ERROR :
						$error = 'Internal PCRE error on line';
						break;
					case PREG_BACKTRACK_LIMIT_ERROR :
						$error = 'pcre.backtrack_limit reached on line';
						break;
					case PREG_RECURSION_LIMIT_ERROR :
						$error = 'pcre.recursion_limit reached on line';
						break;
					case PREG_BAD_UTF8_ERROR :
						$error = 'Malformed UTF-8 data on line';
						break;
					case PREG_BAD_UTF8_OFFSET_ERROR :
						$error = 'Offset doesn\'t correspond to the begin of a valid UTF-8 code point on line';
						break;
					default :
						$error = 'Unable to parse line';
				}
				
				throw new \InvalidArgumentException ( sprintf ( '%s %d (%s).', $error, $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
			}
			
			if ($isRef) {
				$this->refs [$isRef] = end ( $data );
			}
		}
		
		if (isset ( $mbEncoding )) {
			mb_internal_encoding ( $mbEncoding );
		}
		
		return empty ( $data ) ? null : $data;
	}
	
	protected function getRealCurrentLineNb() {
		return $this->currentLineNb + $this->offset;
	}
	
	protected function getCurrentLineIndentation() {
		return strlen ( $this->currentLine ) - strlen ( ltrim ( $this->currentLine, ' ' ) );
	}
	
	protected function getNextEmbedBlock($indentation = null) {
		$this->moveToNextLine ();
		
		if (null === $indentation) {
			$newIndent = $this->getCurrentLineIndentation ();
			
			if (! $this->isCurrentLineEmpty () && 0 == $newIndent) {
				throw new \InvalidArgumentException ( sprintf ( 'Indentation problem at line %d (%s)', $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
			}
		} else {
			$newIndent = $indentation;
		}
		
		$data = array (
				substr ( $this->currentLine, $newIndent ) 
		);
		
		while ( $this->moveToNextLine () ) {
			if ($this->isCurrentLineEmpty ()) {
				if ($this->isCurrentLineBlank ()) {
					$data [] = substr ( $this->currentLine, $newIndent );
				}
				
				continue;
			}
			
			$indent = $this->getCurrentLineIndentation ();
			
			if (preg_match ( '#^(?P<text> *)$#', $this->currentLine, $match )) {
				// empty line
				$data [] = $match ['text'];
			} else if ($indent >= $newIndent) {
				$data [] = substr ( $this->currentLine, $newIndent );
			} else if (0 == $indent) {
				$this->moveToPreviousLine ();
				
				break;
			} else {
				throw new \InvalidArgumentException ( sprintf ( 'Indentation problem at line %d (%s)', $this->getRealCurrentLineNb () + 1, $this->currentLine ) );
			}
		}
		
		return implode ( "\n", $data );
	}
	
	protected function moveToNextLine() {
		if ($this->currentLineNb >= count ( $this->lines ) - 1) {
			return false;
		}
		
		$this->currentLine = $this->lines [++ $this->currentLineNb];
		
		return true;
	}
	
	protected function moveToPreviousLine() {
		$this->currentLine = $this->lines [-- $this->currentLineNb];
	}
	
	protected function parseValue($value) {
		if ('*' === substr ( $value, 0, 1 )) {
			if (false !== $pos = strpos ( $value, '#' )) {
				$value = substr ( $value, 1, $pos - 2 );
			} else {
				$value = substr ( $value, 1 );
			}
			
			if (! array_key_exists ( $value, $this->refs )) {
				throw new \InvalidArgumentException ( sprintf ( 'Reference "%s" does not exist (%s).', $value, $this->currentLine ) );
			}
			return $this->refs [$value];
		}
		
		if (preg_match ( '/^(?P<separator>\||>)(?P<modifiers>\+|\-|\d+|\+\d+|\-\d+|\d+\+|\d+\-)?(?P<comments> +#.*)?$/', $value, $matches )) {
			$modifiers = isset ( $matches ['modifiers'] ) ? $matches ['modifiers'] : '';
			
			return $this->parseFoldedScalar ( $matches ['separator'], preg_replace ( '#\d+#', '', $modifiers ), intval ( abs ( $modifiers ) ) );
		} else {
			return sfYamlInline::load ( $value );
		}
	}
	
	protected function parseFoldedScalar($separator, $indicator = '', $indentation = 0) {
		$separator = '|' == $separator ? "\n" : ' ';
		$text = '';
		
		$notEOF = $this->moveToNextLine ();
		
		while ( $notEOF && $this->isCurrentLineBlank () ) {
			$text .= "\n";
			
			$notEOF = $this->moveToNextLine ();
		}
		
		if (! $notEOF) {
			return '';
		}
		
		if (! preg_match ( '#^(?P<indent>' . ($indentation ? str_repeat ( ' ', $indentation ) : ' +') . ')(?P<text>.*)$#u', $this->currentLine, $matches )) {
			$this->moveToPreviousLine ();
			
			return '';
		}
		
		$textIndent = $matches ['indent'];
		$previousIndent = 0;
		
		$text .= $matches ['text'] . $separator;
		while ( $this->currentLineNb + 1 < count ( $this->lines ) ) {
			$this->moveToNextLine ();
			
			if (preg_match ( '#^(?P<indent> {' . strlen ( $textIndent ) . ',})(?P<text>.+)$#u', $this->currentLine, $matches )) {
				if (' ' == $separator && $previousIndent != $matches ['indent']) {
					$text = substr ( $text, 0, - 1 ) . "\n";
				}
				$previousIndent = $matches ['indent'];
				
				$text .= str_repeat ( ' ', $diff = strlen ( $matches ['indent'] ) - strlen ( $textIndent ) ) . $matches ['text'] . ($diff ? "\n" : $separator);
			} else if (preg_match ( '#^(?P<text> *)$#', $this->currentLine, $matches )) {
				$text .= preg_replace ( '#^ {1,' . strlen ( $textIndent ) . '}#', '', $matches ['text'] ) . "\n";
			} else {
				$this->moveToPreviousLine ();
				
				break;
			}
		}
		
		if (' ' == $separator) {
			// replace last separator by a newline
			$text = preg_replace ( '/ (\n*)$/', "\n$1", $text );
		}
		
		switch ($indicator) {
			case '' :
				$text = preg_replace ( '#\n+$#s', "\n", $text );
				break;
			case '+' :
				break;
			case '-' :
				$text = preg_replace ( '#\n+$#s', '', $text );
				break;
		}
		
		return $text;
	}
	
	protected function isNextLineIndented() {
		$currentIndentation = $this->getCurrentLineIndentation ();
		$notEOF = $this->moveToNextLine ();
		
		while ( $notEOF && $this->isCurrentLineEmpty () ) {
			$notEOF = $this->moveToNextLine ();
		}
		
		if (false === $notEOF) {
			return false;
		}
		
		$ret = false;
		if ($this->getCurrentLineIndentation () <= $currentIndentation) {
			$ret = true;
		}
		
		$this->moveToPreviousLine ();
		
		return $ret;
	}
	
	protected function isCurrentLineEmpty() {
		return $this->isCurrentLineBlank () || $this->isCurrentLineComment ();
	}
	
	protected function isCurrentLineBlank() {
		return '' == trim ( $this->currentLine, ' ' );
	}
	
	protected function isCurrentLineComment() {
		// checking explicitly the first char of the trim is faster than loops
		// or strpos
		$ltrimmedLine = ltrim ( $this->currentLine, ' ' );
		return $ltrimmedLine [0] === '#';
	}
	
	protected function cleanup($value) {
		$value = str_replace ( array (
				"\r\n",
				"\r" 
		), "\n", $value );
		
		if (! preg_match ( "#\n$#", $value )) {
			$value .= "\n";
		}
		
		// strip YAML header
		$count = 0;
		$value = preg_replace ( '#^\%YAML[: ][\d\.]+.*\n#su', '', $value, - 1, $count );
		$this->offset += $count;
		
		// remove leading comments
		$trimmedValue = preg_replace ( '#^(\#.*?\n)+#s', '', $value, - 1, $count );
		if ($count == 1) {
			// items have been removed, update the offset
			$this->offset += substr_count ( $value, "\n" ) - substr_count ( $trimmedValue, "\n" );
			$value = $trimmedValue;
		}
		
		// remove start of the document marker (---)
		$trimmedValue = preg_replace ( '#^\-\-\-.*?\n#s', '', $value, - 1, $count );
		if ($count == 1) {
			// items have been removed, update the offset
			$this->offset += substr_count ( $value, "\n" ) - substr_count ( $trimmedValue, "\n" );
			$value = $trimmedValue;
			
			// remove end of the document marker (...)
			$value = preg_replace ( '#\.\.\.\s*$#s', '', $value );
		}
		
		return $value;
	}
}

?>
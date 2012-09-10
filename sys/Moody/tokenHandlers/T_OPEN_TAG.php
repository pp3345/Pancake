<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_OPEN_TAG.php                                     			*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\TokenHandlers;
	
	use Moody\Token;
	use Moody\TokenVM;
	use Moody\TokenHandler;

	class OpenTagHandler implements TokenHandler {
		private static $instance = null;
		
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
		
		private function __construct() {
			TokenVM::globalRegisterTokenHandler(T_OPEN_TAG, $this);
		}
		
		public function execute(Token $token, TokenVM $vm) {
			if($token->content == '<?' || $token->content == '<%')
				$token->content = '<?php ';
			
			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
		}
	}
?>
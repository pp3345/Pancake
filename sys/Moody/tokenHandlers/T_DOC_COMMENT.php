<?php

	/****************************************************************/
	/* Moody                                                        */
	/* T_DOC_COMMENT.php                                     		*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\TokenHandlers;
	
	use Moody\TokenHandler;
	use Moody\TokenVM;
	use Moody\Token;
	use Moody\Configuration;

	/**
	 * Doc comment remover
	 */
	class DocCommentHandler implements TokenHandler {
		private static $instance = null;
	
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}
	
		private function __construct() {
			TokenVM::globalRegisterTokenHandler(T_DOC_COMMENT, $this);
		}

		public function execute(Token $token, TokenVM $vm) {
			if(Configuration::get('deletecomments', false))
				return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN | TokenVM::DELETE_TOKEN;
			return TokenVM::NEXT_HANDLER | TokenVM::NEXT_TOKEN;
		}
	}
?>
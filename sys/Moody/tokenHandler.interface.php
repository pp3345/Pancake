<?php

	/****************************************************************/
	/* Moody                                                        */
	/* tokenHandler.interface.php                                   */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;
	
	interface TokenHandler {
		public static function getInstance();
		public function execute(Token $token, TokenVM $vm);
	}
	
	interface TokenHandlerWithRegister extends TokenHandler {
		public function register(Token $token, TokenVM $vm);
	}
?>
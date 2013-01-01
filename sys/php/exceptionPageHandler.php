<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* exceptionPageHandler.php                                     */
	/* 2012 Yussuf Khalil                                           */
	/* License: http://pancakehttp.net/license/                     */
	/****************************************************************/
	
	namespace Pancake;

	$requestObject->setHeader('Content-Type', 'text/html; charset=utf-8');
?>
<!doctype html>
<html>
	<head>
		<title><?=$exception->getCode() . ' ' . HTTPRequest::getAnswerCodeString($exception->getCode())?></title>
		<style>
			body{font-family:"Arial"}
			hr{border:1px solid #000}
		</style>
	</head>
	<body>
		<h1><?=$exception->getCode() . ' ' . HTTPRequest::getAnswerCodeString($exception->getCode())?></h1>
		<hr />
		<strong><?=($exception->getCode() >= 500 ? 'Your HTTP request could not be processed.' : 'Your HTTP request was invalid.')?></strong> Error description:
		<br />
		<?=$exception->getMessage()?>
		<br /><br />
		<?php 
		if($exception->getHeader()) {
		?>
		<strong>Headers:</strong>
		<br/>
		<?=nl2br($exception->header)?>
		<?php
		}
		if(Config::get("main.exposepancake")) {
		?>
		<hr />
		Pancake <?=VERSION?>
		<?php } ?>
	</body>
</html>
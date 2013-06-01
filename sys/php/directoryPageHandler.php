<?php

	/****************************************************************/
	/* Pancake                                                      */
	/* directoryPageHandler.php                                     */
	/* 2012 - 2013 Yussuf Khalil                                    */
	/* See LICENSE file for license information                     */
	/****************************************************************/
	
	namespace Pancake;
?>
<!doctype html>
<html>
	<head>
		<title>Index of <?=$requestObject->requestFilePath?></title>
		<style>
			body{font-family:"Arial"}
			hr{border:1px solid #000}
			thead{font-weight:bold}
		</style>
	</head>
	<body>
		<h1>Index of <?=$requestObject->requestFilePath?></h1>
		<hr />
		<table>
			<thead>
				<tr>
					<th>Filename</th>
					<th>Type</th>
					<th>Last Modified</th>
					<th>Size</th>
				</tr>
			</thead>
			<tbody>
			<?php 
				foreach($files as $file) {
			?>
				<tr>
					<td>
						<a href="<?=$file['address']?>"><?=($file['directory'] ? $file['name'] . '/' : $file['name'])?></a>
					</td>
					<td>
						<?=($file['directory'] ? 'Directory' : $file['type'])?>
					</td>
					<td>
						<?=date(Config::get("main.dateformat"), $file['modified'])?>
					</td>
					<?php 
						if(!$file['directory']) {
					?>
					<td>
						<?=formatFilesize($file['size'])?>
					</td>
					<?php } ?>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		<?php 
			if(Config::get('main.exposepancake')) {
		?>
		<hr />
		Pancake <?=VERSION?>
		<?php } ?>
	</body>
</html>
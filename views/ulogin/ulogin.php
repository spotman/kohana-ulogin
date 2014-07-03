<script src="http://ulogin.ru/js/ulogin.js"></script>

<?php if ($cfg['type'] == 'window') :?>

	<a id="<?php echo $uniq_id; ?>" href="#" x-ulogin-params="<?php echo $params; ?>">
		<img src="http://ulogin.ru/img/button.png" width="187px" height="30px" alt="МультиВход"/>
	</a>

<?php else: ?>

	<div id="<?php echo $uniq_id; ?>" x-ulogin-params="<?php echo $params; ?>"></div>
	
<?php endif; ?>
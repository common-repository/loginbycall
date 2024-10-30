<div id="wrapper-loginbycall-delete-info">
	<div id="loginbycall-delete-info">
		<p>
			<?php print __('WARNING! All tables of module will be clear! All available user\'s connections with LoginByCall will be lost!', 'loginbycall'); ?>
		</p>
		<div id="loginbycall-wrapper">
			<form  method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=loginbycall'; ?>">
				<input id="yes-delete-loginbycall" name="yes-delete-loginbycall" type="submit" value="<?php print __('Yes', 'loginbycall'); ?>"></input>
				<input id="loginbycall-form-close" type="submit" value="<?php print __('Not', 'loginbycall'); ?>"></input>
			</form>
		</div>	
		<a id="loginbycall-close">
	<!--		<img src="img/close-icon.png">-->
		</a>
	</div>
</div>
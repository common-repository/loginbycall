<script>
	function openWindow() {
		var leftvar = (screen.width-600)/2;
		var topvar = (screen.height-360)/2;
		myWin = window.open('<?php global $user_login;
print loginbycall_create_link($user_login);
?> ', "displayWindow", "width=600,height=360,left="+leftvar+",top="+topvar+",status=no,toolbar=no,menubar=no");
	}
</script>
<div id="wrapper-loginbycall-form-offer-place">
	<div id="loginbycall-form-offer-place">
		<p>
			<?php print __('Add a convenient way to log on with a loginbycall to your account ', 'loginbycall'); ?><?php
			global $user_login;
			print '<b>' . $user_login . '</b>';
			?><?php print __(" on site ", 'loginbycall'); ?><?php print '<b>' . $_SERVER['HTTP_HOST'] . '</b>'; ?>?
		</p>
		<div id="loginbycall-wrapper">
			<a id="yes-loginbycall" href="#" onclick="openWindow();"><?php print __('Yes', 'loginbycall'); ?></a>
			<a id="loginbycall-form-close" href="#"><?php print __('Not now', 'loginbycall'); ?></a>
			<a id='loginbycall-oauth-unbind' href="#"><?php print __('No longer offer', 'loginbycall'); ?></a>
			<div class="loginbycall-oauth-unbind-value"><?= $user_ID; ?></div>
		</div>	
		<a id="loginbycall-close">
	<!--		<img src="img/close-icon.png">-->
		</a>
	</div>
</div>
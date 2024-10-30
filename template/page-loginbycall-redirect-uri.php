<?php

 require_once(dirname(__FILE__) . "/../function.php");
 
// функция авторизует юзера по логину
function logincall_authorise_user($user_login) {
	//вход пользователя по UID
	$user = get_user_by('login', $user_login);
	wp_set_auth_cookie($user->ID, false);
	if (loginbycall_settings_post()) {
		if ($_SESSION['redirect_url']) {
			wp_redirect($_SESSION['redirect_url']);
			unset($_SESSION['redirect_url']);
		}else{
			wp_redirect(home_url());
		}
		exit('');
	}
}

if ($_GET['error_description']) {
	echo '<script type ="text/javascript">
	if(window.opener){
		window.close();
	}
	</script>
	';
	die();
}
if ($_GET['prev_url']) {
	$_SESSION['redirect_url'] = $_GET['prev_url'];
	wp_redirect(loginbycall_create_link());
	die();
}
/* Производим обмен данными по протоколу и получение пользовательских данных */
if (isset($_SESSION['loginbycall_object'])) {
	$object = $_SESSION['loginbycall_object'];
	unset($_SESSION['loginbycall_object']);
} else {
	$object = loginbycall_oauth_render(get_option('loginbycall_redirect_uri'), get_option('loginbycall_client_id'), get_option('loginbycall_client_secret'), get_option('loginbycall_grant_type'));
	$_SESSION['loginbycall_object'] = $object;
	$_SESSION['loginbycall_redirect'] = 1;
}
if (isset($_SESSION['loginbycall_redirect'])) {
	unset($_SESSION['loginbycall_redirect']);
	echo '<script type ="text/javascript">
	if(window.opener){
		window.opener.location.href = "' . home_url() . '/loginbycall-redirect-uri/";
		window.close();
	}
	</script>
	';
	die('');
}
/* Проверяем ответ сервера на наличие ошибки */
if ($object->error) {
	if (isset($_SESSION['loginbycall_target_token']))
		unset($_SESSION['loginbycall_target_token']);
	return $object->error_description;
}
//if (!$object) {
//	wp_redirect(home_url() . '/loginbycall-settings');
//}
/* Ищем пользователя с пришедшим target_token в таблице loginbycall */
global $wpdb;
/* События не авторизованного пользователя */
if (!is_user_logged_in()) {
	$i_user = false;
	if ($object->target_token) {
		$table_name = $wpdb->prefix . "loginbycall_user";
		$i_user = $wpdb->get_results("SELECT * FROM " . $table_name . " WHERE target_token = '" . $object->target_token . "'");
		if (!count($i_user)) {
			$i_user = false;
		} else {
			$i_user = $i_user[0];
		}
		// Если данный target_token присутствует
		if ($i_user) {
			//вход пользователя по UID
			logincall_authorise_user($i_user->login); 
		}
		// Если данного target_token нет в таблице loginbycall_users
		if (!$i_user) {
			// проверяем, был ли в WP создан юзер с таким login и email через LBC
			$wp_user_login = logincall_find_wp_user($object);

			if ($wp_user_login) {
				// авторизуем пользователя
				logincall_authorise_user($wp_user_login); 
			}
			else {
				//сохраняем в сессию и перенаправляем oauth-user-loginbycall
				$_SESSION['loginbycall_form_object'] = $object;
				if (loginbycall_oauth_user_post()) {
					wp_redirect(home_url() . '/oauth-user-loginbycall'); // переход на страницу создания привязки нового пользователя
					exit('');
				}
			}
		}
	}
}
/* События авторизованного пользователя */ else {
	// Если в БД нет пришедшего target_token
	if ($object->target_token) {
		$table_name = $wpdb->prefix . "loginbycall_user";
		$i_user = $wpdb->get_results("SELECT id FROM " . $table_name . " WHERE target_token = '" . $object->target_token . "'");
		if (count($i_user) == 0) {
			$i_user = false;
		}
		if (!$i_user) {// Добавляем запись в таблицу пользоателей loginbycall
			global $user_ID;
			$user = get_userdata($user_ID);
			$wpdb->insert(
					$table_name, array('uid' => $user_ID, 'login' => $user->data->user_login, 'target_token' => $object->target_token, 'refresh_token' => $object->refresh_token, 'status' => 1), array('%d', '%s', '%s', '%s', '%s', '%d')
			);
			set_login_loginbycall(get_option('loginbycall_redirect_uri'), get_option('loginbycall_client_id'), get_option('loginbycall_client_secret'), $object->access_token, $object->nickname);
		
			if (loginbycall_settings_post()) {
				wp_redirect(home_url() . '/loginbycall-settings');
				exit('');
			}
		} else { // Если в БД есть пришедший target_token переходим в настройки пользователя
			if (loginbycall_settings_post()) {
				wp_redirect(home_url() . '/loginbycall-settings');
				exit('');
			}
		}
	} else {
		if (loginbycall_settings_post()) {
			wp_redirect(home_url() . '/loginbycall-settings');
			exit('');
		}
	}
}
?>
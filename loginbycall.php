<?php

/*
  Plugin Name: LoginByCall
  Plugin URI: https://wordpress.org/plugins/loginbycall/
  Description: LoginByCall - easy sign-in & sign-up with call on your phone
  Version: 4.12
  Author: 2246636
  Author URI: http://LoginByCall.com
 */

require_once dirname(__FILE__) . '/function.php';
add_action('admin_menu', 'add_loginbycall_page');
function add_loginbycall_page()
{
    add_menu_page('LoginByCall', 'LoginByCall', 'manage_options', 'loginbycall', 'loginbycall_options_page', plugin_dir_url(__FILE__) . '/img/logolbc.svg', 1001);
}

function ajax_login_init()
{
    wp_register_script('ajax-login-script', plugin_dir_url(__FILE__) . '/loginbycall.js', array('jquery'));
    wp_enqueue_script('ajax-login-script');
    wp_localize_script('ajax-login-script', 'ajax_login_object', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'redirecturl' => home_url(),
        'loadingmessage' => __('Sending user info, please wait...')
    ));
    wp_enqueue_style('custom-login', plugin_dir_url(__FILE__) . '/css/loginbycall.css');
}

add_action('login_enqueue_scripts', 'ajax_login_init');

function loginbycall_options_page()
{
    loginbycall_change_options();
}

//loginbycall settings
function loginbycall_change_options()
{

    $error = '';
    if (isset($_POST['email_notification']) && $_POST['email_notification'] != getNotificationEmail()) {
        change_credential($_POST['email_notification']);
    }
    if (isset($_POST['loginbycall_update_key_btn']) && get_option('loginbycall_api_key') != $_POST['loginbycall_update_key_btn']) {
        update_option('loginbycall_api_key', $_POST['api_key']);
        if (get_option('loginbycall_new_api_id')) {
            update_option('loginbycall_api_id', get_option('loginbycall_new_api_id'));
            delete_option('loginbycall_new_api_id');
        }
        credential_confirm();
    }
    if (isset($_POST['loginbycall_reset_flag_refuse'])) {
        global $wpdb;
        $r = $wpdb->query("DELETE FROM " . $wpdb->prefix . "usermeta WHERE meta_key = 'loginbycall_user_refuse' and meta_value = '1'", ARRAY_A);
    }

    if (isset($_POST['loginbycall_base_setup_btn'])) {
        $limit = options_loginbycall($_POST['email_notification_limit']);

        if (isset($_POST['loginbycall_register_phone']))
            $loginbycall_register_phone = $_POST['loginbycall_register_phone'];
        else
            $loginbycall_register_phone = 0;

        //attempt to enable the LoginByCall option when the roles are disabled
        if (loginbycall_update_roles(array('_onefactor', '_twofactor'), $loginbycall_register_phone)) {
            $error = '<div class="notice notice-error"><p>' . __('Specify at least one authorization method for each role', 'loginbycall') . '</p></div>';
        } else
            update_option('loginbycall_register_phone', $loginbycall_register_phone);


    }


    //Render the form settings LoginByCall
    if (isset($_POST['loginbycall_pay_btn'])) {
        echo pay_loginbycall($_POST['pay']);
    } else
        render_settings_loginbycall($error);

    //var_dump(status_loginbycall());
}

function render_settings_loginbycall($error)
{
    wp_enqueue_script('jquery-ui-dialog'); // jquery and jquery-ui should be dependencies, didn't check though...
    wp_enqueue_style('wp-jquery-ui-dialog');
    if (server_status() == 1)
        $balance = balance_loginbycall();
    else
        $balance = 0;


    echo '<h2>' . __('LoginByCall Settings', 'loginbycall') . '</h2>';

    if (get_option('loginbycall_credential_active') != 1)
        echo '<div class="notice notice-error"><p>' . __('Missing API-key', 'loginbycall') . '</p></div>';
    echo '<form id="loginbycallupdateform" method="post" action="' . $_SERVER['PHP_SELF'] . '?page=loginbycall&updated=true">';
    echo '<table class="form-table">
			<tr>
				<th><label>' . __('Api key', 'loginbycall') . '</label></th>
				<td width="325"><input name="api_key" type="text" style="width: 323px;" value="' . get_option('loginbycall_api_key') . '"/><br><span class="description">' . __('Enter your api key. To reissue the key, deactivate and reactivate the plugin.', 'loginbycall') . '</span></td>
				<td style="vertical-align:top;"><input type="submit" name="loginbycall_update_key_btn" class="button button-primary" value="'.__('Activate', 'loginbycall').'" '.(get_option('loginbycall_credential_active')==1?'disabled':'').'/></td>
				<td width="900"></td>
			</tr>
			<tr>
			<th><label>'.__('Balance LoginByCall', 'loginbycall').'</label></th>
			<td  class="admin_balance" >
			    <div style="float:left; line-height:2.2;" id="js-loginbycall-balance">' . (lbc_get_safe($balance, 'balance') - lbc_get_safe($balance, 'consumed')) . '</div><div style="float:left; line-height:2.2;  margin-right:5px;">&nbsp;'.__('credits', 'loginbycall').'</div>
			    <div style="padding-top:5px; float:left; margin-right:5px;"><a href="#" id="js-loginbycall-update"><img src="' . plugin_dir_url(__FILE__) . 'img/refresh.png" style="width:20px;"></a></div>
            </td>
            <td><a href="http://loginbycall.com" target="_blank">'.__('View Rates', 'loginbycall').'</a></td>
			</tr>
			<tr>
			<th>'.__('Top up', 'loginbycall').'</th>
			<td width="300"><input name="pay" value="10000">  <br><span class="description">'.__('Enter amount in LBC credits. 1 USD = 10.000 credits', 'loginbycall').'</span></td>
			<td style="vertical-align:top;"><input type="submit" name="loginbycall_pay_btn" class="button button-primary" value="'.__('Proceed', 'loginbycall').'" /></td>
			<td></td>
			</tr>
			<tr>
			<th><label>'.__('Balance notifications', 'loginbycall').'</label></th>
			<td>
			<div style="float:left"><input id="js_email_note" name="email_notification" value="' . getNotificationEmail() . '"><br><span class="description">'.__('E-mail for notification', 'loginbycall').'</span></div>
			</td>
			<td>
			<div><input name="email_notification_limit" value="' . lbc_get_safe($balance, 'balance_notify_limit') . '"><br><span class="description">'.__('Notify if balance is less', 'loginbycall').'</span></div>
			</td>
			<td></td>
			</tr>';
            if($error)
                echo '
 		    <tr>
			<th colspan="2">'.$error.'</th>
			<td ></td>
			<td></td>
			</tr>';
			echo '<tr>
			<th><label>'.__('LoginByCall required', 'loginbycall').'</label></th>
			<td><input name="loginbycall_register_phone" value="1" type="checkbox" ' . (get_option('loginbycall_register_phone') == 1 ? 'checked="checked"' : '') . '></td>
			<td></td>
			<td></td>
			</tr>
			<tr>
			<th><label>'.__('Role settings', 'loginbycall').'</label></th>
			<td></td>
            <td class="b">'.__('Allow One-factor authorization', 'loginbycall').'</td>
            <td class="b">'.__('Allow Two-factor authorization', 'loginbycall').'</td>
                    </tr>';

    foreach (get_editable_roles() as $role_name => $role_info): ?>
        <tr>
            <td></td>
            <td class="b"><?php echo translate_user_role($role_info['name']); ?></td>
            <td><input name="loginbycall_<?php echo $role_name ?>_onefactor" type="checkbox"
                       value="1" <?php echo get_option('loginbycall_' . $role_name . '_onefactor') == 1 ? 'checked="checked"' : '' ?>>
            </td>
            <td><input name="loginbycall_<?php echo $role_name ?>_twofactor" type="checkbox"
                       value="1" <?php echo get_option('loginbycall_' . $role_name . '_twofactor') == 1 ? 'checked="checked"' : '' ?>>
            </td>
        </tr>
    <?php endforeach;
    echo '<tr>
            <th>'.__('Reset "No longer offer" flag for all users', 'loginbycall').'</th>
            <td><input type="submit" name="loginbycall_reset_flag_refuse" class="button button-primary" value="'.__('Reset', 'loginbycall').'"></td>
            <td></td>
            <td></td>
        </tr>
			<tr>
				<td><input type="submit" name="loginbycall_base_setup_btn" class="button button-primary" /></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
		</table>
		<div id="my-dialog" class="hidden" style="max-width:800px">
  <h3>'.__('Attention!', 'loginbycall').'</h3>
  <p>'.__('When the domain administrator\'s email is changed, api_key is reissued. New api-key will come to the email.', 'loginbycall').'</p>
  <p>'.__('Don\'t forget to update the api-key', 'loginbycall').'</p>
</div>';
    echo '</form>';
    ?>
    <script>jQuery(document).ready(function (e) {
            jQuery("#js-loginbycall-update").click(function (e) {
                e.preventDefault;
                jQuery.post("<?php echo get_site_url(); ?>/wp-admin/admin-ajax.php?action=loginbycall_get_balance", function (data) {
                    jQuery("#js-loginbycall-balance").html(data);
                });
            });
            var notEmail = "<?php echo getNotificationEmail(); ?>";
            jQuery("#loginbycallupdateform").submit(function (e) {
                console.log(jQuery("#js_email_note").val() != notEmail);
                if (jQuery("#js_email_note").val() != notEmail) {
                    notEmail = jQuery("#js_email_note").val();
                    e.preventDefault();
                    jQuery("#my-dialog").dialog("open");
                }
            });
            jQuery('input[name="api_key"]').on('change input propertychange keyup paste',function(){
                jQuery('input[name="loginbycall_update_key_btn"]').prop('disabled',false);
            })
            // initalise the dialog
            jQuery("#my-dialog").dialog({
                title: "Предупреждение",
                dialogClass: "wp-dialog",
                autoOpen: false,
                draggable: false,
                width: "auto",
                modal: true,
                resizable: false,
                closeOnEscape: true,
                position: {
                    my: "center",
                    at: "center",
                    of: window
                },
                open: function () {
                    // close dialog by clicking the overlay behind it
                    jQuery(".ui-widget-overlay").bind("click", function () {
                        jQuery("#my-dialog").dialog("close");

                    })
                },
                create: function () {
                    // style fix for WordPress admin
                    jQuery(".ui-dialog-titlebar-close").addClass("ui-button");
                }
            }).on('dialogclose', function (event) {
                jQuery("#loginbycallupdateform").submit();
            });
            ;
            // bind a button or a link to open the dialog

        });</script>
    <style>.b {
            font-weight: 600;
        }</style>
<?php
}

function loginbycall_render_login_types($user, $olduser = false)
{

    $allow = loginbycall_check_allowed_role($user->roles);
    $type = get_user_meta($user->ID, 'loginbycall_user_login_type', true);

    //If only one authorization method is available to the user, then it must be marked.
    //If the user does not have a choice, the first available method.
    if ($type == false) {
        if ($allow['_onefactor'])
            $type = 1;
        elseif ($allow['_twofactor'])
            $type = 2;
    }
    if ($allow['_onefactor'] && $allow['_twofactor'])
        $input_type = 'radio';
    else
        $input_type = 'hidden';
    if ($allow['_onefactor']) {
        ?>
        <div>
            <label for="user_login_type">
                <input type="<?php echo $input_type ?>" name="loginbycall_user_login_type" id="user_login_type"
                       value="1" <?php echo ($type == 1 && $input_type != 'hidden') ? 'checked="checked"' : ''
                ?>>
                <?php echo $input_type != 'hidden'?__('One-factor authorization', 'loginbycall'):'' ?>
            </label>
        </div>
    <?php
    }
    if ($allow['_twofactor']) {
        ?>
        <div>
            <label for="user_login_type2">
                <input type="<?php echo $input_type ?>" name="loginbycall_user_login_type" id="user_login_type2"
                       value="2" <?php
                echo ($type == 2 && $input_type != 'hidden') ? 'checked="checked"' : ''
                ?>>
                <?php echo $input_type != 'hidden'?__('Two-factor authorization', 'loginbycall'):'' ?>
            </label>
        </div>
    <?php
    }
}


add_action('show_user_profile', 'loginbycall_show_extra_profile_fields');
add_action('edit_user_profile', 'loginbycall_show_extra_profile_fields');

function loginbycall_show_extra_profile_fields($user)
{
    wp_enqueue_script('jquery-ui-dialog'); // jquery and jquery-ui should be dependencies, didn't check though...
    wp_enqueue_style('wp-jquery-ui-dialog');
    wp_register_script('ajax-login-script', plugin_dir_url(__FILE__) . '/loginbycall.js', array('jquery'));
    wp_enqueue_script('ajax-login-script');
    wp_enqueue_style('custom-login', plugin_dir_url(__FILE__) . '/css/loginbycall.css');
    ?>
    <h3><?php _e('LoginByCall settings', 'loginbycall') ?></h3>
    <table class="form-table">
        <?php if (get_option('loginbycall_register_phone') != 1) { ?>
            <tr>
                <th><label for="switch"><?php _e('Enable LoginByCall', 'loginbycall') ?></label></th>
                <td>
                    <div class="switch">
                        <input name="loginbycall_user_activate_setting" value="1" id="cmn-toggle-1"
                               class="cmn-toggle cmn-toggle-round"
                               type="checkbox" <?php echo get_user_meta($user->ID, 'loginbycall_user_activate_setting', true) == 1 ? 'checked' : '' ?>>
                        <label for="cmn-toggle-1"></label>
                    </div>
                </td>
            </tr>
        <?php } ?>
        <tr>
            <th><label for="loginbycall_phone"><?php _e('Phone', 'loginbycall') ?></label></th>
            <td>
                +<input type="text" name="loginbycall_phone" id="loginbycall_phone"
                        value="<?php echo get_user_meta($user->ID, 'loginbycall_user_phone', true); ?>"
                        class="regular-text"/><br/>
            </td>
        </tr>
        <?php
        $allow = loginbycall_check_allowed_role($user->roles);
        if ($allow['_twofactor'] && $allow['_onefactor']) {
            ?>
            <tr>
                <th><label for="loginbycall_user_factor"><?php _e('LoginByCall mode', 'loginbycall') ?></label></th>
                <td>
                    <?php loginbycall_render_login_types($user); ?>
                </td>
            </tr>
        <?php } ?>
    </table>
    <?php
    if (isset($_SESSION['loginbycall_user_new_phone'])) {
        ?>
        <div id="loginbycall-dialog" class="hidden" style="">
            <div class="errors"></div>
            <?php render_pin_form($user, $_SESSION['loginbycall_user_new_phone']); ?>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large"
                       value="<?php _e('Confirm', 'loginbycall') ?>" disabled="">
            </p>
        </div>
        <script>jQuery(document).ready(function (e) {

                // initalise the dialog
                jQuery("#loginbycall-dialog").dialog({
                    title: "<?php _e('Confirm', 'loginbycall') ?>",
                    dialogClass: "wp-dialog",
                    autoOpen: true,
                    draggable: false,
                    width: "auto",
                    modal: true,
                    resizable: false,
                    closeOnEscape: true,
                    position: {
                        my: "center",
                        at: "center",
                        of: window
                    },
                    open: function () {
                        // close dialog by clicking the overlay behind it
                        jQuery(".ui-widget-overlay").bind("click", function () {
                            jQuery("#my-dialog").dialog("close");
                        })
                    },
                    create: function () {
                        // style fix for WordPress admin
                        jQuery(".ui-dialog-titlebar-close").addClass("ui-button");
                    }
                }).on('dialogclose', function (event) {
                    jQuery.post("<?php echo get_site_url(); ?>/wp-admin/admin-ajax.php?action=loginbycall_close_phone_change", function (data) {

                    });
                });
                jQuery("#my-dialog").dialog("open");
                // bind a button or a link to open the dialog

            });</script>
    <?php } ?>
    <style>

    </style>
<?php
}

add_action('personal_options_update', 'loginbycall_save_extra_profile_fields');
add_action('edit_user_profile_update', 'loginbycall_save_extra_profile_fields');

function loginbycall_save_extra_profile_fields($user_id)
{

    if (!current_user_can('edit_user', $user_id))
        return false;
    $old_phone = get_user_meta($user_id, 'loginbycall_user_phone', true);
    if ((is_numeric($_POST['loginbycall_phone'])) && $old_phone != $_POST['loginbycall_phone']) {
        $_SESSION['loginbycall_user_new_phone'] = $_POST['loginbycall_phone'];
    }elseif($_POST['loginbycall_phone']==''&& $old_phone != $_POST['loginbycall_phone'])
        update_user_meta($user_id, 'loginbycall_user_phone', '');
    //update_user_meta($user_id, 'loginbycall_user_phone', $_POST['loginbycall_phone']);
    update_user_meta($user_id, 'loginbycall_user_activate_setting', $_POST['loginbycall_user_activate_setting']);
    if (isset($_POST['loginbycall_user_login_type']))
        $factor = $_POST['loginbycall_user_login_type'];
    $user = get_user_by('id', $user_id);
    $allow = loginbycall_check_allowed_role($user->roles);

    $type = null;
    if ($allow['_onefactor'] && $factor == 1)
        $type = 1;
    elseif ($allow['_twofactor'] && $factor == 2)
        $type = 2;
    if (isset($type))
        update_user_meta($user_id, 'loginbycall_user_login_type', $type);
}


//plugin installation, creating tables and pages for loginbycall
function loginbycall_install()
{

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    register_loginbycall();
}


//The hook triggered when the page reloads
function loginbycall_run()
{
    load_plugin_textdomain('loginbycall', false, basename(__DIR__) . '/i18n');
}


/**
 * Checks, there is a registered user with this phone number
 * @param $phone
 * @return mixed if the user is found false, else true
 */
function loginbycall_is_unique_phone($phone)
{
    $target_phone = $phone;
    global $wpdb;
    $lbc_user = $wpdb->get_results("SELECT user_id FROM " . $wpdb->prefix . "usermeta WHERE meta_key = 'loginbycall_user_phone' and meta_value = '$target_phone'", ARRAY_A);
    if (count($lbc_user)) {
        return false;
    }

    return true;
}

function loginbycall_delete_user_meta()
{
    global $wpdb;
    $wpdb->get_results("DELETE FROM " . $wpdb->prefix . "usermeta WHERE meta_key = 'loginbycall_user_phone' OR meta_key = 'loginbycall_user_active' OR meta_key = 'loginbycall_user_login_type' OR meta_key = 'loginbycall_user_activate_setting' OR meta_key = 'loginbycall_user_refuse'");
}


add_action('wp_enqueue_scripts', 'prefix_add_my_stylesheet');

function prefix_add_my_stylesheet()
{
    // Respects SSL, Style.css is relative to the current file
    wp_register_style('prefix-style', plugins_url('css/loginbycall.css', __FILE__));
    wp_enqueue_style('prefix-style');
}

add_shortcode('loginbycall_settings', 'function_loginbycall_settings_user');


add_filter('page_template', 'loginbycall_redirect_uri_template');

function loginbycall_redirect_uri_template($page_template)
{

    if (is_page('loginbycall-redirect-uri')) {
        $page_template = dirname(__FILE__) . '/template/page-loginbycall-redirect-uri.php';
    }

    return $page_template;
}

function cp_admin_init()
{
    if (!session_id()) {
        session_start();
    }
}

function loginbycall_login_panel_step1()//connection for registered users
{
    $allow = false;
    if (isset($_SESSION['loginbycall_user_login_id'])) {
        $fuser = get_user_by('ID', $_SESSION['loginbycall_user_login_id']);
        if ($fuser) {
            if (get_user_meta($_SESSION['loginbycall_user_login_id'], 'loginbycall_user_phone', true) == '')
                $allow = true;

        }
    }

    if ($allow) {
        echo '<style>#login p label{display: none;}
    p.submit{text-align: center; margin-top: 5px!important;}
                            p.submit input{float: none!important;}
</style>
<p style="text-align: center;">'.__('Allow the simple login method LoginByСall for your account', 'loginbycall').'
</p>
		<label class="label_inputMsisdn" for="user_phone">'.__('Phone', 'loginbycall').'<br>
		<span><span>+</span>
		<input ' . (get_option('loginbycall_register_phone') == 1 ? 'data-require="1"' : '') . ' data-filled="false" id="inputMsisdn" class="form-control input-lg phone-number text-center" type="tel" name="loginbycall_phone"  value="" maxlength="15">
		</span>
		</label>
        <input type="hidden" name="step1_form" value="1">
	    <div class="block-description">
	    '.__('We will call you from a random number,', 'loginbycall').'
	    <br>'.__('remember the last 4 digits from it.', 'loginbycall').'</div>';
        $user = get_user_by('ID', $_SESSION['loginbycall_user_login_id']);
        $allow = loginbycall_check_allowed_role($user->roles);
        if ($allow['_twofactor'] && $allow['_onefactor'])
            echo '<p style="margin: 5px 0;">'.__('Select LoginByCall mode', 'loginbycall').':</p>';
        loginbycall_render_login_types($user, $olduser = true);
        if (get_option('loginbycall_register_phone') != 1) {
            ?>
            <div style="position: absolute; bottom:5px;">
                <div style="text-align: center;"><label for="loginbycall_user_refuse">
                        <input type="checkbox" name="loginbycall_user_refuse" id="loginbycall_user_refuse" value="1">
                        <?php echo __('Don\'t offer any more', 'loginbycall') ?>
                        </label></div>
            </div>
        <?php
        }
        ?>

        <script>
            var flashError = '<?php echo getFlashError()?>';
        </script>

    <?php

    }
}

function render_pin_form($fuser, $phone)
{
    $allow = loginbycall_check_allowed_role($fuser->roles);
    if (in_array(true, $allow)) {

        //If the time has come to allow a second call.
	//If the PIN is entered incorrectly it is not necessary to call, but to show a mask.
        //If the limits have expired - send to the main page with an error.

        $phoneCall = call_loginbycall($phone);
        if (lbc_get_safe($phoneCall, 'reason') != '') {
	        if (get_user_meta($fuser->ID, 'loginbycall_user_active', true) != 1) {
	            wp_delete_user($fuser->ID);
		        $_SESSION['loginbycall_error'] = lbc_get_safe($phoneCall, 'reason');
		        wp_safe_redirect('wp-login.php?action=register');
		        die();
	        }
            if (lbc_get_safe($phoneCall, 'error') == 'CALL_REPEAT_TIMEOUT') {
                $countdown = ceil($phoneCall->additional->delay);
                $_SESSION['loginbycall_error'] = __('Repeat after', 'loginbycall').' '. $countdown . ' '.__('seconds', 'loginbycall');
            } else {
                $_SESSION['loginbycall_error'] = lbc_get_safe($phoneCall, 'reason');
                wp_safe_redirect('wp-login.php');
                die();
            }

        } else//call initiated
        {
            $_SESSION['call'] = lbc_get_safe($phoneCall, 'call');
            $_SESSION['loginbycall_count_login'] = 0;
            $_SESSION['loginbycall_mask_check'] = substr(lbc_get_safe($phoneCall, 'mask'), -lbc_get_safe($phoneCall, 'codelen'));
            $_SESSION['loginbycall_phone_mask'] = substr(lbc_get_safe($phoneCall, 'mask'), 0, strlen(lbc_get_safe($phoneCall, 'mask')) - lbc_get_safe($phoneCall, 'codelen'));
            $countdown = lbc_get_safe($phoneCall, 'repeat_timeout');
        }
        ?>
        <p style="text-align: center; padding: 5px; font-size: 15px;"><?php _e('Your cell number', 'loginbycall');?> +<?php echo $phone ?></p>
        <div class="pin_container"
             style="background-image:url('<?php echo plugin_dir_url(__FILE__) ?>/img/phone.svg');">
            <div>
                <div
                    class="phone_mask"><?php echo '+' . (isset($_SESSION['loginbycall_phone_mask']) ? $_SESSION['loginbycall_phone_mask'] : '') ?></div>
                <input type="phone" name="loginbycall_call_maskphone" id="user_mask" class="input" value="" size="4"
                       maxlength="4" style="width:initial;">
            </div>
        </div>

        <div class="block-description">

            <?php echo __('LoginByCall is calling you. Enter 4 last digits of the incoming number.', 'loginbycall') ?>
        </div>
        <div id="call_status"></div>
        <div id="countdowntext"><?php echo __('Repeat after', 'loginbycall') ?> <?php echo $countdown ?> <?php echo __('seconds', 'loginbycall') ?></div>
        <style>
            #login p label {
                display: none;
            }

            #countdowntext {
                text-align: center;
            }

            p.submit {
                text-align: center;
                margin-top: 5px !important;
            }

            p.submit input {
                float: none !important;
            }
        </style>
        <script>
            var _countDown = <?php echo $countdown ?>;
            var flashError = '<?php echo getFlashError()?>';
            var loginUrl = '<?php echo wp_login_url() ?>';
        </script>
    <?php
    }
}


//Request to api by phone number and initiate a call
function loginbycall_login_panel_step2()
{

    if (isset($_SESSION['loginbycall_user_login_id']))
        $fuser = get_user_by('ID', $_SESSION['loginbycall_user_login_id']);
    else
        $fuser = false;
    if ($fuser) {
        render_pin_form($fuser, get_user_meta($fuser->ID, 'loginbycall_user_phone', true));
    }

}

function loginbycall_change_wplogin_title() {
    return '<p class="message register">'.__('LoginByCall is calling you. Enter 4 last digits of the incoming number.', 'loginbycall').'</p>';
}

function loginbycall_filter_gettext( $translated, $original, $domain ) {
    // Use the text string exactly as it is in the translation file
    if ( $original == "Password" ) {
        $translated = __('Password or leave field blank','loginbycall');
    }
    return $translated;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
if($action=='register')
    add_filter('login_message', 'loginbycall_change_wplogin_title');
elseif($action=='login')
    add_filter( 'gettext', 'loginbycall_filter_gettext', 10, 3 );
function loginbycall_form_panel()//for new user registration case
{


    echo '<p>
            <label class="label_inputMsisdn"  for="user_phone">'.__('Phone', 'loginbycall').'<br>';
    echo '<span><span>+</span><input data-require="' . get_option('loginbycall_register_phone') . '" data-filled="false" id="inputMsisdn" class="form-control input-lg phone-number text-center" type="tel" name="loginbycall_user_phone"  value="" maxlength="15"></span>';
    echo '</label>
    </p>';
    $user = new WP_User();
    $user->roles = array(get_option('default_role','subscriber'));
    $allow = loginbycall_check_allowed_role($user->roles);
    if ($allow['_twofactor'] && $allow['_onefactor'])
        echo '<p style="margin: 5px 0;">'.__('Select LoginByCall mode', 'loginbycall').'</p>';
    loginbycall_render_login_types($user);
    echo '<style>
#reg_passmail{
    display: none;
}</style>';
    if ($allow['_twofactor'] && $allow['_onefactor'])
        echo '<p>'.__('You can change the settings in your profile', 'loginbycall').'</p>';
}

function loginbycall_uninstall_hook()
{
    delete_option('loginbycall_api_id');
    delete_option('loginbycall_api_key');
    delete_option('loginbycall_credential_active');
    delete_option('loginbycall_new_api_id');
    delete_option('loginbycall_notification_email');
    delete_option('loginbycall_register_phone');
    loginbycall_delete_user_meta();
    loginbycall_update_roles(array('_onefactor', '_twofactor'));
}

register_deactivation_hook(__FILE__, 'loginbycall_uninstall_hook');

if (isset($_REQUEST['loginbycall_step']) && $_REQUEST['loginbycall_step'] == 1)
    add_action('login_form', 'loginbycall_login_panel_step1');
elseif (isset($_REQUEST['loginbycall_step']) && $_REQUEST['loginbycall_step'] == 2) {

    add_action('login_form', 'loginbycall_login_panel_step2');
} else
    add_action('login_form', 'default_login_form');

function default_login_form()
{
    echo "<script>
            var flashError='" . getFlashError() . "';
                </script>";
}

add_action('register_form', 'loginbycall_form_panel');


add_action('init', 'cp_admin_init');
add_action('init', 'loginbycall_run');
add_action('user_register', 'loginbycall_registration_save', 10, 1);


//call login URL for verification
add_action('wp_ajax_nopriv_verify_logincall', 'verify_logincall');
add_action('wp_ajax_verify_logincall', 'verify_logincall');
function verify_logincall()
{
    echo get_option('loginbycall_api_id');
    die();
}


add_action('wp_ajax_nopriv_call_status_ajax', 'call_status_ajax');
add_action('wp_ajax_call_status_ajax', 'call_status_ajax');
function call_status_ajax()
{
    header('Content-Type: application/json');
    if (isset($_SESSION['call']))
        $status = call_status($_SESSION['call']);
    $status_id = lbc_get_safe($status, 'status');
    if ($status_id == 32)
        $str = lbc_get_safe($status, 'last_error');
    elseif ($status_id >= 4)
        $str = __('Call completed', 'loginbycall');
    else
        $str = __('Ringing...', 'loginbycall');
    echo json_encode(array('id' => $status_id, 'textMsg' => $str));
    die();
}

add_action('wp_ajax_loginbycall_close_phone_change', 'loginbycall_close_phone_change');
function loginbycall_close_phone_change()
{
    unset($_SESSION['loginbycall_user_new_phone']);
}


add_action('wp_ajax_nopriv_verify_logincall_pin', 'verify_logincall_pin');
add_action('wp_ajax_verify_logincall_pin', 'verify_logincall_pin');


function verify_logincall_pin()
{

    header('Content-Type: application/json');
    $data = array('redirect' => 0);
    if ((isset($_SESSION['loginbycall_user_login_id']) || (isset($_SESSION['loginbycall_user_new_phone']) && is_user_logged_in())) && isset($_POST['loginbycall_call_maskphone'])) {

        $_SESSION['loginbycall_count_login']++;
        if ($_SESSION['loginbycall_count_login'] > 3) {
            $_SESSION['loginbycall_mask_check'] = null;
            $data['error'] = '<strong>'.__('ERROR', 'loginbycall').'</strong>'.__(': Maximum number of retries exceeded.', 'loginbycall');
            echo json_encode($data);
            die();
        }

        if (isset($_SESSION['loginbycall_mask_check'])&&$_SESSION['loginbycall_mask_check'] == $_POST['loginbycall_call_maskphone']) {
            $_SESSION['loginbycall_count_login'] = 0;

            if (!is_user_logged_in()) {
                wp_set_auth_cookie($_SESSION['loginbycall_user_login_id']);
                if (get_user_meta($_SESSION['loginbycall_user_login_id'], 'loginbycall_user_active', true) != 1) {
                    update_user_meta($_SESSION['loginbycall_user_login_id'], 'loginbycall_user_active', 1);
                }
                if (get_user_meta($_SESSION['loginbycall_user_login_id'], 'loginbycall_user_activate_setting', true) != 1) {
                    update_user_meta($_SESSION['loginbycall_user_login_id'], 'loginbycall_user_activate_setting', 1);
                }

                unset($_SESSION['loginbycall_user_login_id']);
                $data = array('redirect' => 1);
            } elseif (isset($_SESSION['loginbycall_user_new_phone'])) {
                $user = wp_get_current_user();
                update_user_meta($user->ID, 'loginbycall_user_phone', $_SESSION['loginbycall_user_new_phone']);
                unset($_SESSION['loginbycall_user_new_phone']);
                $data = array('redirect' => 2);

            }
            else
                $data = array('redirect' => 1);
            call_hangup();

        } else {
            $data['error'] = '<strong>'.__('ERROR', 'loginbycall').'</strong>'.__(': Phone not accepted.');

        }
    }
    echo json_encode($data);
    die();
}


add_action('wp_ajax_loginbycall_get_balance', 'loginbycall_get_balance');
function loginbycall_get_balance()
{
    $balance = balance_loginbycall();
    echo(lbc_get_safe($balance, 'balance') - lbc_get_safe($balance, 'consumed'));
    die();
}


add_filter('check_password', 'loginbycall_check_password', 10, 4);
add_filter('authenticate', 'loginbycall_auth_signon', 10, 3);

function loginbycall_auth_signon($user, $username, $password)
{
    //if user have already passed the authorization form
    if (isset($_SESSION['loginbycall_user_login_id']) && is_numeric($_SESSION['loginbycall_user_login_id'])) {
        $user_id = $_SESSION['loginbycall_user_login_id'];
    }

    //we get here from the form for already registered users without a phone, as well as check the session for two-factor authentication
    if (isset($_REQUEST['step1_form']) && $_REQUEST['step1_form'] == 1 && isset($user_id) && isset($_SESSION['loginbycall_user_login_id_safe']) && $_SESSION['loginbycall_user_login_id_safe']) {
        //if refused or the phone is not specified, then login by password
        //You can refuse if there is no demand and did not refuse earlier
        if (((isset($_REQUEST['loginbycall_user_refuse']) && $_REQUEST['loginbycall_user_refuse'] == 1) || $_REQUEST['loginbycall_phone'] == '') &&
            get_option('loginbycall_register_phone') != 1 && get_user_meta($user_id, 'loginbycall_user_refuse', true) != 1
        ) {
            update_user_meta($user_id, 'loginbycall_user_refuse', isset($_REQUEST['loginbycall_user_refuse']) ? $_REQUEST['loginbycall_user_refuse'] : 0);
            unset($_SESSION['loginbycall_user_login_id']);
            wp_set_auth_cookie($user_id);
            wp_safe_redirect('/wp-admin/');
            die();
        } else {
            $fuser = get_user_by('id', $user_id);

            if (!loginbycall_is_unique_phone($_POST['loginbycall_phone'])) {
                $_SESSION['loginbycall_error'] = '<strong>'.__('ERROR', 'loginbycall').'</strong>'.__(': This phone is already registered.');
                wp_safe_redirect('wp-login.php?loginbycall_step=1');
                die();
            }
            $allow = loginbycall_check_allowed_role($fuser->roles);
            if (in_array(true, $allow)) {
                update_user_meta($user_id, 'loginbycall_user_login_type', $_POST['loginbycall_user_login_type']);
                update_user_meta($user_id, 'loginbycall_user_phone', $_POST['loginbycall_phone']);
                wp_safe_redirect('wp-login.php?loginbycall_step=2');
                die();
            }
        }
    }

    if (!empty($username))//If the session is empty, then we find the user or exit by mistake
    {
        if (is_email($username))
            $find = 'email';
        else
            $find = 'login';
        $fuser = get_user_by($find, $username);

        if ($fuser&&$password=='')//if the user is found and the password is empty then let's continue
        {
            $allow = loginbycall_check_allowed_role($fuser->roles);
            //if there is one-factor authorization, the user has access, he did not refuse and the status of the server is ok,
	    //then initiate loginbycall authorization
            if (get_user_meta($fuser->ID, 'loginbycall_user_login_type', true) == 1 && $allow['_onefactor']  && server_status() == 1&&get_user_meta($fuser->ID, 'loginbycall_user_activate_setting', true)==1) {
                $_SESSION['loginbycall_user_login_id'] = $fuser->ID;
                $_SESSION['loginbycall_user_login_id_safe'] = false;
                if (isset($_SESSION['loginbycall_mask_check']))
                    unset($_SESSION['loginbycall_mask_check']);
                if (is_numeric(get_user_meta($fuser->ID, 'loginbycall_user_phone', true)))
                {
                    wp_safe_redirect('wp-login.php?loginbycall_step=2');
                    die();
                }
            }
        }//go to password check
    }

    return $user;
}

//checks one-factor or two-factor authorization
function loginbycall_check_password($check, $password, $hash, $user_id)
{
    //success, if refused and there is no requirement for LoginByCall
    if (get_user_meta($user_id, 'loginbycall_user_refuse', true) == 1 &&get_user_meta($user_id, 'loginbycall_user_activate_setting', true) != 1)//если юзер отказался то обычная проверки
        return $check;

    $user = get_user_by('ID', $user_id);
    if($user)
    {
        $allow = loginbycall_check_allowed_role($user->roles);
        //authorization if one-factor
        if($allow['_onefactor']&&$check&&get_user_meta($user->ID, 'loginbycall_user_login_type', true) == 1)
            return $check;

        //authorization if the password is two-factor or if one-factor
        if ((($check&&$allow['_twofactor'])||$allow['_onefactor']) && server_status() == 1) {
            $_SESSION['loginbycall_user_login_id'] = $user_id;
            $_SESSION['loginbycall_user_login_id_safe'] = true;
            if (isset($_SESSION['loginbycall_mask_check']))
                unset($_SESSION['loginbycall_mask_check']);

            //check that the phone is entered

            if (!is_numeric(get_user_meta($user_id, 'loginbycall_user_phone', true))) {
                wp_safe_redirect('wp-login.php?loginbycall_step=1');
                die();
            } elseif(get_user_meta($user_id, 'loginbycall_user_activate_setting', true)==1)
            {
                wp_safe_redirect('wp-login.php?loginbycall_step=2');
                die();
            }

        }
    }


        return $check;
}

function loginbycall_phone_check($errors, $sanitized_user_login, $user_email)
{

    if (get_option('loginbycall_register_phone') == 1 && $_POST['loginbycall_user_phone'] == '') {
        $errors->add('zipcode_error', '<strong>'.__('ERROR', 'loginbycall').'</strong>'.__(': Enter your cell number', 'loginbycall'));
    } elseif (strlen($_POST['loginbycall_user_phone']) > 0 && !loginbycall_is_unique_phone($_POST['loginbycall_user_phone'])) {
        $errors->add('zipcode_error', '<strong>'.__('ERROR', 'loginbycall').'</strong>'.__(': This phone is already registered.', 'loginbycall'));
    }
    return $errors;
}

add_filter('registration_errors', 'loginbycall_phone_check', 10, 3);


function loginbycall_registration_save($user_id)
{
    if (isset($_POST['loginbycall_user_phone'])) {
        update_user_meta($user_id, 'loginbycall_user_phone', $_POST['loginbycall_user_phone']);
        if (($_POST['loginbycall_user_phone']) != '') {
            update_user_meta($user_id, 'loginbycall_user_activate_setting', 1);
            update_user_meta($user_id, 'loginbycall_user_active', 0);//activated if successful login
            wp_schedule_single_event(time() + 60 * 60 * 24, 'loginbycall_delete_users', array($user_id));
        }

    }
    if (isset($_POST['loginbycall_user_login_type']))
        update_user_meta($user_id, 'loginbycall_user_login_type', $_POST['loginbycall_user_login_type']);
    if (server_status() == 1 && get_user_meta($user_id, 'loginbycall_user_activate_setting', true) == 1) {
        if (get_option('loginbycall_'.get_option('default_role','subscriber').'_onefactor') == 1) {
            $_SESSION['loginbycall_user_login_id'] = $user_id;
            $_SESSION['loginbycall_user_login_id_safe'] = false;
            if (isset($_SESSION['loginbycall_mask_check']))
                unset($_SESSION['loginbycall_mask_check']);
            wp_safe_redirect('wp-login.php?loginbycall_step=2');
            die();
        }
    }
}
add_action('loginbycall_delete_users', 'loginbycall_delete_users_daily', 10, 1);
function loginbycall_delete_users_daily($user_id)
{
    if (get_user_meta($user_id, 'loginbycall_user_active', true) != 1 && get_user_meta($user_id, 'loginbycall_user_phone', true) != '') {
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
    }
}

register_activation_hook(__FILE__, 'loginbycall_install');

?>

<?php
function service_loginbycall($method, $data = array(), $crypto = false, $nonCryptData = array())
{
    if ($crypto) {
        $cryptodata['call-api-id'] = get_option('loginbycall_api_id');
        $cryptodata['timestamp'] = time();
        $cryptodata['nonce'] = loginbycall_generator(20);
        $dataToSend = $cryptodata = array_merge($cryptodata, $data);
        $signed = $method;
        foreach ($cryptodata as $key => $value) {
            $signed .= chr(0x00) . $key . chr(0x0-0) . $value;
        }
        $dataToSend['signature'] = hash_hmac('sha512', $signed, get_option('loginbycall_api_key'));
    } else
        $dataToSend = $data;
    $dataToSend = array_merge($dataToSend, $nonCryptData);
    $str = array();
    foreach ($dataToSend as $key => $v) {
        $str[] = $key . '=' . $v;
    }
    //var_dump('https://internal.loginbycall.net/callapi/v2.0/' . $method.'?'.implode('&',$str));
    return $info = json_decode(@file_get_contents('https://internal.loginbycall.net/callapi/v2.0/' . $method . '?' . implode('&', $str)));
}

function call_loginbycall($msisdn)
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        //check ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        //to check ip is pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $info=service_loginbycall('call', array('msisdn'=>$msisdn,'ip_address'=>$ip),true);

    //$info = json_decode('{"call":"ONP5qZde0pn43nDdRgmcNaEBlqoKyix3vK487LEQ","mask":"79256880636","codelen":4,"repeat_timeout":10}');
    //$info = json_decode('{"clazz":"PROCESS","reason":"ddd","error":"CALL_REPEAT_TIMEOUT","additional":{"delay":23.563}}');

    return $info;
}


function pay_loginbycall($amount)
{
    echo $amount = trim(str_replace(array('.', ','), '', $amount));
    $rUrl = get_site_url() . '/wp-admin/admin.php?page=loginbycall';
    $links = array('success_url' => $rUrl, 'fail_url' => $rUrl, 'return_url' => $rUrl,);
    $info = service_loginbycall('pay', array('amount' => $amount), true, $links);
    if (property_exists($info, 'html')) {
        echo '<div class="loginbycall_pay_form">'.$info->html.'</div><script>jQuery(document).ready(function(){
                jQuery(".loginbycall_pay_form form").submit();
                    });</script>';
    }
}

function lbc_get_safe($item, $property)
{

    if (is_object($item)&&property_exists($item, $property))
        return $item->$property;
    else
        return '';
}
function call_hangup()
{
    $info = service_loginbycall('call-hangup', array('call'=>$_SESSION['call']),true);
}

function register_loginbycall()
{
    $data['domain'] = str_replace(array('http://', 'https://'), '', get_site_url());
    $data['verify_url'] = get_site_url() . '/wp-admin/admin-ajax.php?action=verify_logincall';
    /*domain - домен, на котором будет использоваться настоящий API. Должен быть именем домена, без протокола и пути (например example.com). Обязательный параметр.*/
    $data['admin_email'] = get_option('admin_email');
    //невозможно зарегать admin_phone  при текущем раскладе
    /*admin_phone - номер телефона администратора клиента. Необязательный параметр.
    Параметры ответа
    call_api_id - идентификатор аккаунта LoginByCall api.
    verify_url - адрес для проверки принадлежности домена. Возвращается либо тот, который получен в запросе, либо созданный сервисом LoginByCall автоматически.*/
    $info = service_loginbycall('register', $data);
    if(lbc_get_safe($info,'call_api_id')!='')
    update_option('loginbycall_api_id', lbc_get_safe($info,'call_api_id'));
    update_option('loginbycall_credential_active',0);
}

function status_loginbycall()
{
    return $info = service_loginbycall('status', array('call-api-id' => get_option('loginbycall_api_id')));
    /*
     * activated - 1 (аккаунт активирован) или 0 (аккаунт не активирован).
        blocked - 1 (аккаунт заблокирован) или 0 (аккаунт не заблокирован).
        allow_unsecure_calls - 1 или 0 - разрешил или нет администратор сервиса LoginByCall не подписывать цифровой подписью запросы.
     */
}

function server_status()
{
    $info = service_loginbycall('server-status', array());

    if(lbc_get_safe($info,'server_status')==1&&get_option('loginbycall_credential_active')==1)
        return lbc_get_safe($info,'server_status');
    else
        return 0;
}

function call_status($handler)
{
    return service_loginbycall('call-status',array('call'=>$handler),true);
}

function change_credential($email=null)
{
    if(isset($email))
        $data['admin_email'] = $email;
    else
        $data['admin_email'] = getNotificationEmail();
    $info = service_loginbycall('change_credential', $data, true);
    $new_api=lbc_get_safe($info, 'call_api_id');
    if($new_api)
    {
        if(isset($email))
            setNotificationEmail($email);
        update_option('loginbycall_new_api_id', $new_api);
        update_option('loginbycall_credential_active',0);
    }

}
function credential_confirm()
{
    $info = service_loginbycall('credential_confirm', array(), true);
    $r=lbc_get_safe($info,'reason');
    if($r=='')
    {
        update_option('loginbycall_credential_active',1);
    }
    return $info;
}


function options_loginbycall($limit)
{
    return $info = service_loginbycall('options', array('balance_notify_limit' => $limit), true);
}


function balance_loginbycall()
{
    //call-api-id, timestamp, nonce.
    return service_loginbycall('balance', array(), true);
    /*
     * balance - сумма всех платежей клиента в копейках.
consumed - сумма всех расходов клиента в копейках.
balance_notify_limit - установленный минимальный баланс в копейках при котором будут отправляться сообщения администратору. Подробнее см. в методе register.
last_payments - массив из 5 последних платежей клиента. Каждый элемент массива это объект с двумя свойствами value - сумма платежа и created - UNIX time даты платежа.
     */
}


/* Функция получения значения единственного поля */

function get_one_field($select, $from, $where, $value)
{
    global $wpdb;
    $i_user = $wpdb->get_results("SELECT " . $select . " FROM " . $from . " WHERE " . $where . " = " . $value, ARRAY_A);
    if (count($i_user)) {
        return $i_user[0][$select];
    } else {
        return false;
    }
}

/* функция генерации пароля */

function loginbycall_generator($max)
{
    if (!$max) {
        $max = 12;
    }
    $password = null;
    $chars = "qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
    $size = StrLen($chars) - 1;
    while ($max--)
        $password .= $chars[rand(0, $size)];
    return $password;
}



function loginbycall_check_allowed_role($user_roles)
{
    foreach (array('_onefactor','_twofactor') as $factor)
    {
        $allow[$factor]=false;
        foreach($user_roles as $user_role)
        {
            if(get_option('loginbycall_' . $user_role . $factor))
                $allow[$factor]=true;
        }
    }
    return $allow;
}

//возвращаем ошибку если телефон обязательный и хотябы одного способо нет в одной роле
function loginbycall_update_roles($factors,$loginbycall_register_phone=0)
{
    $error=false;
    $roles_count=array();
    foreach ($factors as $factor)
        foreach (get_editable_roles() as $role_name => $role_info) {
            if (isset($_POST['loginbycall_' . $role_name . $factor]))
            {
                if($loginbycall_register_phone==1)
                {
                    if(!isset($roles_count[$role_name]))
                        $roles_count[$role_name]=1;
                }
                update_option('loginbycall_' . $role_name . $factor, $_POST['loginbycall_' . $role_name . $factor]);
            }
            else
                delete_option('loginbycall_' . $role_name . $factor);
        }
    if($loginbycall_register_phone==1)
    foreach (get_editable_roles() as $role_name => $role_info) {
        if(!isset($roles_count[$role_name]))
            return $error=true;
    }
    return $error;

}

function getFlashError()
{
    if(isset($_SESSION['loginbycall_error']))
    {
        $error=$_SESSION['loginbycall_error'];
        unset($_SESSION['loginbycall_error']);
        return $error;

    }
    return '';
}
function getNotificationEmail()
{
    $email=get_option('loginbycall_notification_email');
    if(!$email)
    {
        $email=get_option('admin_email');
        update_option('loginbycall_notification_email',$email);
    }
    return $email;


}
function setNotificationEmail($email)
{
    update_option('loginbycall_notification_email',$email);
}
?>
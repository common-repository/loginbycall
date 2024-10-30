jQuery(document).ready(function () {
    //all this code is needed only to process the countdown timer when LoginByCall calling
    if (typeof _countDown !== 'undefined')
    {

        function hidepinarrow(x) {
            var pinarrow = document.getElementById('pin-arrow');
            if (pinarrow)
                pinarrow.style.display = 'none';
        }

        function showpinarrow(x) {
            var pinarrow = document.getElementById('pin-arrow');
            if (pinarrow)
                pinarrow.style.display = 'block';
        }

        function checkLength() {
            var fieldLength = document.getElementById('pin').value.length;
            if (fieldLength < 4) {
                var pinarrow = document.getElementById('pin-arrow-');
                if (pinarrow)
                    pinarrow.id = 'pin-arrow';
                $('#user_mask').removeClass('pin-done');
                return true;
            } else {
                var str = document.getElementById('pin').value;
                str = str.substring(0, 4);
                document.getElementById('pin').value = str;
                document.getElementById('pin-arrow').id = 'pin-arrow-';
                $('#user_mask').addClass('pin-done');
            }
        }

        var sTime = new Date().getTime();
        var countDown;

        function UpdateTime() {
            var cTime = new Date().getTime();
            var diff = cTime - sTime;
            var seconds = countDown - Math.floor(diff / 1000);
            if (seconds <= 0) {
                clearInterval(counter);
                document.getElementById('countdowntext').innerHTML = '<a class=\"button-recall\" href=\"?loginbycall_step=2\">Repeat call</a>';
            } else {
                document.getElementById('countdowntext').innerHTML = 'Repeat call after <span>'
                + seconds + '</span>&nbsp;seconds';
            }
        }

        var counter;

        function StartUpdateTime() {
            countDown = _countDown;
            UpdateTime();
            counter = setInterval(UpdateTime, 500);
        }

        // Input mask password
        var setCodeFormValidation, codeInput, getCode;

        codeInput = function () {
            return jQuery('#user_mask');
        }

        getCode = function () {
            var code;
            code = codeInput().val() && codeInput().val().replace(/[^\d]/g, "");
            if (!code || (code.length != 4))
                return '';
            return code;
        }

        var no_submit = false;

        function makeCheck(email) {

            var re = /^([a-z0-9_\-]+\.)*[a-z0-9_\-]+@([a-z0-9][a-z0-9\-]*[a-z0-9]\.)+[a-z]{2,6}$/i;
            return re.test(email);
        }

        jQuery('#user_mask').focus();


        jQuery('#loginform').on('submit', function (e) {
            e.preventDefault();
            verify_pin();
        });

        function verify_pin()
        {
            console.log(no_submit);
            if (no_submit)
                return;
            var code = getCode();
            if (!code) {
                setError('<strong>ERROR</strong>: Phone not accepted.<br></div>');
                return false;
            }
            no_submit = true;
            jQuery.post( "/wp-admin/admin-ajax.php?action=verify_logincall_pin",
                {loginbycall_call_maskphone:code}).done(function( data ) {
                    no_submit = false;
                    if(data.redirect==0)
                    {
                        if(data.error.length>0)
                            setError(data.error);
                    }
                    else if(data.redirect==1) {
                        window.location = 'wp-admin';
                    }
                    else if(data.redirect==2)
                    {
                        location.reload();
                    }

                });
        }

        codeInput().on('change input propertychange keyup paste', function () {

            var haveCode = getCode();
            jQuery('#wp-submit').prop('disabled', !haveCode);

            if (haveCode) {
                verify_pin();
            }
        }).trigger('change');


        StartUpdateTime();
        var call_intervall=setInterval(status_call,5000);
        function status_call(){
            jQuery.post( "/wp-admin/admin-ajax.php?action=call_status_ajax",
                {}).done(function( data ) {
                    //console.log(data);
                    if(data.id>=4)
                        clearInterval(call_intervall);
                    jQuery('#call_status').html(data.textMsg);
                });
        }


    }
    function cleanPhone(phone) {
        return phone.replace(/[^\d.]/g, "");
    }
    function getPhone() {
        var msisdn_original = jQuery('#inputMsisdn').val();
        var msisdn = cleanPhone(msisdn_original);
        if((!msisdn&&jQuery('#inputMsisdn').data('require')==1) || ((msisdn.length<9&&msisdn.length>0)||msisdn.length>15))
            return null;
        return {
            msisdn_original: msisdn_original,
            msisdn: msisdn
        };
    }
    jQuery('#loginbycall_user_refuse').change(function() {
        if (this.checked) {
            jQuery('#wp-submit').prop('disabled', false);
        } else {

            jQuery('#wp-submit').prop('disabled', !getPhone());
        }
    });
    jQuery('#inputMsisdn').on('change input propertychange keyup paste', function(evt) {
        jQuery(this).val(cleanPhone(jQuery(this).val()))
        if(jQuery("#loginbycall_user_refuse").prop('checked'))
            jQuery('#wp-submit').prop('disabled', false);
        else
            jQuery('#wp-submit').prop('disabled', !getPhone());
    }).trigger('change');

    function validate_digit(evt) {
    }

    function setError(error) {
        jQuery('#login h1, #loginbycall-dialog .errors').after('<div id="login_error" class="notice notice-error">'+error+'</div>');
    }
    if(typeof flashError !== 'undefined'&&flashError !=='')
        setError(flashError)
});

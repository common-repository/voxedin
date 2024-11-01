

function pollForLoginCompletion(isregistration) {
    jQuery.ajax({
        url: voxedInAjaxVars.ajaxurl,
        type: 'POST',
        data: {
            login_id: jQuery('input#login_id').val(),
            nonce: voxedInAjaxVars.loginNonce,
            action: 'islogincomplete'
        },
        dataType: 'json',
        success: function (data) {
            if (data.poll) {
              setTimeout(function() { pollForLoginCompletion(isregistration) }, 1000);
            } else if (!data.error) {
                jQuery('#voxedinsdk').animate({ height: 1 });
                if (isregistration) {
                  jQuery('#viw_enrolled').prop('checked', true);
                } else {
                  jQuery('#loginform').submit();
                }
            } else {
                jQuery('#voxedinsdk').animate({ height: 1 });
                jQuery('#loginform').submit();
            }
        },
        error: function (reason) {
            primeFormSubmission();
        },
        timeout: 30000
    });
}

function getPayloadForUser(isregistration) {
    var lid = jQuery('input#login_id').val();
    var uid = jQuery('input#user_login').val();
    var nce = voxedInAjaxVars.payloadNonce;
    
    jQuery.ajax({
        url: voxedInAjaxVars.ajaxurl,
        type: 'POST',
        data: {
            login_id: lid,
            username: uid,
            registration: isregistration,
            nonce: nce,
            action: 'getpayloadforuser'
        },
        dataType: 'json',
        success: function (data) {
            if (data && data.use_voxedin === true) {

                jQuery('#voxedinsdk').animate({ height: 220 });

                // Set the form values..
                jQuery('#voxedinsdk').contents().find('#access_key').val(data.access_key);
                jQuery('#voxedinsdk').contents().find('#iv').val(data.iv);
                jQuery('#voxedinsdk').contents().find('#payload').val(data.payload);

                // ..and kick off the voice login interactions
                jQuery('#voxedinsdk').contents().find('#voxedinsdkform').submit();

            } else {

                jQuery('#loginform').submit();
                return;

            }
            //..then wait for the result
            pollForLoginCompletion(isregistration);
        },
        error: function (reason) {
            primeFormSubmission();
        },
        timeout: 5000
    });
}

function primeFormSubmission() {
    jQuery('input[type=submit]').removeAttr('disabled');

    jQuery('#loginform').submit(function () {
        if (jQuery('input[type=submit]').attr('disabled')) {
            return true;
        } else {
            jQuery('input[type=submit]').attr('disabled', 'disabled');

            // Translate the provided username into a payload and begin the voice login process
            getPayloadForUser(false);

            return false;
        }
    });
}

jQuery(document).ready(function () {
    primeFormSubmission();
});

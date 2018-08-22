/** MauticExtendeeFormTabBundle **/

Mautic.generateFieldsFormTab = function () {
    var formId = mQuery('#modify-form-result').html('');
    var formId = mQuery('.formtab-campaign-form').val();
    Mautic.ajaxActionRequest('plugin:extendeeFormTab:generateFieldsFormTab', {'formId': formId}, function (response) {
        if (response.content) {
            mQuery('#modify-form-result').html(response.content);
        }
    })
}

var formId = mQuery('.formtab-campaign-form').val();
if(formId) {
    Mautic.ajaxActionRequest('plugin:extendeeFormTab:generateFieldsFormTab', {'formId': formId}, function (response) {
        if (response.content) {
        //    mQuery('#modify-form-result').html(response.content);
        }
    })
}
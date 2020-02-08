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
if (formId) {
    Mautic.ajaxActionRequest('plugin:extendeeFormTab:generateFieldsFormTab', {'formId': formId}, function (response) {
        if (response.content) {
            //    mQuery('#modify-form-result').html(response.content);
        }
    })
}

Mautic.updateComplexConditionEventOptions = function () {
    var complexConditionSelectNode = mQuery("#campaignevent_properties_conditions");

    complexConditionSelectNode.children().remove();

    var selected = complexConditionSelectNode.data("selected");

    for (var eventId in Mautic.campaignBuilderCanvasEvents) {
        var event = Mautic.campaignBuilderCanvasEvents[eventId];

        if (event.type !== 'form.complex.condition' && (event.type === 'form.field_value' || event.type === 'form.tab.date.condition')) {
            var opt = mQuery("<option />")
                .attr("value", event.id)
                .text(event.name)

            if (mQuery.inArray(event.id+"", selected) > -1 || mQuery.inArray(event.id, selected) > -1) {
                opt.attr("selected", "selected");
            }
            complexConditionSelectNode.append(opt);
        }
    }

    complexConditionSelectNode.trigger("chosen:updated");
};

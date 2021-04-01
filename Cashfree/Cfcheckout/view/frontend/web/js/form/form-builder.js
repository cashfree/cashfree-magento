define(
    [
        'jquery',
        'underscore',
        'mage/template'
    ],
    function ($, _, mageTemplate) {
        'use strict';

        return {
          
            build: function (formData) {
                var formTmpl = mageTemplate('<form action="<%= data.action %>" id="cfcheckout_payment_form"' +
                    ' method="POST" hidden enctype="application/x-www-form-urlencoded">' +
                        '<% _.each(data.fields, function(val, key){ %>' +
                            '<input value=\'<%= val %>\' name="<%= key %>" type="hidden">' +
                        '<% }); %>' +
                    '</form>');

                return $(formTmpl({
                    data: {
                        action: formData.action,
                        fields: formData.fields
                    }
                })).appendTo($('[data-container="body"]'));
            }

        };
    }
);

var AdminListControl = {
    init: function() {
        AdminListControl.initToggleSearchFilters();
        AdminListControl.initCheckList();
    },

    initToggleSearchFilters: function() {
        $('.jsBtnSearchFilterBox').click(function() {
            $('.jsSeachFilterBox').toggle();
        });
    },

    initCheckList: function() {
        $('.jsBulkCheckList .jsBulkCheckAll').change(function() {
            var allCheckbox = $(this).parents('.jsBulkCheckList').eq(0).find('.jsCheckOne');

            if ($(this).is(':checked')) {
                allCheckbox.prop('checked', true);
            } else {
                allCheckbox.prop('checked', false);
            }
        })
    }
}

var OrderAdminList = {
    printUrl: null,

    init: function() {
        OrderAdminList.initPrint();
    },

    initPrint: function() {
        $('.jsBtnPrintMultiOrders').click(function() {
            var ids = [];
            $('.jsBulkCheckList:first input.jsCheckOne:checked').each(function(index, order){
                ids.push($(order).val());
            })

            if (ids.length > 0) {
                var url = OrderAdminList.printUrl + "?" + $.param({'ids': ids});
                var win = window.open(url, '_blank');
                win.focus();
            }

            return false;
        });
    }
}

var OrderAdminEdit = {

    init: function() {
        OrderAdminEdit.initEmailForm();
        OrderAdminEdit.initReverseOrderItem();
        OrderAdminEdit.initEmailSendConfirmation();

        $('.jsBtnEditShippingAddress').click(function(){
            $('.jsDetailsEditShippingAddress').show();
        });

        $('.jsBtnEditBillingAddress').click(function(){
            $('.jsDetailsEditBillingAddress').show();
        });
    },

    initReverseOrderItem: function() {
        $('.jsBtnReverseOrderItem').click(function(){
            $(this).parent('.jsSectionReverseOrderItem').find('.jsFieldReverseOrderItem').show();
            $('.jsBtnReverseOrderItem').hide();
        });
    },

    initEmailForm: function() {
        $('.jsOrderNotificationForm .jsOrderNotificationSelect').change(function(){
            $(this).parents('.jsOrderNotificationForm').find('.jsOrderNotificationParam').hide().find('input').prop('disabled', true);
            $(this).parents('.jsOrderNotificationForm').find('.jsOrderNotificationParam').hide().find('textarea').prop('disabled', true);

            var className = '.jsOrderNotificationParam' + $(this).val();
            $(this).parents('.jsOrderNotificationForm').find(className).show().find('input').prop('disabled', false);
            $(this).parents('.jsOrderNotificationForm').find(className).show().find('textarea').prop('disabled', false);
        })
    },

    initEmailSendConfirmation: function() {
        $('.jsSendButton').click(function(){
            $('.modal-body').empty();
            var form = $('.jsOrderNotificationForm');
            var ajaxUrl = form.attr('action').replace('?', 'Preview?');
            $.ajax({
                url: ajaxUrl,
                dataType: 'json',
                data: form.serialize(),
                type: 'POST',
                success: function(email){
                    $('.modal-body').append(email.body);
                }
            });
        });
    }
}

var OrderAdminMisc = {
    init: function() {
        $('.jsCheckboxOItemQty').hide();

        $('.jsCheckboxOItem').change(function() {
            var oitemQty = $(this).parent().find('.jsCheckboxOItemQty');
            if (this.checked) {
                var row = $(this).closest('tr');
                var maxSplitQuantity = row.find('.jsOItemQuantity').html()
                    - row.find('.jsOItemPicked').html() - row.find('.jsOItemRefunded').html();
                if (maxSplitQuantity == 1) {
                    oitemQty.val(1);
                }
                oitemQty.show();
            }else{
                oitemQty.hide();
                oitemQty.val(0)
            }
        });
    }
}

//Spinner on Scan
var optsSpin = {
    lines: 13, // The number of lines to draw
    length: 8, // The length of each line
    width: 4, // The line thickness
    radius: 13, // The radius of the inner circle
    corners: 1, // Corner roundness (0..1)
    rotate: 0, // The rotation offset
    direction: 1, // 1: clockwise, -1: counterclockwise
    color: '#000', // #rgb or #rrggbb or array of colors
    speed: 1, // Rounds per second
    trail: 100, // Afterglow percentage
    shadow: false, // Whether to render a shadow
    hwaccel: false, // Whether to use hardware acceleration
    className: 'spinner', // The CSS class to assign to the spinner
    zIndex: 2e9, // The z-index (defaults to 2000000000)
    top: '50%', // Top position relative to parent*/
    left: '35%' // Left position relative to parent*/
};
var spinnerJsObj = new Spinner(optsSpin);
var targetSpin = document.getElementById('spinnerDiv');
//End Spinner on Scan

var PicklistitemScan = {
    init: function() {
        PicklistitemScan.initScanForm();
        PicklistitemScan.initSwitchCode();

        $('.jsFormItemScan input.jsItemBarcode').focus();
    },

    initScanForm: function() {
        $('.jsFormItemScan').submit(function(){
            var $form = $(this);

            spinnerJsObj.spin();
            targetSpin.appendChild(spinnerJsObj.el);
            $("#jsSubmitBarcode").prop('disabled', true);

            $.ajax({
                url: $form.attr('action'),
                dataType: "json",
                data: $form.serialize(),
                type: "POST",
                beforeSend: function() {
                    $form.find('.jsItemBarcode').val('');
                    $form.find('.jsItemSku').val('');
                }
            })

            .done(function(data) {

                var isAllOrderComplete = false;
                var isAllItemComplete = false;

                if (data.itemIncrement) {
                    var domItemScannedNumber = $('.jsItemScannedNumber:first');
                    domItemScannedNumber.text(parseInt(data.itemIncrement) + parseInt(domItemScannedNumber.text()));

                    isAllItemComplete = parseInt(domItemScannedNumber.text()) == parseInt($('.jsItemTotalNumber:first').text());
                }

                if (data.orderIncrement) {
                    var domOrderScannedNumber = $('.jsOrderScannedNumber:first');
                    domOrderScannedNumber.text(parseInt(data.orderIncrement) + parseInt(domOrderScannedNumber.text()));

                    isAllOrderComplete = parseInt(domOrderScannedNumber.text()) == parseInt($('.jsOrderTotalNumber:first').text());
                }

                if (data.itemLog) {
                    $form.find('ul.jsScanLog').prepend(data.itemLog);
                    $form.find('ul.jsScanLog li:first').fadeIn();
                }

                if (data.orderInfo) {
                    var domOrderInfo = $('.jsScanItemOrder:first');
                    domOrderInfo.html(data.orderInfo);
                }

                if (data.pigeonHole) {
                    var domPigeonHoleInfo = $('.jsPigeonHoleAllocation:first');
                    domPigeonHoleInfo.html(data.pigeonHole);
                }

                if (isAllItemComplete && isAllOrderComplete) {
                    $("#jsSubmitBarcode").prop('disabled', true);
                    spinnerJsObj.spin();
                    setTimeout(function() {
                        $('#picklistAutoComplete').modal('show');
                        setTimeout(function() {
                            $('#picklistAutoComplete input:first').focus();
                        }, 700);
                    }, 1800);
                    spinnerJsObj.stop();
                    $("#jsSubmitBarcode").prop('disabled', false);
                }

            })

            .fail(function(data) {
                alert('Error occurred, please try again later!')
            })

            .always(function() {
                spinnerJsObj.stop();
                $("#jsSubmitBarcode").prop('disabled', false);
            })

            return false;
        })
    },

    initSwitchCode: function() {
        $('.jsBtnToSku').click(function(){
            $('.jsDivBarcode').hide();
            $('.jsItemBarcode').val('');
            $('.jsItemSku').val('');
            $('.jsDivSku').show();
        });

        $('.jsBtnToBarcode').click(function(){
            $('.jsDivSku').hide();
            $('.jsItemSku').val('');
            $('.jsItemBarcode').val('');
            $('.jsDivBarcode').show();
        });
    }
}

var OrderAdminPacking = {
    init: function() {
        $('#orderId').focus();

        $('#orderId').keypress(function(event) { if (event.which == 13) {
            OrderAdminPacking.orderIdNext();
            return OrderAdminPacking.ajaxOrderCall();
        }});
        $('#orderId').blur(function() {
            OrderAdminPacking.orderIdNext();
            return OrderAdminPacking.ajaxOrderCall();
        });

        $('#code').keypress(function(event) { if (event.which == 13) { return OrderAdminPacking.codeNext(); }});
        $('#code').blur(function() { return OrderAdminPacking.codeNext(); });

        $('#userId').keypress(function(event) { if (event.which == 13) { return OrderAdminPacking.userIdNext(); }});
        $('#userId').blur(function() { return OrderAdminPacking.userIdNext(); });

        $('#weight').keypress(function(event) { if (event.which == 13) { return OrderAdminPacking.weightNext(); }});
        $('#weight').blur(function() { return OrderAdminPacking.weightNext(); });

        $('#tracking_code').keypress(function(event) { if (event.which == 13) { return OrderAdminPacking.trackingNext(); }});
        $('#tracking_code').blur(function() { return OrderAdminPacking.trackingNext(); });
    },

    ajaxOrderCall: function() {
        $.ajax({
            url: '/packing/adjust-packing-screen/'+$('#orderId').val(),
            dataType: 'json'
        })
        .done(function(data) {
            if (data.success) {
                OrderAdminPacking.hideOrderComment();
                OrderAdminPacking.setUseApi(data.useApi);
                OrderAdminPacking.setEnterWeight(data.enterWeight);
                OrderAdminPacking.setEnterTracking(data.enterTracking);
            }else{
                OrderAdminPacking.displayOrderComment(data.message);
            }
        })
        .fail(function(data) {
            if ($('#orderId').val() == '') {
                OrderAdminPacking.displayOrderComment('Please choose an order.');
                OrderAdminPacking.orderIdStay();
            }else{
                var message = 'Error occurred';
                if (data.message) {
                    message += ': '.data.message;
                }else{
                    message += ', please check order increment id!';
                }
                OrderAdminPacking.displayOrderComment(message);
            }
        })
        .always(function(data) {
            $('#packing').attr('action', '/packing/complete/'+$('#orderId').val());
        });

        return false;
    },
    displayOrderComment: function(comment) {
        $('#orderComment').html(comment);
        $('#orderComment').addClass('alert alert-error');
        $('#orderComment').show();
    },
    hideOrderComment: function() {
        $('#orderComment').hide();
        $('#orderComment').removeClass('alert alert-error');
        $('#orderComment').html('');
    },
    isApiMethodChangeableToANonApiMethod: function() {
        return false;
    },
    setUseApi: function(useApi) {
        if (useApi) {
            if (OrderAdminPacking.isApiMethodChangeableToANonApiMethod()) {
                $('#noApi-group').find('input:hidden').each(function () {
                    $('<input type="checkbox" />').attr({id: this.id, class: this.class}).insertBefore(this);
                }).remove();
                $('#noApi-group').css('display', 'inline-block');
            }else{
                $('#noApi').attr('value', 0);
                $('#completeField').attr('href', '#completePacking');
            }
        }else{
            $('#noApi-group').hide();
            $('#noApi-group').find('input:checkbox').each(function () {
                $('<input type="hidden" />').attr({id: this.id, class: this.class}).insertBefore(this);
            }).remove();
            $('#noApi').attr('value', 'On');
            $('#completeField').removeAttr('href');
        }
        $('#noApi').removeAttr('checked');
    },
    setCodeLabel: function(codeLabel) {
        if (codeLabel) {
            $('#group-code label').html(codeLabel+':');
        }
    },
    setEnterWeight: function(enterWeight) {
        if (enterWeight) {
            $('#group-weight').show();
            $('#enterWeight').val('On');
        }else{
            $('#group-weight').hide();
            $('#enterWeight').val(0);
        }
    },
    setEnterTracking: function(enterTracking) {
        if (enterTracking) {
            $('#group-tracking').show();
            $('#enterTracking').val('On');
        }else{
            $('#group-tracking').hide();
            $('#enterTracking').val(0);
        }
    },
    orderIdStay: function() {
        $('#orderId').val('');
        $('#orderId').focus();
        return false;
    },
    orderIdNext: function() {
        $('#code').val('');
        $('#code').focus();
        return false;
    },
    codeNext: function() {
        $('#userId').val('');
        $('#userId').focus();
        return false;
    },
    userIdNext: function() {
        $('#weight').val('');
        if ($('#enterWeight').val() == 'On') {
            $('#weight').focus();
        }else{
            OrderAdminPacking.weightNext();
        }
        return false;
    },
    weightNext: function() {
        $('#tracking_code').val('');
        if ($('#enterTracking').val() == 'On') {
            $('#tracking_code').focus();
        }else{
            OrderAdminPacking.trackingNext();
        }
        return false;
    },
    trackingNext: function() {
        $('#completeField').focus();
        return false;
    }
}


var PackingOrder = {
    init: function() {
        PackingOrder.initForm();
        PackingOrder.initInlineEdit();
    },

    initForm: function() {
        $('#trackingCodeField').focus();

        $('#trackingCodeField').blur(function(){ $('form.jsPackingOrderForm').submit(); });

        $('#completePacking .jsChkAdditionalNote').click(function(){
            $('#completePacking .jsChkAdditionalNoteSection').toggle();
        });

        $('form.jsPackingOrderForm').submit(function(){
            $.ajax({
                url: $(this).attr('action'),
                dataType: "json",
                data: $(this).serialize(),
                type: $(this).attr('method')
            })

            .done(function( data ) {
                if (data.success) {
                    $('.jsPackingOrderForm .jsInputTrackingCode').hide();
                    $('.jsPackingOrderForm .jsTextTrackingCode').text(data.trackingCode).show();
                }
            })

            .fail(function( data ) {
                alert('Error occurred, please try again later!')
            })

            .always(function() {
                $('#completeField').focus();
            });

            return false;
        });
    },

    initInlineEdit: function() {
        $('.jsPackingOrderForm .jsTextTrackingCode').click(function() {
            $(this).hide().siblings('.jsInputTrackingCode:first').show().find('input:first').focus();
        })
    }
}

$(document).ready(function() {
    AdminListControl.init();
})

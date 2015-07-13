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
        $('#order').focus();

        $('#order').keypress(function (event) {
            if (event.which == 13) {
                $('#user').focus();
                return false;
            }
        });

        $('#order').blur(function() {
            $.ajax({
                url: '/packing/adjust-packing-screen/'+$(this).val(),
                dataType: 'json'
            })

                .done(function (data) {
                    if (data.success) {
                        if (data.code) {
                            $('#group-code label').html(data.code + ':');
                        }

                        if (data.weight) {
                            $('#group-weight').show();
                        } else {
                            $('#group-weight').hide();
                        }
                    }
                })

                .fail(function (data) {
                    alert('Error occurred, please try again later!')
                })

                .always(function () {
                    $('#user').focus();
                });

            return false;
        });

        $('#user').keypress(function (event) {
            if (event.which == 13) {
                $('#code').focus();
                return false;
            }
        });

        $('#code').keypress(function (event) {
            if ($('#group-weight').is(':hidden')) {
                $('#packing').submit();
            }else{
                $('#weigth').focus();
            }
            return false;
        });

        $('#code').blur(function () {
            if ($('#group-weight').is(':hidden')) {
                $('#packing').submit();
            }else{
                $('#weigth').focus();
            }
            return false;
        });

        $('#weight').keypress(function (event) {
            if (event.which == 13) {
                $('#packing').submit();
                return false;
            }
        });

        $('#weight').blur(function () {
            $('#packing').submit();
            return false;
        });
    }
}


var PackingOrder = {
    init: function() {
        PackingOrder.initForm();
        PackingOrder.initInlineEdit();
    },

    initForm: function() {
        document.getElementById('trackingCodeField').focus();
        $('#trackingCodeField').focus();

        $('#trackingCodeField').blur(function(){
            $('form.jsPackingOrderForm').submit();
        });

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
        $('.jsPackingOrderForm .jsTextTrackingCode').click(function(){
            $(this).hide().siblings('.jsInputTrackingCode:first').show().find('input:first').focus();
        })
    }
}

$(document).ready(function() {
    AdminListControl.init();
})

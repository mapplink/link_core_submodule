var MenuControl = {

    timeoutMillisec: 400,
    timeoutHL: null,

    init: function() {
        MenuControl.dropdownButtonInit();
        MenuControl.dropdownSubmenuInit();
    },

    dropdownButtonInit: function() {
        $('.jsTopMenu .jsMenuDropdown').hover(
            function(){
                clearTimeout(MenuControl.timeoutHL);
                $('.jsTopMenu li').removeClass('open');
                $(this).parents('li').addClass('open');
            },
            function(){
                clearTimeout(MenuControl.timeoutHL);
                MenuControl.timeoutHL = setTimeout(function() {
                    $('.jsTopMenu li').removeClass('open');
                }, MenuControl.timeoutMillisec);
            }
        );
    },

    dropdownSubmenuInit: function() {
        $('.jsTopMenu .jsDropdownSubmenu').hover(
            function(){
                clearTimeout(MenuControl.timeoutHL);
            },
            function(){
                clearTimeout(MenuControl.timeoutHL);
                MenuControl.timeoutHL = setTimeout(function() {
                    $('.jsTopMenu li').removeClass('open');
                }, MenuControl.timeoutMillisec);
            }
        );
    }
}

var PasswordRetrieval = {

    sendLock : false,

    init: function() {
        PasswordRetrieval.clickToShowForm();
        PasswordRetrieval.clickToSendLink();
    },

    clickToShowForm: function() {
        $('#jsBtnShowPasswordRetrievalForm').click(function() {
            $('.jsPasswordRetrievalForm .jsFieldUsername').eq(0).val($('.jsLoginForm .jsFieldUsername').eq(0).val());
            $('.jsLoginForm').hide();
            $('.jsPasswordRetrievalForm').show();

            return false;
        });
    },

    clickToSendLink: function() {
        $('.jsPasswordRetrievalForm .jsSubmit').click(function(){
            if (PasswordRetrieval.sendLock) {
                return false;
            }

            $form = $(this).parents('.jsPasswordRetrievalForm').find('form').eq(0);
            $form.find('.jsErrorMessage').hide();

            $.ajax({
                url: $form.attr('action'),
                dataType: "json",
                data: $form.serialize()
            })

                .done(function( data ) {

                    if (data.success) {
                        $form.find('.jsSuccessMessage span').html(data.message);
                        $form.find('.jsSuccessMessage').show();
                    } else {
                        $form.find('.jsErrorMessage span').html(data.message);
                        $form.find('.jsErrorMessage').show();
                    }
                })

                .fail(function( data ) {
                    alert('Error occurred, please try again later!')
                })

                .always(function() {
                    PasswordRetrieval.sendLock = false;
                })

            ;


            console.log('send link');
            return false;
        });
    }
}

$(document).ready(function() {
    MenuControl.init();
    PasswordRetrieval.init();

    $('.jsDatetimePicker').datetimepicker({
        format:'d/m/Y H:i:s'
    });
})
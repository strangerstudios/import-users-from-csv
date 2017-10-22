(function($){
    "use strict";

    var $import_timer;
    var $pause_import;

    function ai_importPartial( $status, $title, $cycles, $pause_import, $count )  {

        $.ajax({
            url: ajaxurl,
            type:'GET',
            timeout: (parseInt( ia_settings.timeout ) * 1000),
            dataType: 'json',
            data: {
                action: 'import_users_from_csv',
                'filename' : ia_settings.filename,
                'password_nag': ia_settings.password_nag,
                'users_update': ia_settings.users_update,
                'new_user_notification': ia_settings.new_user_notification,
                'password_hashing_disabled' : ia_settings.password_hashing_disabled,
            },
            error: function( $response ){
                window.console.log( 'Import error: ', $response );
                window.alert( 'Error with import. Try refreshing: ' );
            },
            success: function( $response ){

                if ( $response.success === true ) {

                    if ( typeof $response.data.status !== 'undefined' && $response.data.status === true ) {

                        if ( typeof $response.data.message !== 'undefined' && null !== $response.data.message ) {

                            $status.html($status.html() + $response.data.message);
                            document.title = $cycles[(parseInt($count) % 4 )] + ' ' + $title;

                            if (false === $pause_import) {

                                $import_timer = setTimeout(function () {
                                    ai_importPartial($status, $title, $cycles, $pause_import, $count);
                                }, 2000);
                            }
                        } else if ( typeof $response.data.message !== 'undefined' ) {
                            $status.html($status.html() + '\nDone!');
                            document.title = '! ' + $title;
                        }
                    }

                    // Scroll the text area to the bottom unless the mouse is over it
                    if ($('#importstatus:hover').length <= 0) {
                        $status.scrollTop( $status[0].scrollHeight - $status.height() );
                    }

                } else {

                    if ( typeof $response.data.message !== 'undefined' && ( $response.data.status === false || $response.data.status === -1 ) ) {

                        $status.html($status.html() + $response.data.message );

                        document.title = $title;
                        window.alert('Error with import. Try refreshing.');
                    }
                }
            }
        });
    }

    $(document).ready(function() {

        //Get status
        var $status = $('#importstatus');

        // Init variables
        var $row = 1;
        var $count = 0;
        var $title = document.title;
        var $cycles = ['|','/','-','\\'];
        var $pausebutton = $('#pauseimport');
        var $resumebutton = $('#resumeimport');

        $pause_import = false;

        //enable pause button
        $pausebutton.unbind('click').on('click', function() {

            clearTimeout( $import_timer);

            $pause_import = true;

            $pausebutton.hide();
            $resumebutton.show();

            $status.html($status.html() + 'Pausing. You may see one more partial import update under here as we clean up.\n');
        });

        //enable resume button
        $resumebutton.unbind('click').on('click', function() {

            $pause_import = false;
            clearTimeout( $import_timer);
            $resumebutton.hide();
            $pausebutton.show();

            $status.html($status.html() + 'Resuming...\n');

            ai_importPartial( $status, $title, $cycles, $pause_import, $count );
        });

        //start importing and update status
        if($status.length > 0)
        {
            $status.html($status.html() + '\n' + 'JavaScript Loaded.\n');
            $import_timer = setTimeout(function() { ai_importPartial( $status, $title, $cycles, $pause_import, $count );}, 2000);
        }
    });

})(jQuery);




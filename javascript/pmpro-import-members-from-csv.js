(function($){
    "use strict";

    $(document).ready(function() {

        var pmp_import = {
            init: function() {
                this.status = $('#importstatus');
                this.pausebutton = $('#pauseimport');
                this.resumebutton = $('#resumeimport');

                this.title = document.title;
                this.cycles = ['|','/','-','\\'];
                this.count = 0;
                this.row = 1;

                this.importTimer = null;
                this.status_paused = false;

                var self = this;
                // Load button handling
                self.bind();

                if(self.status.length > 0) {

                    self.status.html( self.status.html() + '\n' + pmp_im_settings.lang.loaded + '\n');
                    self.importTimer = setTimeout(
                        function() {
                            self.import();
                        },
                        2000
                    );
                }
            },
            bind: function() {

                var self = this;

                // Activate the pause button
                this.pausebutton.unbind('click').on('click', function() {

                    clearTimeout( self.importTimer);

                    self.status_paused = true;

                    self.pausebutton.hide();
                    self.resumebutton.show();

                    self.status.html(self.status.html() + pmp_im_settings.lang.pausing + '\n' );
                });

                self.resumebutton.unbind('click').on('click', function() {

                    self.status_paused = false;
                    clearTimeout( self.importTimer );
                    self.resumebutton.hide();
                    self.pausebutton.show();

                    self.status.html( self.status.html() + pmp_im_settings.lang.resuming + '\n');

                    self.import();
                });

            },
            import: function() {

                var self = this;

                $.ajax({
                    url: ajaxurl,
                    type:'GET',
                    timeout: (parseInt( pmp_im_settings.timeout ) * 1000),
                    dataType: 'json',
                    data: {
                        action: 'import_members_from_csv',
                        'filename' : pmp_im_settings.filename,
                        'password_nag': pmp_im_settings.password_nag,
                        'users_update': pmp_im_settings.users_update,
                        'deactivate_old_memberships': pmp_im_settings.deactivate_old_memberships,
                        'new_user_notification': pmp_im_settings.new_user_notification,
                        'password_hashing_disabled' : pmp_im_settings.password_hashing_disabled,
                        'pmp-im-import-members-nonce': $('#pmp-im-import-members-nonce').val()
                    },
                    error: function( $response ){
                        window.console.log( 'Import error: ', $response );
                        window.alert( pmp_im_settings.lang.alert_msg );
                    },
                    success: function( $response ){

                        if ( $response.success === true ) {

                            if ( typeof $response.data.status !== 'undefined' && $response.data.status === true ) {

                                if ( typeof $response.data.message !== 'undefined' && null !== $response.data.message ) {

                                    self.status.html(self.status.html() + $response.data.message);
                                    document.title = self.cycles[(parseInt(self.count) % 4 )] + ' ' + self.title;

                                    if (false === self.status_paused ) {

                                        self.importTimer = setTimeout(function () {
                                            self.import();
                                        }, 2000);
                                    }
                                } else if ( typeof $response.data.message !== 'undefined' ) {
                                    self.status.html( self.status.html() + '\n' + pmp_im_settings.lang.done );
                                    document.title = '! ' + self.title;
                                }
                            }

                            // Scroll the text area to the bottom unless the mouse is over it
                            if ($('#importstatus:hover').length <= 0) {
                                self.status.scrollTop( self.status[0].scrollHeight - self.status.height() );
                            }

                        } else {

                            if ( typeof $response.data.message !== 'undefined' && ( $response.data.status === false || $response.data.status === -1 ) ) {

                                self.status.html(self.status.html() + $response.data.message );

                                document.title = self.title;
                                window.alert( pmp_im_settings.lang.error );
                            }
                        }
                    }
                });
            }
        };

        pmp_import.init();

    });

})(jQuery);




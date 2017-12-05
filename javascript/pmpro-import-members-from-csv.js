(function($){
    "use strict";

    $(document).ready(function() {

        var pmp_import = {
            init: function() {
                this.status = $('#importstatus');
                this.pausebutton = $('#pauseimport');
                this.resumebutton = $('#resumeimport');
                this.complete_btn = $("#completedImport");
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
                    self.set_busy();
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

                self.complete_btn.unbind('click').on('click', function( event ) {
                    event.preventDefault();
                    location.href = pmp_im_settings.admin_page;
                });
            },
            import: function() {

                var self = this;

                $.ajax({
                    url: ajaxurl,
                    type:'POST',
                    timeout: (parseInt( pmp_im_settings.timeout ) * 1000),
                    dataType: 'json',
                    data: {
                        action: 'import_members_from_csv',
                        'background_import': parseInt( pmp_im_settings.background_import ),
                        'filename' : pmp_im_settings.filename,
                        'password_nag': parseInt( pmp_im_settings.password_nag ),
                        'update_users': parseInt( pmp_im_settings.update_users ),
                        'deactivate_old_memberships': parseInt( pmp_im_settings.deactivate_old_memberships ),
                        'new_user_notification': parseInt( pmp_im_settings.new_user_notification ),
                        'password_hashing_disabled' : parseInt( pmp_im_settings.password_hashing_disabled ),
                        'per_partial': parseInt( pmp_im_settings.per_partial ),
                        'import': pmp_im_settings.import,
                        'pmp-im-import-members-nonce': $('#pmp-im-import-members-nonce').val()
                    },
                    error: function( $response ){
                        window.console.log( 'Import error: ', $response );
                        window.alert( pmp_im_settings.lang.alert_msg );

                        window.location.href = pmp_im_settings.admin_page;
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
                                    self.clear_busy();
                                    self.complete_btn.show();
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
            },
            set_busy: function() {
                $('#importstatus').css( 'cursor', 'wait' );
            },
            clear_busy: function(){
                $('#importstatus').css( 'cursor', 'pointer' );
            }
        };

        pmp_import.init();

    });

})(jQuery);




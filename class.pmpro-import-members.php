<?php
/**
* Plugin Name: Paid Memberships Pro - Import Members from CSV
* Plugin URI: http://wordpress.org/plugins/pmpro-import-members-from-csv/
* Description: Import Users and their metadata from a csv file.
* Version: 2.2
* Requires PHP: 5.4
* Author: <a href="https://eighty20results.com/thomas-sjolshagen/">Thomas Sjolshagen <thomas@eighty20results.com></a>
* License: GPL2
* Text Domain: pmpro-import-members-from-csv
* Domain Path: languages/
*/
/**
 * Copyright 2017 - Thomas Sjolshagen (https://eighty20results.com/thomas-sjolshagen)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @credit http://wordpress.org/plugins/import-users-from-csv/ - Ulich Sossou -  https://github.com/sorich87
 * @credit https://github.com/strangerstudios/pmpro-import-users-from-csv - Jason Coleman - https://github.com/ideadude
*/
namespace PMPRO\Addons;

if ( ! defined( 'PMP_IM_CSV_DELIMITER' ) ) {
    define ( 'PMP_IM_CSV_DELIMITER', ',' );
}
if ( ! defined( 'PMP_IM_CSV_ESCAPE') ) {
    define ( 'PMP_IM_CSV_ESCAPE', '\\' );
}
if ( ! defined( 'PMP_IM_CSV_ENCLOSURE') ) {
    define ( 'PMP_IM_CSV_ENCLOSURE', '"' );
}

class Import_Members_From_CSV {
 
    /**
     * Instance of this class
     *
     * @var null|Import_Members_From_CSV $instance
     */
    private static $instance = null;
    
    /**
     * Path to error log file
     *
     * @var string $logfile_path
     */
	private $logfile_path = '';
	
	/**
     * URI for error log
     *
     * @var string $logfile_url
     */
	private $logfile_url  = '';
	
	/**
     * List of Membership import fields
     *
     * @var array|null $pmpro_fields
     */
    private $pmpro_fields = null;
    
    /**
     * Name/path of CSV import file
     *
     * @var null|string $filename
     */
    private $filename = null;
    
    /**
     * Update existing user data?
     *
     * @var bool $update_users
     */
    private $update_users = false;
    
    /**
     * Set the password nag message when user logs in for the first time?
     *
     * @var bool $password_nag
     */
    private $password_nag = false;
    
    /**
     * @var bool $password_hashing_disabled - Password is supplied in import file as an encrypted string
     */
    private $password_hashing_disabled = false;
    
    /**
     * Should we deactivate old membership levels for the user that
     * match the record being imported?
     *
     * @var bool $deactivate_old_memberships
     */
    private $deactivate_old_memberships = false;
    
    /**
     * Do we send the imported user the "new WordPress Account" notice?
     *
     * @var bool $new_user_notification
     */
    private $new_user_notification = false;
    
    /**
     * Do we send a welcome message to the member if they're imported as an active member to the site
     *
     * @var bool $new_member_notification
     */
    private $new_member_notification = false;
    
    /**
     * The ID of the multisite to import the user data to/for
     *
     * @var null|int $site_id
     */
    private $site_id = null;
    
    /**
     * Import the CSV file as a "background" process (i.e. with a JavaScript loop)
     *
     * @var bool $background_import
     */
    private $background_import = false;
    
    /**
     * Number of records to import per transaction
     *
     * @var int $per_partial
     */
    private $per_partial = 30;
    
    /**
      * Import_Members_From_CSV constructor.
      */
	private function __construct() {
   	 
	    // Set the error log info
        $upload_dir = wp_upload_dir();
		$this->logfile_path = trailingslashit( $upload_dir['basedir'] ) . 'pmp_im_errors.log';
		$this->logfile_url  = trailingslashit( $upload_dir['baseurl'] ) . 'pmp_im_errors.log';
		
		// Configure fields for PMPro import
        $this->pmpro_fields = array(
            "membership_id" => null,
            "membership_code_id" => null,
            "membership_discount_code" => null,
            "membership_initial_payment" => null,
            "membership_billing_amount" => null,
            "membership_cycle_number" => null,
            "membership_cycle_period" => null,
            "membership_billing_limit" => null,
            "membership_trial_amount" => null,
            "membership_trial_limit" => null,
            "membership_status" => null,
            "membership_startdate" => null,
            "membership_enddate" => null,
            "membership_subscription_transaction_id" => null,
            "membership_payment_transaction_id" => null,
            "membership_gateway" => null,
            "membership_affiliate_id" => null,
            "membership_timestamp" => null,
        );
	}
 
	/**
	 * Initialization
	 *
	 * @since 2.0
     *
	 **/
	public function load_plugins() {
		
        add_action( 'init', array( self::get_instance(), 'load_i18n' ), 5 );
		add_action( 'init', array( self::get_instance(), 'process_csv' ) );

        add_action( 'admin_menu', array( self::get_instance(), 'add_admin_pages' ) );
        add_action( 'admin_notices', array( self::get_instance(), 'display_admin_message' ) );
        
		add_action( 'admin_enqueue_scripts', array( self::get_instance(), 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_import_members_from_csv', array( self::get_instance(), 'wp_ajax_import_members_from_csv' ) );
		
		// PMPro specific import functionality
		add_action( 'pmp_im_pre_user_import', array( self::get_instance(), 'pre_member_import' ) , 10, 2);
		add_filter( 'pmp_im_import_usermeta', array( self::get_instance(), 'import_usermeta' ), 10, 2);
		add_action( 'pmp_im_post_user_import', array( self::get_instance(), 'import_membership_info' ), 10, 2 );
		
		add_action( 'admin_bar_menu', array( self::get_instance(), 'load_to_pmpro_menu' ), 1001 );
		
		// Set URIs in plugin listing to PMPro support
		add_filter( 'plugin_row_meta', array( self::get_instance(), 'plugin_row_meta' ), 10, 2);
	}
	
	/**
     * Return or instantiate class for use
     *
     * @return Import_Members_From_CSV
     */
    public static function get_instance() {
        
        if ( is_null( self::$instance ) ) {
            self::$instance = new self;
        }
        
        return self::$instance;
    }
	
	/**
     * Load translation (glotPress friendly)
     */
	public function load_i18n() {
	    
        load_plugin_textdomain(
            'pmpro-import-members-from-csv',
            false,
            basename( dirname( __FILE__ ) ) . '/languages'
        );
	}

	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public function users_page() {
	 
		if ( ! current_user_can( 'create_users' ) ) {
		    wp_die( __( 'You do not have sufficient permissions to access this page.' , 'pmpro-import-members-from-csv') );
		} ?>
	<div class="wrap">
	    <?php printf( '<div id="e20r-status" %s></div>', ( !isset( $_REQUEST['import'] ) ? 'style="display: none;"' : 'style="display: inline-block;"' ) ); ?>
		<h2><?php _e( 'Import PMPro members from a CSV file' , 'pmpro-import-members-from-csv'); ?></h2>
		<?php

		if ( ! file_exists( $this->logfile_path ) ) {
		 
			if ( ! @fopen( $this->logfile_path, 'x' ) ) {
			    
                printf( '<div class="updated"><p><strong>%s</strong></p></div>',
                sprintf(
                        __( 'Note: Please make the %s directory writable to allow you to see/save the error log.' , 'pmpro-import-members-from-csv'),
                         $this->logfile_path
                         )
                );
			}
		}

		if ( isset( $_REQUEST['import'] ) ) {
			$error_log_msg = '';
			
			if ( file_exists( $this->logfile_path ) ) {
			    $error_log_msg = sprintf(
			            __( ', please %1$scheck the error log%2$s' , 'pmpro-import-members-from-csv'),
			            sprintf('<a href="%s">', esc_url_raw( $this->logfile_url ) ),
			            '</a>'
			            );
			}
			
			switch ( $_REQUEST['import'] ) {
				case 'file':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', __( 'Error during file upload.' , 'pmpro-import-members-from-csv') );
					break;
				case 'data':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', __( 'Cannot extract data from uploaded file or no file was uploaded.' , 'pmpro-import-members-from-csv') );
					break;
				case 'fail':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'No members were successfully imported%s.' , 'pmpro-import-members-from-csv'), $error_log_msg ) );
					break;
				case 'errors':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'Some members were successfully imported but some were not%s.' , 'pmpro-import-members-from-csv'), $error_log_msg ) );
					break;
				case 'success':
					printf( '<div class="updated"><p><strong>%s</strong></p></div>', __( 'Member import was successful.' , 'pmpro-import-members-from-csv') );
					break;
				default:
			}
			
			if( isset($_REQUEST['import']) && $_REQUEST['import'] == 'resume' && !empty($_REQUEST['filename'])) {
			 
				$this->filename = sanitize_file_name($_REQUEST['filename']);
				
				//Resetting position option?
				if(!empty($_REQUEST['reset'])) {
				    $file = basename( $this->filename );
				    delete_option("pmpcsv_{$this->filename}" );
				}
			?>
			<h3><?php _e( 'Importing the file using AJAX (in the background)', 'pmpro-import-members-from-csv' ); ?></h3>
			<p><strong><?php _e('IMPORTANT:', 'pmpro-import-members-from-csv' ); ?></strong> <?php printf(
			        __('Your import is not finished. %1$sClosing this page will stop the import operation%2$s. If the import stops or you have to close your browser, you can navigate to %3$sthis URL%4$s to resume the import operation later.', 'pmpro-import-members-from-csv'),
			'<strong>',
			'</strong>',
			sprintf('<a href="%s">', admin_url( $_SERVER['QUERY_STRING'] ) ),
			'</a>'
			); ?>
			</p>
			
			<p>
				<a id="pauseimport" href="#"><?php _e("Click here to pause.", 'pmpro-import-members-from-csv' ); ?></a>
				<a id="resumeimport" href="#" style="display:none;"><?php _e("Paused. Click here to resume.", "pmpro-import-members-from-csv" ); ?></a>
			</p>
			
			<textarea id="importstatus" rows="10" cols="60"><?php _e( 'Loading...', 'pmpro-import-members-from-csv' ); ?></textarea>
			<p class="complete_btn">
			    <input type="button" class="button-primary" id="completedImport" value="<?php _e("Finished", "pmpro-import-members-from-csv" ); ?>" style="display:none;"/>
            </p>
			<?php
			}
		}
		
		if(empty($_REQUEST['filename'])) { ?>
		<form method="post" action="" enctype="multipart/form-data">
			<?php wp_nonce_field( 'pmp-im-import-members', 'pmp-im-import-members-wpnonce' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="members_csv"><?php _e( 'CSV file to load' , 'pmpro-import-members-from-csv'); ?></label></th>
					<td>
						<input type="file" id="members_csv" name="members_csv" value="" class="all-options" accept=".csv, text/csv" about="<?php __('Select .CSV file to process', 'pmpro-import-members-from-csv' ); ?>"/><br />
						<span class="description"><?php echo sprintf( __( 'You may want to see <a href="%s">the example of the CSV file</a>.' , 'pmpro-import-members-from-csv'), plugin_dir_url(__FILE__).'examples/import.csv'); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Update user record' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Users update' , 'pmpro-import-members-from-csv' ); ?></span></legend>
						<label for="update_users">
							<input id="update_users" name="update_users" type="checkbox" value="1" checked="checked" />
							<?php _e( "Update, don't add a user when the username or email already exists", 'pmpro-import-members-from-csv' ) ;?>
						</label>
					</fieldset></td>
				</tr>
                <tr valign="top">
					<th scope="row"><?php _e( 'Deactivate existing membership' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Deactivate existing membership' , 'pmpro-import-members-from-csv' ); ?></span></legend>
						<label for="deactivate_">
							<input id="deactivate_old_memberships" name="deactivate_old_memberships" type="checkbox" value="1" checked="checked" />
							<?php _e( "Refresh the member status when importing someone who already have an 'active' membership level", "pmpro-import-members-from-csv" ) ;?>
						</label>
					</fieldset></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Send notification' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Send new user notification to the user and admin' , 'pmpro-import-members-from-csv'); ?></span></legend>
						<label for="new_user_notification">
							<input id="new_user_notification" name="new_user_notification" type="checkbox" value="1" />
							<?php _e('Send the new user notification to new users', 'pmpro-import-members-from-csv') ?>
						</label>
					</fieldset></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Display password nag' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Password nag' , 'pmpro-import-members-from-csv'); ?></span></legend>
						<label for="password_nag">
							<input id="password_nag" name="password_nag" type="checkbox" value="1" />
							<?php _e('Show the password nag when the new user(s) log in', 'pmpro-import-members-from-csv') ?>
						</label>
					</fieldset></td>
				</tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Password is already hashed' , 'pmpro-import-members-from-csv'); ?></th>
                    <td><fieldset>
                        <legend class="screen-reader-text"><span><?php _e( 'Password is hashed' , 'pmpro-import-members-from-csv' ); ?></span></legend>
                        <label for="password_hashing_disabled">
                            <input id="password_hashing_disabled" name="password_hashing_disabled" type="checkbox" value="1" />
                            <?php _e( "The passsword in the .csv file is already hashed and doesn't need to be encrypted by the import process.", 'pmpro-import-members-from-csv' ) ;?>
                        </label>
                    </fieldset></td>
                </tr>
                <tr valign="top">
					<th scope="row"><?php _e( 'Large .CSV file import' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Import a large .csv file. Example, your import file consists of more than 100 entries' , 'pmpro-import-members-from-csv' ); ?></span></legend>
						<label for="background_import">
							<input id="background_import" name="background_import" type="checkbox" value="1" checked="checked" />
							<?php _e( 'Use a background process to import all of the records.', 'pmpro-import-members-from-csv' ) ;?>
						</label>
					</fieldset></td>
				</tr>
				<?php
				if ( is_multisite() ):
				
				$site_list = get_sites();
				?>
                <tr valign="top">
					<th scope="row"><?php _e( 'Site to import to' , 'pmpro-import-members-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Select the multisite instance to import these members to.' , 'pmpro-import-members-from-csv' ); ?></span></legend>
							<select id="site_id" name="site_id">
							<?php foreach( $site_list as $site ) {
							    $subsite_id = $site->blog_id;
							    $subsite_name = $site->blogname;
                            ?>
                                <option value="<?php esc_attr_e( $subsite_id ); ?>"><?php esc_html_e( $subsite_name ); ?></option>
                            <?php
							} ?>
							</select>
					</fieldset></td>
				</tr>

                <?php
				endif;
				?>
				<?php do_action('pmp_im_import_page_setting_html' ) ?>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Import' , 'pmpro-import-members-from-csv'); ?>" />
			</p>
		</form>
		<?php }
	}

	/**
	 * Add to Memberships Menu in WP Back-end (left sidebar)
	 *
	 * @since 2.1
	 **/
	public function add_admin_pages() {
	 
		add_submenu_page(
		        'pmpro-membershiplevels',
		        __( 'Import Members' , 'pmpro-import-members-from-csv'),
		        __( 'Import Members' , 'pmpro-import-members-from-csv'),
		        'create_users',
		        'pmpro-import-members-from-csv',
		        array( self::get_instance(), 'users_page' )
        );
	}

	/**
     * Add Import Members to the PMPro Members drop-down menu
     * @since 2.1
     */
	public function load_to_pmpro_menu() {
	    
	    global $wp_admin_bar;
	    
	    if( current_user_can('create_users') ) {
		    $wp_admin_bar->add_menu( array(
            'id' => 'pmpro-import-members-from-csv',
            'parent' => 'paid-memberships-pro',
            'title' => __( 'Import Members', 'pmpro-import-members-from-csv' ),
            'href' => add_query_arg( 'page', 'pmpro-import-members-from-csv', get_admin_url(null, 'admin.php' ) ),
            )
        );
        }
	}
	
	/**
	 * Add admin JS
	 *
     * @param string $hook
     *
	 * @since 1.0
	 **/
	public function admin_enqueue_scripts($hook) {
	 
		if ( !isset($_REQUEST['page']) || $_REQUEST['page'] != 'pmpro-import-members-from-csv') {
			return;
		}
		
        $this->load_settings();
		
        $max_run_time = (
                apply_filters( 'pmp_im_import_time_per_record', 3 ) *
                apply_filters( 'pmp_im_import_records_per_scan', 30 )
            );
        
		wp_register_script( 'pmpro-import-members-from-csv', plugins_url( 'javascript/pmpro-import-members-from-csv.js',__FILE__ ), array('jquery' ), '2.1'  );
		
		wp_localize_script( 'pmpro-import-members-from-csv', 'pmp_im_settings',
		apply_filters( 'pmp_im_import_js_settings', array(
		            'timeout' => $max_run_time,
                    'background_import' => intval( $this->background_import ),
                    'filename' => $this->filename,
                    'update_users' => intval( $this->update_users ),
                    'deactivate_old_memberships' => intval( $this->deactivate_old_memberships ),
                    'new_user_notification' => intval( $this->new_user_notification ),
                    'password_hashing_disabled' => intval( $this->password_hashing_disabled ),
                    'password_nag' => intval( $this->password_nag ),
                    'per_partial' => intval( $this->per_partial ),
                    'site_id' => intval( $this->site_id ),
                    'admin_page' => admin_url(),
                    'import' => isset( $_REQUEST['import'] ) ? sanitize_text_field( $_REQUEST['import'] ) : null,
                    'lang' => array(
                        'pausing' => __( 'Pausing. You may see one more update here as we clean up.', 'pmpro-import-members-from-csv' ),
                        'resuming' => __( 'Resuming...', 'pmpro-import-members-from-csv' ),
                        'loaded' => __( 'JavaScript Loaded.', 'pmpro-import-members-from-csv' ),
                        'done' => __( 'Done!', 'pmpro-import-members-from-csv' ),
                        'alert_msg' => __( 'Error with import. Try refreshing: ', 'pmpro-import-members-from-csv' ),
                        'error' => __( 'Error with import. Try refreshing.', 'pmpro-import-members-from-csv' ),
                    ),
                )
            )
		);
		
        wp_enqueue_script( 'pmpro-import-members-from-csv' );
    }
	
    /**
     * Load/configure settings from $_REQUEST array (if available)
     */
    public function load_settings() {

        if ( true === $this->is_configured() ) {
            
            if ( WP_DEBUG ) {
                error_log("Environment is configured already: {$this->filename}");
            }
            return;
        }
        
        if (WP_DEBUG) {
            error_log("Received info: " . print_r( $_REQUEST, true ));
        }
        
        $this->filename = isset( $_FILES['members_csv']['tmp_name'] ) ? $_FILES['members_csv']['tmp_name'] : $this->filename;
        $this->filename = isset( $_REQUEST['filename'] ) ? sanitize_file_name( $_REQUEST['filename'] ) : $this->filename;
        
        if (WP_DEBUG) {
            error_log( "Setting file name to {$this->filename}");
        }

        $this->update_users = !empty( $_REQUEST['update_users'] ) ? ( 1 === intval( $_REQUEST['update_users'] ) ) : $this->update_users;
        
        if (WP_DEBUG) {
            error_log("Settings users update to: {$this->update_users}");
        }
        
        $this->background_import = !empty( $_REQUEST['background_import'] ) ? ( 1 === intval( $_REQUEST['background_import'] ) ) : $this->background_import;
        $this->deactivate_old_memberships = !empty( $_REQUEST['deactivate_old_memberships'] ) ? ( 1 === intval($_REQUEST['deactivate_old_memberships'] ) ) : $this->deactivate_old_memberships;
		$this->password_nag               = !empty( $_REQUEST['password_nag'] ) ? ( 1 === intval( $_REQUEST['password_nag'] ) ) : $this->password_nag;
        $this->password_hashing_disabled  = !empty( $_REQUEST['password_hashing_disabled'] ) ? ( 1 === intval(  $_REQUEST['password_hashing_disabled'] ) ): $this->password_hashing_disabled;
        $this->new_user_notification      = !empty( $_REQUEST['new_user_notification'] ) ? ( 1 === intval($_REQUEST['new_user_notification'] ) ) : $this->new_user_notification;
        $this->new_member_notification      = !empty( $_REQUEST['new_member_notification'] ) ? ( 1 === intval($_REQUEST['new_member_notification'] ) ) : $this->new_member_notification;
        $this->per_partial = !empty( $_REQUEST['per_partial'] ) ? intval( $_REQUEST['per_partial'] ) : $this->per_partial;
        $this->site_id = !empty( $_REQUEST['site_id'] ) ? intval( $_REQUEST['site_id'] ) : $this->site_id;
        
        $this->per_partial = apply_filters( 'pmp_im_import_records_per_scan', $this->per_partial );
    }

    /**
     * Is the class configured (Request variables read to variables) already?
     *
     * @return bool
     */
	private function is_configured() {
	    
	    return !empty( $this->filename );
	}

	/**
     * Add admin notice to WP Admin backedn
     */
	public function display_admin_message() {

	    $error_msgs = get_option( 'pmp_im_error_msg', array() );
	    
        if ( WP_DEBUG ) {
	        error_log("Error info: " . print_r( $error_msgs, true ) );
	    }

	    if ( !empty( $error_msgs ) && is_admin() ) {
	        
	        foreach( $error_msgs as $msg_info ) {
	    ?>
        <div class="notice notice-<?php esc_attr_e( $msg_info['type'] ); ?> is-dismissible">
	        <p><strong><?php esc_html_e( $msg_info['message'] ); ?></strong></p>
	        <button type="button" class="notice-dismiss">
		        <span class="screen-reader-text"><?php __( 'Dismiss this message.', 'pmpro-import-members-from-csv' ); ?></span>
	        </button>
        </div>
        <?php
            }
            
            // Clear error/warning/notice/success messages
            delete_option( 'pmp_im_error_msg' );
        }
	}
	
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function process_csv() {
	 
		if ( isset( $_REQUEST['pmp-im-import-members-wpnonce'] ) && ! ( defined('DOING_AJAX' ) && DOING_AJAX !== true ) ) {
			
		    if (WP_DEBUG) {
		        error_log("Processing AJAX request");
		    }
		    
		    check_admin_referer( 'pmp-im-import-members', 'pmp-im-import-members-wpnonce' );
					   
            // Setup settings variables
            $this->load_settings();

			if ( isset( $_FILES['members_csv']['tmp_name'] ) ) {
			 
				//use AJAX?
				if ( true === $this->background_import ) {

				    if (WP_DEBUG ) {
				        error_log("Background processing for import");
				    }
					//check for a imports directory in wp-content
					$upload_dir = wp_upload_dir();
					$import_dir = $upload_dir['basedir'] . "/imports/";
					
					//create the dir and subdir if needed
					if(!is_dir($import_dir)) {
						wp_mkdir_p($import_dir);
					}
					
					//figure out filename
					$this->filename = $_FILES['members_csv']['name'];
					$file_arr = explode( '.', $this->filename );
					$filetype = $file_arr[ (count( $file_arr ) - 1 ) ];
					
					$count = 0;
					
					if ( empty( $this->filename ) ) {
					    
					    
					    $this->log_errors(  array( new \WP_Error( 'pmp_import', __( 'No .CSV file specified for import!', 'pmpro-import-members-from-csv' ) ) ) );
					    $this->add_error_msg( __( 'CSV file not selected!', 'import-members-from-csv' ), 'error' );
					    
					    wp_redirect( add_query_arg( 'import', 'fail', wp_get_referer() ) );
					    exit();
                    }
					
					while ( file_exists("{$import_dir}{$this->filename}" ) ) {
					 
						if( !empty( $count ) ) {
							$this->filename = $this->str_lreplace("-{$count}.{$filetype}", "-" . strval($count + 1 ) . ".{$filetype}", $this->filename );
						} else {
							$this->filename = $this->str_lreplace(".{$filetype}", "-1.{$filetype}", $this->filename);
                        }
										
						$count++;
						
						//let's not expect more than 50 files with the same name
						if( $count > 50) {
						    $this->add_error_msg( sprintf( __( "Error uploading file! Too many files with the same name. Clean out the %s directory on your server.", "pmpro-import-members-from-csv" ), $import_dir ), 'error' );
						}
					}
					
					//save file
					if( false !== strpos($_FILES['members_csv']['tmp_name'], $upload_dir['basedir']) ) {
					 
						//was uploaded and saved to $_SESSION
						rename($_FILES['members_csv']['tmp_name'], "{$import_dir}{$this->filename}");
					} else {
						//it was just uploaded
						move_uploaded_file($_FILES['members_csv']['tmp_name'], "{$import_dir}{$this->filename}");
					}
					
					//redurect to the page to run AJAX
					$url = add_query_arg(
					        array(
                                'page' => 'pmpro-import-members-from-csv',
                                'import' => 'resume',
                                'filename' => $this->filename,
                                'background_import' => true,
                                'update_users' => $this->update_users,
                                'password_nag'=>$this->password_nag,
                                'password_hashing_disabled' => $this->password_hashing_disabled,
                                'new_user_notification'=>$this->new_user_notification,
                                'deactivate_old_memberships'=>$this->deactivate_old_memberships,
                                'partial' => true,
			                    'per_partial' => $this->per_partial,
			                    'site_id' => $this->site_id,
					        ),
					        admin_url('admin.php' )
                        );
					
					wp_redirect($url);
					exit;
					
				} else {
				 
					$results = $this->import_csv( $this->filename, array(
                        'filename' => $this->filename,
                        'background_import' => true,
                        'update_users' => $this->update_users,
                        'password_nag'=>$this->password_nag,
                        'password_hashing_disabled' => $this->password_hashing_disabled,
                        'new_user_notification'=>$this->new_user_notification,
                        'deactivate_old_memberships'=>$this->deactivate_old_memberships,
			            'partial' => false,
			            'per_partial' => apply_filters( 'pmp_im_import_records_per_scan', 30 ),
			            'site_id' => $this->site_id,
					) );

					// No users imported?
					if ( ! $results['user_ids'] ) {
						wp_redirect( add_query_arg( 'import', 'fail', wp_get_referer() ) );

					// Some users imported?
					} elseif ( $results['errors'] ) {
					    wp_redirect( add_query_arg( 'import', 'errors', wp_get_referer() ) );
					
					// All users imported? :D
					} else {
						wp_redirect( add_query_arg( 'import', 'success', wp_get_referer() ) );
					}

					exit;
				}
			}

			wp_redirect( add_query_arg( 'import', 'file', wp_get_referer() ) );
			exit;
		}
	}
	
	/**
	 * Log errors to a file
	 *
     * @param array $errors
     *
	 * @since 1.0
	 **/
	private function log_errors( $errors ) {
	 
		if ( empty( $errors ) ) {
		    return;
		}

		$log = @fopen( $this->logfile_path, 'a' );
		
		@fwrite( $log,
		sprintf(
		        __( "BEGIN %s\n" , 'pmpro-import-members-from-csv'),
		        date( 'Y-m-d H:i:s', current_time('timestamp' )
		        )
            )
        );

		/**
         * @param \WP_Error $error
         */
		foreach ( $errors as $key => $error ) {
			$line = $key + 1;
			$message = $error->get_error_message();
			@fwrite( $log, sprintf(
			        __( '[Line %1$s] %2$s' , 'pmpro-import-members-from-csv'), $line, $message ) . "\n" );
		}

		@fclose( $log );
	}

	/**
     * Add a error/warning/success/info message to /wp-admin/
     *
     * @param string $msg
     * @param string $type
     */
	private function add_error_msg( $msg, $type = 'info' ) {
     
	    $error_msg = get_option( 'pmp_im_error_msg', array() );
					   
        if ( !empty( $error_msg ) ) {
            $error_msg = $error_msg + array( 'type' => $type, 'message' => $msg );
        } else {
            $error_msg = array( array( 'type' => $type, 'message' => $msg ) );
        }
        
        update_option( 'pmp_im_error_msg',  $error_msg,'no' );
	}

	/**
      * Replace leftmost instance of string
      *
      * @param string $search
      * @param string $replace
      * @param string $subject
      *
      * @return string
      */
	public function str_lreplace($search, $replace, $subject) {
	    
        $pos = strrpos($subject, $search);

        if($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search) );
        }

        return $subject;
    }
	
	/**
	 * Import a csv file
	 *
     * @param array $args
     *
     * @return array
	 * @since 0.5
	 */
	public function import_csv( $filename, $args ) {
		
	    if ( is_multisite() ) {
	        $current_blog_id = get_current_blog_id();
	    }
	    
	    $errors = $user_ids = array();
		$headers = array();
		
		$defaults = array(
		        
            'filename' => null,
			'password_nag' => false,
			'background_import' => false,
			'new_user_notification' => false,
			'password_hashing_disabled' => false,
			'update_users' => false,
			'deactivate_old_memberships' => false,
			'partial' => false,
			'per_partial' => 30,
			'site_id' => null,
		);
		
		$defaults = apply_filters( 'pmp_im_import_default_settings', $defaults );
		
		// Securely extract variables
		$settings = wp_parse_args( $args, $defaults );
		
		// Cast variables to expected type
        $password_nag = (bool) $settings['password_nag'];
		$new_user_notification = (bool) $settings['new_user_notification'];
		$password_hashing_disabled = (bool) $settings['password_hashing_disabled'];
		$update_users = (bool) $settings['update_users'];
		$deactivate_old_memberships = (bool) $settings['deactivate_old_memberships'];
		$partial = (bool) $settings['partial'];
		$site_id = $settings['site_id'];
		$per_partial = apply_filters( 'pmp_im_import_records_per_scan', intval( $settings['per_partial'] ) );
  
		// User data fields list used to differentiate with user meta
		$userdata_fields       = array(
			'ID', 'user_login', 'user_pass',
			'user_email', 'user_url', 'user_nicename',
			'display_name', 'user_registered', 'first_name',
			'last_name', 'nickname', 'description',
			'rich_editing', 'comment_shortcuts', 'admin_color',
			'use_ssl', 'show_admin_bar_front', 'show_admin_bar_admin',
			'role',
		);

		// Mac CR+LF fix
		ini_set( 'auto_detect_line_endings', true );
		
		$file = basename( $filename);
		$fh = fopen(  $filename, 'r');

		// Loop through the file lines
		$first = true;
		$rkey = 0;

		if ( is_multisite() && !empty( $site_id ) ) {
		    switch_to_blog( $site_id );
		}
		
		while (! feof($fh) ) {

			$line = fgetcsv($fh, 0, PMP_IM_CSV_DELIMITER, PMP_IM_CSV_ENCLOSURE, PMP_IM_CSV_ESCAPE );

			// If the first line is empty, abort
			// If another line is empty, just skip it
			if ( empty( $line ) ) {
				if ( true === $first ) {
				    break;
				} else {
				    continue;
				}
			}

			// If we are on the first line, the columns are the headers
			if ( true === $first ) {
				$headers = $line;
				$first = false;
				
				// Skip ahead ?
				if(!empty($partial)) {

				    // Get filename only
					$position = get_option( "pmpcsv_{$file}", null );
					
					if(!empty($position)) {
						fseek($fh,$position);
					}
				}
				
				// On to the next line in the file
				continue;
			}

			// Separate user data from meta
			$userdata = $usermeta = array();
			
			foreach ( $line as $ckey => $column ) {

			    if ( !isset($headers[$ckey] ) ) {
			        $errors[] = new \WP_Error( 'pmp_im_header', sprintf( __("Cannot find header value for %s!", "" ), $ckey ) );
			        continue;
			    }
			    
				$column_name = $headers[$ckey];
				$column = trim( $column );

				if ( in_array( $column_name, $userdata_fields ) ) {
					$userdata[$column_name] = $column;
				} else {
					$usermeta[$column_name] = $column;
				}
			}

			// A plugin may need to filter the data and meta
			$userdata = apply_filters( 'is_iu_import_userdata', $userdata, $usermeta,  $settings );
			$userdata = apply_filters( 'pmp_im_import_userdata', $userdata, $usermeta,  $settings );
			$usermeta = apply_filters( 'is_iu_import_usermeta', $usermeta, $userdata, $settings );
			$usermeta = apply_filters( 'pmp_im_import_usermeta', $usermeta, $userdata, $settings );

			// If no user data, bailout!
			if ( empty( $userdata ) ) {
				continue;
            }

			// Something to be done before importing one user?
			do_action( 'is_iu_pre_user_import', $userdata, $usermeta );
			do_action( 'pmp_im_pre_member_import', $userdata, $usermeta );

			$user = $user_id = false;

			if ( isset( $userdata['ID'] ) ) {
			    $user = get_user_by( 'ID', $userdata['ID'] );
			}

			if ( empty( $user ) && true == $update_users ) {
				if ( isset( $userdata['user_login'] ) )
					{$user = get_user_by( 'login', $userdata['user_login'] );}

				if ( ! $user && isset( $userdata['user_email'] ) )
					{$user = get_user_by( 'email', $userdata['user_email'] );}
			}
			
			$update = false;
			
			if ( !empty( $user ) ) {
				$userdata['ID'] = $user->ID;
				$update = true;
			}
			
			// If creating a new user and no password was set, let auto-generate one!
			if ( false === $update && empty( $userdata['user_pass'] ) ) {
				$userdata['user_pass'] = wp_generate_password( 12, false );
			}
			
            // Insert, Update or insert without (re) hashing the password
			if ( true === $update && false === $password_hashing_disabled ) {
			    $user_id = wp_update_user( $userdata );
			} else if ( false === $update && false === $password_hashing_disabled ) {
			    $user_id = wp_insert_user( $userdata );
			} else {
			    $user_id = $this->insert_disabled_hashing_user( $userdata );
			}
			
            $default_role = apply_filters( 'pmp_im_import_default_user_role', 'subscriber',$user_id, $site_id );

			// Is there an error o_O?
			if ( is_wp_error( $user_id ) ) {
				$errors[$rkey] = $user_id;
			} else {
			 
				// If no error, let's update the user meta too!
				if ( !empty( $usermeta ) ) {
					foreach ( $usermeta as $metakey => $metavalue ) {
						$metavalue = maybe_unserialize( $metavalue );
						update_user_meta( $user_id, $metakey, $metavalue );
					}
				}

				// Set the password nag as needed
                if ( true === $password_nag ) {
                    update_user_option( $user_id, 'default_password_nag', true, true );
                }

                // Adds the user to the specified blog ID if we're in a multisite configuration
                if ( is_multisite() && !empty( $this->site_id ) ) {
                    add_user_to_blog( $site_id, $user_id, $default_role );
                }

				// If we created a new user, send new user notification?
				if ( false === $update ) {
				 
					if ( true === $new_user_notification  ) {
						wp_new_user_notification( $user_id );
                    }
				}

				// Some plugins may need to do things after one user has been imported. Who know?
				do_action( 'is_iu_post_user_import', $user_id, $settings );
				do_action( 'pmp_im_post_user_import', $user_id, $settings );

				$user_ids[] = $user_id;
			}

			$rkey++;
			
			// Doing a partial import, save our location and then exit
			if(!empty($partial) && !empty( $rkey )) {
			 
				$position = ftell($fh);
				
				update_option("pmpcsv_{$file}", $position, 'no' );

				if($rkey > ($per_partial - 1) ) {
				    break;
				}
			}
		}

		fclose( $fh );
		ini_set('auto_detect_line_endings',true);

		// One more thing to do after all imports?
		do_action( 'is_iu_post_users_import', $user_ids, $errors );
		do_action( 'pmp_im_post_users_import', $user_ids, $errors );

		// Let's log the errors
		$this->log_errors( $errors );

		// Return to the active (pre import) site
		if ( is_multisite() ) {
		    switch_to_blog( $current_blog_id );
		}
		
		// delete_option( "pmpcsv_{$file}" );
		
		return array(
			'user_ids' => $user_ids,
			'errors'   => $errors,
		);
	}
	
	/**
	 * Insert an user into the database.
	 * Copied from wp-include/user.php and commented wp_hash_password part
     *
     * @param mixed $userdata
     *
     * @return int|\WP_Error
     *
	 * @since 2.0.1
	 *
	 **/
	private function insert_disabled_hashing_user( $userdata ) {
	   
	   global $wpdb;
	   
		if ( is_a( $userdata, 'stdClass' ) ) {
			$userdata = get_object_vars( $userdata );
		} elseif ( is_a( $userdata, 'WP_User' ) ) {
			$userdata = $userdata->to_array();
		}
		// Are we updating or creating?
		if ( ! empty( $userdata['ID'] ) ) {
			$ID = (int) $userdata['ID'];
			$update = true;
			$old_user_data = \WP_User::get_data_by( 'id', $ID );
			// hashed in wp_update_user(), plaintext if called directly
			// $user_pass = $userdata['user_pass'];
		} else {
			$update = false;
			// Hash the password
			// $user_pass = wp_hash_password( $userdata['user_pass'] );
		}
		$user_pass = $userdata['user_pass'];
		$sanitized_user_login = sanitize_user( $userdata['user_login'], true );
		/**
		 * Filter a username after it has been sanitized.
		 *
		 * This filter is called before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $sanitized_user_login Username after it has been sanitized.
		 */
		$pre_user_login = apply_filters( 'pre_user_login', $sanitized_user_login );
		//Remove any non-printable chars from the login string to see if we have ended up with an empty username
		$user_login = trim( $pre_user_login );
		if ( empty( $user_login ) ) {
			return new \WP_Error('empty_user_login', __('Cannot create a user with an empty login name.') );
		}
		if ( false === $update && username_exists( $user_login ) ) {
			return new \WP_Error( 'existing_user_login', __( 'Sorry, that username already exists!' ) );
		}
		if ( empty( $userdata['user_nicename'] ) ) {
			$user_nicename = sanitize_title( $user_login );
		} else {
			$user_nicename = $userdata['user_nicename'];
		}
		// Store values to save in user meta.
		$meta = array();
		/**
		 * Filter a user's nicename before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $user_nicename The user's nicename.
		 */
		$user_nicename = apply_filters( 'pre_user_nicename', $user_nicename );
		$raw_user_url = empty( $userdata['user_url'] ) ? '' : $userdata['user_url'];
		/**
		 * Filter a user's URL before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $raw_user_url The user's URL.
		 */
		$user_url = apply_filters( 'pre_user_url', $raw_user_url );
		$raw_user_email = empty( $userdata['user_email'] ) ? '' : $userdata['user_email'];
		/**
		 * Filter a user's email before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $raw_user_email The user's email.
		 */
		$user_email = apply_filters( 'pre_user_email', $raw_user_email );
		if ( false === $update && ! defined( 'WP_IMPORTING' ) && email_exists( $user_email ) ) {
			return new \WP_Error( 'existing_user_email', __( 'Sorry, that email address is already used!' ) );
		}
		$nickname = empty( $userdata['nickname'] ) ? $user_login : $userdata['nickname'];
		/**
		 * Filter a user's nickname before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $nickname The user's nickname.
		 */
		$meta['nickname'] = apply_filters( 'pre_user_nickname', $nickname );
		$first_name = empty( $userdata['first_name'] ) ? '' : $userdata['first_name'];
		/**
		 * Filter a user's first name before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $first_name The user's first name.
		 */
		$meta['first_name'] = apply_filters( 'pre_user_first_name', $first_name );
		$last_name = empty( $userdata['last_name'] ) ? '' : $userdata['last_name'];
		/**
		 * Filter a user's last name before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $last_name The user's last name.
		 */
		$meta['last_name'] = apply_filters( 'pre_user_last_name', $last_name );
		if ( empty( $userdata['display_name'] ) ) {
			if ( true === $update ) {
				$display_name = $user_login;
			} elseif ( $meta['first_name'] && $meta['last_name'] ) {
				/* translators: 1: first name, 2: last name */
				$display_name = sprintf( _x( '%1$s %2$s', 'Display name based on first name and last name' ), $meta['first_name'], $meta['last_name'] );
			} elseif ( $meta['first_name'] ) {
				$display_name = $meta['first_name'];
			} elseif ( $meta['last_name'] ) {
				$display_name = $meta['last_name'];
			} else {
				$display_name = $user_login;
			}
		} else {
			$display_name = $userdata['display_name'];
		}
		/**
		 * Filter a user's display name before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $display_name The user's display name.
		 */
		$display_name = apply_filters( 'pre_user_display_name', $display_name );
		$description = empty( $userdata['description'] ) ? '' : $userdata['description'];
		/**
		 * Filter a user's description before the user is created or updated.
		 *
		 * @since 2.0.1
		 *
		 * @param string $description The user's description.
		 */
		$meta['description'] = apply_filters( 'pre_user_description', $description );
		$meta['rich_editing'] = empty( $userdata['rich_editing'] ) ? 'true' : $userdata['rich_editing'];
		$meta['comment_shortcuts'] = empty( $userdata['comment_shortcuts'] ) ? 'false' : $userdata['comment_shortcuts'];
		$admin_color = empty( $userdata['admin_color'] ) ? 'fresh' : $userdata['admin_color'];
		$meta['admin_color'] = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $admin_color );
		$meta['use_ssl'] = empty( $userdata['use_ssl'] ) ? 0 : $userdata['use_ssl'];
		$user_registered = empty( $userdata['user_registered'] ) ? gmdate( 'Y-m-d H:i:s' ) : $userdata['user_registered'];
		$meta['show_admin_bar_front'] = empty( $userdata['show_admin_bar_front'] ) ? 'true' : $userdata['show_admin_bar_front'];
		$user_nicename_check = $wpdb->get_var(
		        $wpdb->prepare(
		                "SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1" ,
		                 $user_nicename,
		                 $user_login
		                 )
                );
		
		if ( !empty( $user_nicename_check ) ) {
		 
			$suffix = 2;
			
			while ($user_nicename_check) {
			 
				$alt_user_nicename = $user_nicename . "-$suffix";
				$user_nicename_check = $wpdb->get_var(
				        $wpdb->prepare(
				                "SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s AND user_login != %s LIMIT 1" ,
				                 $alt_user_nicename,
				                 $user_login
				                 )
                );
				$suffix++;
			}
			
			$user_nicename = $alt_user_nicename;
		}
		
		$compacted = compact( 'user_pass', 'user_email', 'user_url', 'user_nicename', 'display_name', 'user_registered' );
		
		$data = wp_unslash( $compacted );
		
		if ( true === $update ) {
			$wpdb->update( $wpdb->users, $data, compact( 'ID' ) );
			$user_id = (int) $ID;
		} else {
			$wpdb->insert( $wpdb->users, $data + compact( 'user_login' ) );
			$user_id = (int) $wpdb->insert_id;
		}
		$user = new \WP_User( $user_id );
		// Update user meta.
		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
		foreach ( wp_get_user_contact_methods( $user ) as $key => $value ) {
			if ( isset( $userdata[ $key ] ) ) {
				update_user_meta( $user_id, $key, $userdata[ $key ] );
			}
		}
		if ( isset( $userdata['role'] ) ) {
			$user->set_role( $userdata['role'] );
		} elseif ( false === $update ) {
			$user->set_role(get_option('default_role'));
		}
		wp_cache_delete( $user_id, 'users' );
		wp_cache_delete( $user_login, 'userlogins' );
		if ( true === $update ) {
			/**
			 * Fires immediately after an existing user is updated.
			 *
			 * @since 2.0.1
			 *
			 * @param int    $user_id       User ID.
			 * @param object $old_user_data Object containing user's data prior to update.
			 */
			do_action( 'profile_update', $user_id, $old_user_data );
		} else {
			/**
			 * Fires immediately after a new user is registered.
			 *
			 * @since 2.0.1
			 *
			 * @param int $user_id User ID.
			 */
			do_action( 'user_register', $user_id );
		}
		return $user_id;
	}

	/**
	 * AJAX service that does the heavy loading to import a CSV file
	 *
	 * @since 2.0
	 */
	public function wp_ajax_import_members_from_csv() {
	 
        //get settings
		$this->load_settings();
		
        // Error message to return
        if ( empty( $this->filename ) ) {
            wp_send_json_error( array( 'status' => -1, 'message' => __( "No import file provided!", "pmpro-import-members-from-csv" ) ) );
            exit;
        }
        
		//figure out upload dir
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . "/imports/";
		
		//make sure file exists
		if( ! file_exists("{$import_dir}{$this->filename}" ) ) {
			wp_send_json_error(
			        array(
			                'status' => -1,
			                'message' => sprintf(
			                        __("File (%s) not found!", 'pmpro-import-members-from-csv' ),
			                        $this->filename
			                        ),
                        )
            );
			exit;
        }
		
		//import next few lines of file
		$args = array(
			'partial'=>true,
			'filename' => $this->filename,
			'password_nag' => $this->password_nag,
			'password_hashing_disabled' => $this->password_hashing_disabled,
			'update_users' => $this->update_users,
			'new_user_notification' => $this->new_user_notification,
			'new_member_notification' => $this->new_member_notification,
			'deactivate_old_memberships' => $this->deactivate_old_memberships,
			'background_import' => $this->background_import,
			'per_partial' => $this->per_partial,
			'site_id' => $this->site_id,
		);

		$args = apply_filters( 'pmp_im_import_arguments', $args );
		
		if ( WP_DEBUG ) {
		    error_log("Path to import file: {$import_dir}{$this->filename}");
		}
		
		$results = $this->import_csv(  "{$import_dir}{$this->filename}", $args );

		$error_log_msg = null;
			
        if ( file_exists( $this->logfile_path ) ) {
            $error_log_msg = sprintf(
                    __( ', please %1$scheck the error log%2$s' , 'pmpro-import-members-from-csv'),
                    sprintf('<a href="%s">', esc_url_raw( $this->logfile_url ) ),
                    '</a>'
                    );
        }
        
        if ( isset( $_REQUEST['import'] ) ) {
			$error_log_msg = '';
			
			if ( file_exists( $this->logfile_path ) ) {
			    $error_log_msg = sprintf(
			            __( ', please %1$scheck the error log%2$s' , 'pmpro-import-members-from-csv'),
			            sprintf('<a href="%s">', esc_url_raw( $this->logfile_url ) ),
			            '</a>'
			            );
			}
			
            switch ( $_REQUEST['import'] ) {
                case 'file':
                    $status = sprintf('<div class="error"><p><strong>%s</strong></p></div>', __( 'Error during file upload.' , 'pmpro-import-members-from-csv') );
                    break;
                case 'data':
                    $status = sprintf('<div class="error"><p><strong>%s</strong></p></div>', __( 'Cannot extract data from uploaded file or no file was uploaded.' , 'pmpro-import-members-from-csv') );
                    break;
                case 'fail':
                    $status = sprintf('<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'No members were successfully imported%s.' , 'pmpro-import-members-from-csv'), $error_log_msg ) );
                    break;
                case 'errors':
                    $status = sprintf('<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'Some members were successfully imported but some were not%s.' , 'pmpro-import-members-from-csv'), $error_log_msg ) );
                    break;
                case 'success':
                    $status = sprintf( '<div class="updated"><p><strong>%s</strong></p></div>', __( 'Member import was successful.' , 'pmpro-import-members-from-csv') );
                    break;
                default:
            }
        }
			
		// No users imported (or done)
		if ( empty( $results['user_ids'] ) ) {
		
			//Clear the file
			unlink("{$import_dir}{$this->filename}" );
			
			//Clear position
			delete_option("pmpcsv_{$this->filename}");
			
			wp_send_json_success(array( 'status' => true, 'message' => $status ) );
			exit;
			
		} elseif ( !empty( $results['errors'] ) ) {
		    wp_send_json_error( array( 'status' => false, 'message' => sprintf( __('Unable to import certain user records: %s', 'pmpro-import-members-from-csv' ), count( $results['errors'] ), implode( ', ', $results['errors']->getMessages() ) ) ) );
		    exit;
		} else {
		    wp_send_json_success( array( 'status' => true, 'message' => sprintf( __( "Imported %s", "pmpro-import-members-from-csv" ), str_pad('', count($results['user_ids']), '.') . "\n" ) ) );
		    exit;
		}
	}
	
    /**
     * Delete all import_ meta fields before an import in case the user has been imported in the past.
     *
     * @param array $user_data
     * @param array $user_meta
     */
    public function pre_member_import( $user_data, $user_meta ) {
        
        // Init variables
        $user = false;
        $target = null;
        
        //Get user by ID
        if ( isset( $user_data['ID'] ) ) {
            $user = get_user_by( 'ID', $user_data['ID'] );
        }
    
        // That didn't work, now try by login value or email
        if ( empty( $user->ID ) ) {
            
            if ( isset( $user_data['user_login'] ) ) {
                $target = 'login';
                
            } else if ( isset( $user_data['user_email'] ) ) {
                $target = 'email';
            }
            
            if ( !empty( $target ) ) {
                $user = get_user_by( $target, $user_data["user_{$target}"] );
            } else {
                return; // Exit quietly
            }
        }
        
        // Clean up if we found a user (delete the import_ usermeta)
        if(!empty($user->ID)) {
            
            foreach($this->pmpro_fields as $field_name => $value ) {
                delete_user_meta($user->ID, "imported_{$field_name}");
            }
        }
    }

    /**
     * Change some of the imported columns to add "imported_" to the front so we don't confuse the data later.
     *
     * @param array $user_meta
     * @param array $user_data
     *
     * @return array
     */
    public function import_usermeta($user_meta, $user_data) {
        
        foreach($user_meta as $key => $value) {
            
            if( in_array($key, array_keys( $this->pmpro_fields ) ) ) {
                $key = "imported_{$key}";
            }
            
            $user_meta[$key] = $value;
        }
        
        return $user_meta;
    }

    /**
     * After the new user was created, import PMPro membership metadata
     *
     * @param int $user_id
     * @param array $settings
     */
    public function import_membership_info( $user_id, $settings ) {
    
        global $wpdb;
        $errors = array();
        
        wp_cache_delete($user_id, 'users');
        $user = get_userdata($user_id);
        
        if ( empty( $user ) ) {
            $errors[] = new \WP_Error( 'import-member', sprintf( __( "Unable to locate user with expected user ID of %d", "pmpro-import-members-from-csv" ), $user_id ) );
            return;
        }
        
        // Generate PMPro specific member value(s)
        foreach ( $this->pmpro_fields as $var_name => $field_value ) {
            
            $this->pmpro_fields[$var_name] = $user->{"imported_{$var_name}"};

        }
        
        /*
        $membership_id = $user->imported_membership_id;
        $membership_code_id = $user->imported_membership_code_id;
        $membership_discount_code = $user->imported_membership_discount_code;
        $membership_initial_payment = $user->imported_membership_initial_payment;
        $membership_billing_amount = $user->imported_membership_billing_amount;
        $membership_cycle_number = $user->imported_membership_cycle_number;
        $membership_cycle_period = $user->imported_membership_cycle_period;
        $membership_billing_limit = $user->imported_membership_billing_limit;
        $membership_trial_amount = $user->imported_membership_trial_amount;
        $membership_trial_limit = $user->imported_membership_trial_limit;
        $membership_status = $user->imported_membership_status;
        $membership_startdate = $user->imported_membership_startdate;
        $membership_enddate = $user->imported_membership_enddate;
        $membership_timestamp = $user->imported_membership_timestamp;
        */
        
        // Fix date formats
        if ( ! empty( $this->pmpro_fields['membership_startdate'] ) ) {
            $this->pmpro_fields['membership_startdate'] = date_i18n(
                    "Y-m-d 00:00:00",
                    strtotime($this->pmpro_fields['membership_startdate'], current_time('timestamp' )
                    )
            );
        }
        
        if ( ! empty( $this->pmpro_fields['membership_enddate'] ) ) {
            $this->pmpro_fields['membership_enddate'] = date_i18n(
                    "Y-m-d 23:59:59",
                    strtotime(
                            $this->pmpro_fields['membership_enddate'],
                            current_time('timestamp' )
                    )
                );
            
        } else {
            $this->pmpro_fields['membership_enddate'] = null;
        }
        
        if ( ! empty( $this->pmpro_fields['membership_timestamp'] ) ) {
            $this->pmpro_fields['membership_timestamp'] = date_i18n(
                    "Y-m-d H:i:s",
                    strtotime(
                            $this->pmpro_fields['membership_timestamp'],
                            current_time( 'timestamp' )
                    )
            );
        }
        
        //look up discount code
        if ( ! empty( $this->pmpro_fields['membership_discount_code'] ) && empty( $this->pmpro_fields['membership_code_id'] ) ) {
            
            $this->pmpro_fields['membership_code_id'] = $wpdb->get_var(
                    $wpdb->prepare(
                            "SELECT dc.id
                              FROM {$wpdb->pmpro_discount_codes} AS dc
                              WHERE dc.code = %s
                              LIMIT 1",
                              $this->pmpro_fields['membership_discount_code']
                          )
                    );
        }
        
        //Change membership level
        if ( ! empty( $this->pmpro_fields['membership_id'] ) ) {
         
            // Cancel previously existing (active) memberships (Should support MMPU add-on)
            // without triggering cancellation emails, etc
            if ( true === (bool) $this->deactivate_old_memberships ) {
                
                // Update all currently active memberships with the specified ID for the specified user
                $update_sql = $wpdb->prepare(
                        "UPDATE {$wpdb->pmpro_memberships_users} as mu
                                SET mu.status = %s
                                WHERE mu.user_id = %d AND mu.membership_id = %s AND mu.status = %s ",
                                'cancelled',
                                $user_id,
                                $this->pmpro_fields['membership_id'],
                                'active'
                );
                
                if ( false === $wpdb->query( $update_sql ) ) {
                    $errors[] = new \WP_Error( 'import-member',sprintf(
                                    __('Unable to cancel old membership level (ID: %d) for user (ID: %d)', 'pmpro-import-members-from-csv' ),
                                     $this->pmpro_fields['membership_id'],
                                     $user_id
                             ) );
                }
            }
            
            $custom_level = array(
                'user_id' => $user_id,
                'membership_id' => $this->pmpro_fields['membership_id'],
                'code_id' => !empty( $this->pmpro_fields['membership_code_id'] ) ? $this->pmpro_fields['membership_code_id'] : 0,
                'initial_payment' => !empty( $this->pmpro_fields['membership_initial_payment'] ) ? $this->pmpro_fields['membership_initial_payment'] : '',
                'billing_amount' => !empty( $this->pmpro_fields['membership_billing_amount'] ) ? $this->pmpro_fields['membership_billing_amount'] : '',
                'cycle_number' => !empty( $this->pmpro_fields['membership_cycle_number'] ) ? $this->pmpro_fields['membership_cycle_number'] : null,
                'cycle_period' => !empty( $this->pmpro_fields['membership_cycle_period'] ) ? $this->pmpro_fields['membership_cycle_period'] : 'Month',
                'billing_limit' => !empty( $this->pmpro_fields['membership_billing_limit'] ) ? $this->pmpro_fields['membership_billing_limit'] : '',
                'trial_amount' => !empty( $this->pmpro_fields['membership_trial_amount'] ) ? $this->pmpro_fields['membership_trial_amount'] : '',
                'trial_limit' => !empty( $this->pmpro_fields['membership_trial_limit'] ) ? $this->pmpro_fields['membership_trial_limit'] : '',
                'status' => !empty( $this->pmpro_fields['membership_status'] ) ? $this->pmpro_fields['membership_status'] : 'inactive',
                'startdate' => !empty( $this->pmpro_fields['membership_startdate'] ) ? $this->pmpro_fields['membership_startdate'] : null,
                'enddate' => !empty( $this->pmpro_fields['membership_enddate'] ) ? $this->pmpro_fields['membership_enddate'] : null,
            );
            
            pmpro_changeMembershipLevel($custom_level, $user_id, 'cancelled' );
            
            // If membership ended in the past, make it inactive for now
            if ( "inactive" === $this->pmpro_fields['membership_status'] ||
                ( ! empty($this->pmpro_fields['membership_enddate']) &&
                    $this->pmpro_fields['membership_enddate'] !== "NULL" &&
                    strtotime($this->pmpro_fields['membership_enddate'], current_time('timestamp') ) < current_time('timestamp' )
                    )
                ) {
                
                if ( false !== $wpdb->update(
                        $wpdb->pmpro_memberships_users,
                        array( 'status' => 'inactive' ),
                        array( 'user_id' => $user_id, 'membership_id' => $this->pmpro_fields['membership_id'] ),
                        array( '%s' ),
                        array( '%d', '%d' )
                        )
                   ) {
                    $membership_in_the_past = true;
                } else {
                    $errors[] = new \WP_Error( 'import-member',sprintf( __('Unable to set inactive membership status for user (ID: %d) with membership level ID %d', 'pmpro-import-members-from-csv' ),$user_id, $this->pmpro_fields['membership_id'] ) );
                }
            }
            
            if ( 'active' === $this->pmpro_fields['membership_status'] &&
            ( empty( $this->pmpro_fields['membership_enddate'] ) ||
                'NULL' === strtoupper( $this->pmpro_fields['membership_enddate'] )  ||
                strtotime($this->pmpro_fields['membership_enddate'], current_time('timestamp') ) >= current_time( 'timestamp' ) )
            ) {
                
                if ( false === $wpdb->update( $wpdb->pmpro_memberships_users, array( 'status' => 'active' ), array( 'user_id' => $user_id, 'membership_id' => $this->pmpro_fields['membership_id'] ) ) ) {
                    $errors[] = new \WP_Error( 'import-member',sprintf( __('Unable to set activate membership for user (ID: %d) with membership level ID %d', 'pmpro-import-members-from-csv' ),$user_id, $this->pmpro_fields['membership_id'] ) );
                }
            }
        }
        
        //look for a subscription transaction id and gateway
        $this->pmpro_fields['membership_subscription_transaction_id'] = $user->import_membership_subscription_transaction_id;
        $this->pmpro_fields['membership_payment_transaction_id'] = $user->import_membership_payment_transaction_id;
        $this->pmpro_fields['membership_affiliate_id'] = $user->import_membership_affiliate_id;
        $this->pmpro_fields['membership_gateway'] = $user->import_membership_gateway;
        
        // Add a PMPro order record so integration with gateway doesn't cause surprises
        if (
            !empty($this->pmpro_fields['membership_subscription_transaction_id']) && !empty($this->pmpro_fields['membership_gateway']) ||
            !empty($this->pmpro_fields['membership_timestamp']) || !empty($this->pmpro_fields['membership_code_id'])
        ) {
            $order = new \MemberOrder();
            $order->user_id = $user_id;
            $order->membership_id =  $this->pmpro_fields['membership_id'];
            $order->InitialPayment = $this->pmpro_fields['membership_initial_payment'];
            $order->payment_transaction_id = $this->pmpro_fields['membership_payment_transaction_id'];
            $order->subscription_transaction_id = $this->pmpro_fields['membership_subscription_transaction_id'];
            $order->affiliate_id = $this->pmpro_fields['membership_affiliate_id'];
            $order->gateway = $this->pmpro_fields['membership_gateway'];
            
            if( true === $membership_in_the_past ) {
                $order->status = "cancelled";
            }
            
            $order->saveOrder();
    
            //update timestamp of order?
            if(!empty($this->pmpro_fields['membership_timestamp'])) {
                
                $timestamp = strtotime($this->pmpro_fields['membership_timestamp'], current_time('timestamp'));
                
                $order->updateTimeStamp(
                        date("Y", $timestamp),
                        date("m", $timestamp),
                        date("d", $timestamp),
                        date("H:i:s", $timestamp)
                );
            }
        }
        
        // Add any Discount Code use for this user
        if( ! empty( $this->pmpro_fields['membership_code_id'] ) && ! empty( $order ) && !empty( $order->id ) ) {
            
            if ( false === $wpdb->insert(
                    $wpdb->pmpro_discount_codes_uses,
                    array(
                            'code_id' => $this->pmpro_fields['membership_code_id'],
                            'user_id' => $user_id,
                            'order_id' => $order->id,
                            'timestamp' => 'CURRENT_TIMESTAMP',
                    ),
                    array( '%d', '%d', '%d', '%s')
            ) ) {
                $errors[] = new \WP_Error( 'import-member',sprintf( __('Unable to set update discount code usage for code (ID: %d ) for user (user/order id: %d/%s)', 'pmpro-import-members-from-csv' ),$this->pmpro_fields['membership_code_id'], $user_id, $order->id ) );
            }
        }
    
        // Email 'your membership account is active' to member if they were imported with an active member status
        if( true === $this->new_member_notification && isset( $this->pmpro_fields['membership_status'] ) && 'active' === $this->pmpro_fields['membership_status'] ) {
            
            if ( !empty( $pmproiufcsv_email )) {
                $subject = apply_filters(
                    'pmp_im_imported_member_message_subject', $pmproiufcsv_email['subject'] );
                $body = apply_filters( 'pmp_im_imported_member_message_body', $pmproiufcsv_email['body'] );
            } else {
                $subject = apply_filters(
                    'pmp_im_imported_member_message_subject',
                        __("Your membership to !!sitename!! has been activated", 'pmpro-import-members-from-csv' )
                );
            
                $body = apply_filters( 'pmp_im_imported_member_message_body', null );

            }
            
            $email = new \PMProEmail();
            $email->recipient = $user->user_email;
            $email->data = apply_filters( 'pmp_im_imported_member_message_data', array() );
            $email->subject = $subject;
            $email->body = $body;
            $email->template = 'imported_member';
            
            // Process and send email
            $email->sendEmail();
        }
        
        if ( !empty( $errors ) ) {
            $this->log_errors( $errors );
        }
    }

    /**
     * Add links to support & docs for the plugin
     *
     * @param array $links - Links for the Plugins page
     * @param string $file
     *
     * @return array
     */
    public function plugin_row_meta($links, $file) {
	
        if( false !== strpos($file, 'pmpro-import-members-from-csv.php') ) {
            // Add (new) 'Import Users from CSV' links to plugin listing
            $new_links = array(
                sprintf(
                        '<a href="%s" title="%s">%s</a>',
                        esc_url( 'https://eighty20results.com/wordpress-plugins/pmpro-import-members-from-csv/' ),
                        __( 'View Documentation', 'paid-memberships-pro' ),
                        __( 'Docs', 'paid-memberships-pro' )
                ),
                sprintf(
                        '<a href="%s" title="%s">%s</a>',
                        esc_url( 'https://eighty20results.com/support/' ),
                        __( 'Visit Support Forum', 'pmpro' ),
                        __( 'Support', 'paid-memberships-pro' )
                ),
            );
            
            $links     = array_merge( $links, $new_links );
        }
        
	    return $links;
    }
}

// Load the plugin.
add_action('plugins_loaded', array( Import_Members_From_CSV::get_instance(), 'load_plugins' ) );

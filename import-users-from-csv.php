<?php
/*
Plugin Name: Import Users from CSV
Plugin URI: http://wordpress.org/plugins/import-users-from-csv/
Description: Import Users and their metadata from a csv file.
Version: 2.0.1
Requires PHP: 5.4
Author: <a href="http://ulrichsossou.com/">Ulrich Sossou</a>, <a href="https://eighty20results.com/thomas-sjolshagen/">Thomas Sjolshagen <thomas@eighty20results.com></a>
License: GPL2
Text Domain: import-users-from-csv
Domain Path: languages/
*/
/*  Copyright 2011, 2017  Ulrich Sossou  (https://github.com/sorich87), Thomas Sjolshagen (https://eighty20results.com/thomas-sjolshagen)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'IS_IU_CSV_DELIMITER' ) ) {
    define ( 'IS_IU_CSV_DELIMITER', ',' );
}

if ( ! defined( 'IS_IU_CSV_ESCAPE') ) {
    define ( 'IS_IU_CSV_ESCAPE', '\\' );
}

if ( ! defined( 'IS_IU_CSV_ENCLOSURE') ) {
    define ( 'IS_IU_CSV_ENCLOSURE', '"' );
}

/**
 * Main plugin class
 *
 * @since 0.1
 **/
class IS_IU_Import_Users {
 
	private static $log_dir_path = '';
	private static $log_dir_url  = '';
    private static $instance = null;
    
    /**
      * IS_IU_Import_Users constructor.
      */
	public function __construct() {
     
	    if ( is_null( self::$instance ) ) {
	        self::$instance = $this;
	    }
	    
	    load_plugin_textdomain( 'import-users-from-csv', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	/**
	 * Initialization
	 *
	 * @since 0.1
	 **/
	public static function init() {
	 
		add_action( 'admin_menu', array( self::$instance, 'add_admin_pages' ) );
		add_action( 'init', array( self::$instance, 'process_csv' ) );
		add_action( 'admin_enqueue_scripts', array( self::$instance, 'admin_enqueue_scripts') );
		add_action('wp_ajax_import_users_from_csv', array( self::$instance, 'wp_ajax_import_users_from_csv'));
		
		$upload_dir = wp_upload_dir();
		self::$log_dir_path = trailingslashit( $upload_dir['basedir'] );
		self::$log_dir_url  = trailingslashit( $upload_dir['baseurl'] );
	}

	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public static function users_page() {
	 
		if ( ! current_user_can( 'create_users' ) ) {
		    wp_die( __( 'You do not have sufficient permissions to access this page.' , 'import-users-from-csv') );
		}
	?>
	<div class="wrap">
		<h2><?php _e( 'Import users from a CSV file' , 'import-users-from-csv'); ?></h2>
		<?php
		$error_log_file = self::$log_dir_path . 'is_iu_errors.log';
		$error_log_url  = self::$log_dir_url . 'is_iu_errors.log';

		if ( ! file_exists( $error_log_file ) ) {
		 
			if ( ! @fopen( $error_log_file, 'x' ) ) {
			    
                printf( '<div class="updated"><p><strong>%s</strong></p></div>',
                sprintf(
                        __( 'Note: Please make the %s directory writable to allow you to see/save the error log.' , 'import-users-from-csv'),
                         self::$log_dir_path
                         )
                );
			}
		}

		if ( isset( $_REQUEST['import'] ) ) {
			$error_log_msg = '';
			
			if ( file_exists( $error_log_file ) ) {
			    $error_log_msg = sprintf(
			            __( ', please %1$scheck the error log%2$s' , 'import-users-from-csv'),
			            sprintf('<a href="%s">', esc_url_raw( $error_log_url ) ),
			            '</a>'
			            );
			}

			switch ( $_REQUEST['import'] ) {
				case 'file':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', __( 'Error during file upload.' , 'import-users-from-csv') );
					break;
				case 'data':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', __( 'Cannot extract data from uploaded file or no file was uploaded.' , 'import-users-from-csv') );
					break;
				case 'fail':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'No user was successfully imported%s.' , 'import-users-from-csv'), $error_log_msg ) );
					break;
				case 'errors':
					printf( '<div class="error"><p><strong>%s</strong></p></div>', sprintf( __( 'Some users were successfully imported but some were not%s.' , 'import-users-from-csv'), $error_log_msg ) );
					break;
				case 'success':
					printf( '<div class="updated"><p><strong>%s</strong></p></div>', __( 'Users import was successful.' , 'import-users-from-csv') );
					break;
				default:
					break;
			}
			
			if($_REQUEST['import'] == 'resume' && !empty($_REQUEST['filename'])) {
			 
				$filename = sanitize_file_name($_REQUEST['filename']);
				//resetting position transients?
				if(!empty($_REQUEST['reset'])) {
				    $file = basename( $filename );
				    delete_option("iufcsv_{$filename}" );
				}
			?>
			<h3><?php _e( 'Importing file using AJAX', 'import-users-from-csv' ); ?></h3>
			<p><strong><?php _e('IMPORTANT:', 'import-users-from-csv' ); ?></strong> <?php printf(
			        __('Your import is not finished. Closing this page will stop it. If the import stops or you have to close your browser, you can navigate to %sthis URL%s to resume the import later.', 'import-users-from-csv'),
			sprintf('<a href="%s">', admin_url( $_SERVER['QUERY_STRING'] ) ),
			'</a>'
			); ?>
			</p>
			
			<p>
				<a id="pauseimport" href="#"><?php _e("Click here to pause.", 'import-users-from-csv' ); ?></a>
				<a id="resumeimport" href="#" style="display:none;"><?php _e("Paused. Click here to resume.", "import-users-from-csv" ); ?></a>
			</p>
			
			<textarea id="importstatus" rows="10" cols="60"><?php _e( 'Loading...', 'import-users-from-csv' ); ?></textarea>
			<?php
			}
		}
		
		if(empty($_REQUEST['filename']))
		{
		?>
		<form method="post" action="" enctype="multipart/form-data">
			<?php wp_nonce_field( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="users_csv"><?php _e( 'CSV file' , 'import-users-from-csv'); ?></label></th>
					<td>
						<input type="file" id="users_csv" name="users_csv" value="" class="all-options" /><br />
						<span class="description"><?php echo sprintf( __( 'You may want to see <a href="%s">the example of the CSV file</a>.' , 'import-users-from-csv'), plugin_dir_url(__FILE__).'examples/import.csv'); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Notification' , 'import-users-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Notification' , 'import-users-from-csv'); ?></span></legend>
						<label for="new_user_notification">
							<input id="new_user_notification" name="new_user_notification" type="checkbox" value="1" />
							<?php _e('Send to new users', 'import-users-from-csv') ?>
						</label>
					</fieldset></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Password nag' , 'import-users-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Password nag' , 'import-users-from-csv'); ?></span></legend>
						<label for="password_nag">
							<input id="password_nag" name="password_nag" type="checkbox" value="1" />
							<?php _e('Show password nag on new users signon', 'import-users-from-csv') ?>
						</label>
					</fieldset></td>
				</tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Password is hashed' , 'import-users-from-csv'); ?></th>
                    <td><fieldset>
                        <legend class="screen-reader-text"><span><?php _e( 'Password is hashed' , 'import-users-from-csv' ); ?></span></legend>
                        <label for="password_hashing_disabled">
                            <input id="password_hashing_disabled" name="password_hashing_disabled" type="checkbox" value="1" />
                            <?php _e( 'Password is hashed', 'import-users-from-csv' ) ;?>
                        </label>
                    </fieldset></td>
                </tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Users update' , 'import-users-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'Users update' , 'import-users-from-csv' ); ?></span></legend>
						<label for="users_update">
							<input id="users_update" name="users_update" type="checkbox" value="1" />
							<?php _e( 'Update user when a username or email exists', 'import-users-from-csv' ) ;?>
						</label>
					</fieldset></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'AJAX' , 'import-users-from-csv'); ?></th>
					<td><fieldset>
						<legend class="screen-reader-text"><span><?php _e( 'AJAX' , 'import-users-from-csv' ); ?></span></legend>
						<label for="background_import">
							<input id="background_import" name="background_import" type="checkbox" value="1" />
							<?php _e( 'Use AJAX to process the import gradually over time.', 'import-users-from-csv' ) ;?>
						</label>
					</fieldset></td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Import' , 'import-users-from-csv'); ?>" />
			</p>
		</form><?php
		}
	}

	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
	 
		add_users_page(
		        __( 'Import From CSV' , 'import-users-from-csv'),
		        __( 'Import From CSV' , 'import-users-from-csv'),
		        'create_users',
		        'import-users-from-csv',
		        array( self::$instance, 'users_page' )
        );
	}
	
	/**
	 * Add admin JS
	 *
	 * @since ?
	 **/
	public function admin_enqueue_scripts($hook) {
	 
		if ( !isset($_GET['page']) || $_GET['page'] != 'import-users-from-csv') {
			return;
		}

		$filename              = isset( $_REQUEST['filename'] ) ? sanitize_file_name($_REQUEST['filename']) : null;
		$password_nag          = isset( $_REQUEST['password_nag'] ) ? ( 1 === intval( $_REQUEST['password_nag'] ) ) : false;
		$password_hashing_disabled 	= isset( $_POST['password_hashing_disabled'] ) ? ( 1 === intval(  $_REQUEST['password_hashing_disabled'] ) ): false;
		$users_update          = isset( $_REQUEST['users_update'] ) ? ( 1 === intval($_REQUEST['users_update'] ) ) : false;
		$new_user_notification = isset( $_REQUEST['new_user_notification'] ) ? ( 1 === intval($_REQUEST['new_user_notification'] ) ) : false;
        $max_run_time = (
                apply_filters( 'is_iu_import_time_per_record', 3 ) *
                apply_filters( 'is_iu_import_records_per_scan', 30 )
            );
        
		wp_register_script( 'import-users-from-csv', plugins_url( 'javascript/import-users-from-csv.js',__FILE__ ), array('jquery' ), '2.0.1'  );
		
		wp_localize_script( 'import-users-from-csv', 'ia_settings',
		array(
		            'timeout' => $max_run_time,
                    'filename' => $filename,
                    'users_update' => $users_update,
                    'new_user_notification' => $new_user_notification,
                    'password_hashing_disabled' => $password_hashing_disabled,
                    'password_nag' => $password_nag,
                )
		);
        wp_enqueue_script( 'import-users-from-csv' );
    }
 
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function process_csv() {
	 
		if ( isset( $_REQUEST['_wpnonce-is-iu-import-users-users-page_import'] ) ) {
			
		    check_admin_referer( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' );
			
			if ( isset( $_FILES['users_csv']['tmp_name'] ) ) {
			 
				// Setup settings variables
				$filename              = $_FILES['users_csv']['tmp_name'];
		        $password_nag          = isset( $_REQUEST['password_nag'] ) ? ( 1 === intval( $_REQUEST['password_nag'] ) ) : false;
		        $password_hashing_disabled 	= isset( $_POST['password_hashing_disabled'] ) ? ( 1 === intval(  $_REQUEST['password_hashing_disabled'] ) ): false;
		        $users_update          = isset( $_REQUEST['users_update'] ) ? ( 1 === intval($_REQUEST['users_update'] ) ) : false;
		        $new_user_notification = isset( $_REQUEST['new_user_notification'] ) ? ( 1 === intval($_REQUEST['new_user_notification'] ) ) : false;
				
		        
				//use AJAX?
				if ( !empty( $_REQUEST['background_import'] ) ) {
				 
					//check for a imports directory in wp-content
					$upload_dir = wp_upload_dir();
					$import_dir = $upload_dir['basedir'] . "/imports/";
					
					//create the dir and subdir if needed
					if(!is_dir($import_dir)) {
						wp_mkdir_p($import_dir);
					}
					
					//figure out filename
					$filename = $_FILES['users_csv']['name'];
					$file_arr = explode( '.', $filename );
					$filetype = $file_arr[ (count( $file_arr ) - 1 ) ];
					
					$count = 0;
					
					while ( file_exists($import_dir . $filename ) ) {
					 
						if( !empty( $count ) ) {
							$filename = self::str_lreplace("-{$count}.{$filetype}", "-" . strval($count+1) . ".{$filetype}", $filename );
						} else {
							$filename = self::str_lreplace(".{$filetype}", "-1.{$filetype}", $filename);
                        }
										
						$count++;
						
						//let's not expect more than 50 files with the same name
						if($count > 50) {
							die("Error uploading file. Too many files with the same name. Clean out the " . $import_dir . " directory on your server.");
						}
					}
					
					//save file
					if(strpos($_FILES['users_csv']['tmp_name'], $upload_dir['basedir']) !== false) {
					 
						//was uploaded and saved to $_SESSION
						rename($_FILES['users_csv']['tmp_name'], $import_dir . $filename);
					} else {
						//it was just uploaded
						move_uploaded_file($_FILES['users_csv']['tmp_name'], $import_dir . $filename);
					}
					
					//redurect to the page to run AJAX
					$url = add_query_arg(
					        array(
                                'page' => 'import-users-from-csv',
                                'import' => 'resume',
                                'filename' => $filename,
                                'password_nag'=>$password_nag,
                                'password_hashing_disabled' => $password_hashing_disabled,
                                'new_user_notification'=>$new_user_notification,
                                'users_update'=>$users_update,
					        ),
					        admin_url('users.php' )
                        );
					
					wp_redirect($url);
					exit;
					
				} else {
				 
					$results = self::import_csv( $filename, array(
						'password_nag' => $password_nag,
						'new_user_notification' => $new_user_notification,
						'users_update' => $users_update,
						'password_hashing_disabled' => $password_hashing_disabled,
					) );

					// No users imported?
					if ( ! $results['user_ids'] )
						{wp_redirect( add_query_arg( 'import', 'fail', wp_get_referer() ) );}

					// Some users imported?
					elseif ( $results['errors'] )
						{wp_redirect( add_query_arg( 'import', 'errors', wp_get_referer() ) );}

					// All users imported? :D
					else
						{wp_redirect( add_query_arg( 'import', 'success', wp_get_referer() ) );}

					exit;
				}
			}

			wp_redirect( add_query_arg( 'import', 'file', wp_get_referer() ) );
			exit;
		}
	}

	/**
      * Replace leftmost instance of string
      *
      * @param $search
      * @param $replace
      * @param $subject
      *
      * @return string
      */
	public static function str_lreplace($search, $replace, $subject) {
	    
        $pos = strrpos($subject, $search);

        if($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search) );
        }

        return $subject;
    }

	/**
	 * Import a csv file
	 *
	 * @since 0.5
	 */
	public static function import_csv( $filename, $args ) {
		
	    $errors = $user_ids = array();
		$headers = array();
		
		$defaults = array(
			'password_nag' => false,
			'new_user_notification' => false,
			'password_hashing_disabled' => false,
			'users_update' => false,
			'partial' => false,
			'per_partial' => 30,
		);
		
		// Securely extract variables
		$settings = wp_parse_args( $args, $defaults );
		
		// Cast variables to expected type
        $password_nag = (bool) $settings['password_nag'];
		$new_user_notification = (bool) $settings['new_user_notification'];
		$password_hashing_disabled = (bool) $settings['password_hashing_disabled'];
		$users_update = (bool) $settings['users_update'];
		$partial = (bool) $settings['partial'];
		$per_partial = apply_filters( 'is_iu_import_records_per_scan', intval( $settings['per_partial'] ) );

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
		
		$file = basename($filename);
		$fh = fopen( $filename, 'r');

		// Loop through the file lines
		$first = true;
		$rkey = 0;

		while (! feof($fh) ) {

			$line = fgetcsv($fh, 0, IS_IU_CSV_DELIMITER, IS_IU_CSV_ENCLOSURE, IS_IU_CSV_ESCAPE );

			// If the first line is empty, abort
			// If another line is empty, just skip it
			if ( empty( $line ) ) {
				if ( $first ) {
				    break;
				} else {
				    continue;
				}
			}

			// If we are on the first line, the columns are the headers
			if ( $first ) {
				$headers = $line;
				$first = false;
				
				//skip ahead for partial imports
				if(!empty($partial)) {

				    // Get filename only
					$position = get_option( "iufcsv_{$file}", null );
					
					if(!empty($position)) {
						fseek($fh,$position);
					}
				}
				
				continue;
			}

			// Separate user data from meta
			$userdata = $usermeta = array();
			
			foreach ( $line as $ckey => $column ) {
			 
				$column_name = $headers[$ckey];
				$column = trim( $column );

				if ( in_array( $column_name, $userdata_fields ) ) {
					$userdata[$column_name] = $column;
				} else {
					$usermeta[$column_name] = $column;
				}
			}

			// A plugin may need to filter the data and meta
			$userdata = apply_filters( 'is_iu_import_userdata', $userdata, $usermeta );
			$usermeta = apply_filters( 'is_iu_import_usermeta', $usermeta, $userdata );

			// If no user data, bailout!
			if ( empty( $userdata ) ) {
				continue;
            }

			// Something to be done before importing one user?
			do_action( 'is_iu_pre_user_import', $userdata, $usermeta );

			$user = $user_id = false;

			if ( isset( $userdata['ID'] ) )
				{$user = get_user_by( 'ID', $userdata['ID'] );}

			if ( empty( $user ) && true == $users_update ) {
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
			    $user_id = self::insert_disabled_hashing_user( $userdata );
			}

			// Is there an error o_O?
			if ( is_wp_error( $user_id ) ) {
				$errors[$rkey] = $user_id;
			} else {
			 
				// If no error, let's update the user meta too!
				if ( $usermeta ) {
					foreach ( $usermeta as $metakey => $metavalue ) {
						$metavalue = maybe_unserialize( $metavalue );
						update_user_meta( $user_id, $metakey, $metavalue );
					}
				}

                if ( true === $password_nag ) {
                    update_user_option( $user_id, 'default_password_nag', true, true );
                }

				// If we created a new user, maybe set password nag and send new user notification?
				if ( false === $update ) {

					if ( true === $new_user_notification  ) {
						wp_new_user_notification( $user_id );
                    }
				}

				// Some plugins may need to do things after one user has been imported. Who know?
				do_action( 'is_iu_post_user_import', $user_id );

				$user_ids[] = $user_id;
			}

			$rkey++;
			
			// Doing a partial import, save our location and then exit
			if(!empty($partial) && $rkey) {
			 
				$position = ftell($fh);
				
				update_option("iufcsv_{$file}", $position, 'no' );

				if($rkey > $per_partial-1) {
				    break;
				}
			}
		}

		fclose( $fh );
		ini_set('auto_detect_line_endings',true);

		// One more thing to do after all imports?
		do_action( 'is_iu_post_users_import', $user_ids, $errors );

		// Let's log the errors
		self::log_errors( $errors );

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
     * @return int
     *
	 * @since 2.0.1
	 *
	 **/
	private static function insert_disabled_hashing_user( $userdata ) {
	   
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
			$old_user_data = WP_User::get_data_by( 'id', $ID );
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
			return new WP_Error('empty_user_login', __('Cannot create a user with an empty login name.') );
		}
		if ( ! $update && username_exists( $user_login ) ) {
			return new WP_Error( 'existing_user_login', __( 'Sorry, that username already exists!' ) );
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
		if ( ! $update && ! defined( 'WP_IMPORTING' ) && email_exists( $user_email ) ) {
			return new WP_Error( 'existing_user_email', __( 'Sorry, that email address is already used!' ) );
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
			if ( $update ) {
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
		$user_nicename_check = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1" , $user_nicename, $user_login));
		if ( $user_nicename_check ) {
			$suffix = 2;
			while ($user_nicename_check) {
				$alt_user_nicename = $user_nicename . "-$suffix";
				$user_nicename_check = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1" , $alt_user_nicename, $user_login));
				$suffix++;
			}
			$user_nicename = $alt_user_nicename;
		}
		$compacted = compact( 'user_pass', 'user_email', 'user_url', 'user_nicename', 'display_name', 'user_registered' );
		$data = wp_unslash( $compacted );
		if ( $update ) {
			$wpdb->update( $wpdb->users, $data, compact( 'ID' ) );
			$user_id = (int) $ID;
		} else {
			$wpdb->insert( $wpdb->users, $data + compact( 'user_login' ) );
			$user_id = (int) $wpdb->insert_id;
		}
		$user = new WP_User( $user_id );
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
		} elseif ( ! $update ) {
			$user->set_role(get_option('default_role'));
		}
		wp_cache_delete( $user_id, 'users' );
		wp_cache_delete( $user_login, 'userlogins' );
		if ( $update ) {
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
	 * Log errors to a file
	 *
	 * @since 0.2
	 **/
	private static function log_errors( $errors ) {
	 
		if ( empty( $errors ) )
			{return;}

		$log = @fopen( self::$log_dir_path . 'is_iu_errors.log', 'a' );
		@fwrite( $log, sprintf( __( 'BEGIN %s' , 'import-users-from-csv'), date( 'Y-m-d H:i:s', time() ) ) . "\n" );

		foreach ( $errors as $key => $error ) {
			$line = $key + 1;
			$message = $error->get_error_message();
			@fwrite( $log, sprintf( __( '[Line %1$s] %2$s' , 'import-users-from-csv'), $line, $message ) . "\n" );
		}

		@fclose( $log );
	}

	/**
	 * AJAX service to import file
	 *
	 * @since 2.0
	 */
	public function wp_ajax_import_users_from_csv() {
	 
		//check for filename
		if(empty($_REQUEST['filename'])) {
			wp_die( __( "No file name given.", "import-users-from-csv" ) );
        } else {
			$filename = sanitize_file_name($_REQUEST['filename']);
        }
		
		//figure out upload dir
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . "/imports/";
		
		//make sure file exists
		if( ! file_exists("{$import_dir}{$filename}" ) ) {
			wp_send_json_error(array( 'status' => -1, 'message' => sprintf( __("File (%s) not found!", 'import-users-from-csv' ), $filename ) ) );
			exit;
        }

		//get settings
		$password_nag          = isset( $_REQUEST['password_nag'] ) ? ( 1 === intval( $_REQUEST['password_nag'] ) ) : false;
		$users_update          = isset( $_REQUEST['users_update'] ) ? ( 1 === intval($_REQUEST['users_update'] ) ) : false;
		$new_user_notification = isset( $_REQUEST['new_user_notification'] ) ? ( 1 === intval($_REQUEST['new_user_notification'] ) ) : false;
		$password_hashing_disabled 	= isset( $_POST['password_hashing_disabled'] ) ? ( 1 === intval(  $_REQUEST['password_hashing_disabled'] ) ): false;
		
		//import next few lines of file
		$args = array(
			'partial'=>true,
			'password_nag' => $password_nag,
			'password_hashing_disabled' => $password_hashing_disabled,
			'users_update' => $users_update,
			'new_user_notification' => $new_user_notification,
		);
	
		$results = self::import_csv( "{$import_dir}{$filename}", $args );

		// No users imported (or done)
		if ( empty( $results['user_ids'] ) ) {
		
			//Clear the file
			unlink("{$import_dir}{$filename}" );
			
			//Clear position
			delete_option("iufcsv_{$filename}");
			
			wp_send_json_success(array( 'status' => true, 'message' => null ) );
			exit;
			
		} elseif ( !empty( $results['errors'] ) ) {
		    wp_send_json_error( array( 'status' => false, 'message' => sprintf( __('Unable to import certain user records: %s', 'import-users-from-csv' ), count( $results['errors'] ), implode( ', ', $results['errors']->getMessages() ) ) ) );
		    exit;
		} else {
		    wp_send_json_success( array( 'status' => true, 'message' => sprintf( __( "Imported %s", "import-users-from-csv" ), str_pad('', count($results['user_ids']), '.') . "\n" ) ) );
		    exit;
		}
	}
}

// Load the plugin.
add_action('plugins_loaded', array( new IS_IU_Import_Users(), 'init' ) );

<?php
/**
 * Registers upload acceleration
 *
 * @link       https://swarmify.com/
 * @since      1.0.0
 *
 * @package    Swarmify
 * @subpackage Swarmify/includes
 */

/**
 * Register upload acceleration for the plugin.
 *
 * Hooks into media upload subsytem to improve uploading of large
 * media files.
 *
 * @package    Swarmify
 * @subpackage Swarmify/includes
 */
class SwarmifyUploadAccelerator {

	/**
	 * SwarmifyUploadAccelerator instance.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 * @var SwarmifyUploadAccelerator
	 */
	private static $instance = false;

	/**
	 * Get the instance.
	 * 
	 * Returns the current instance, creates one if it
	 * doesn't exist. Ensures only one instance of
	 * SwarmifyUploadAccelerator is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 *
	 * @return SwarmifyUploadAccelerator
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;

	}

	/**
	 * Constructor.
	 * 
	 * Initializes and adds functions to filter and action hooks.
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {

        // Only enable if the option is turned on for it so that users with problems
        // can disable.
        if( get_option( 'swarmify_toggle_uploadacceleration', 'on' ) == 'on' ) {
            add_filter( 'plupload_init', array( $this, 'filter_plupload_settings' ) );
            add_filter( 'upload_post_params', array( $this, 'filter_plupload_params' ) );
            add_filter( 'plupload_default_settings', array( $this, 'filter_plupload_settings' ) );
            add_filter( 'plupload_default_params', array( $this, 'filter_plupload_params' ) );
            add_action( 'wp_ajax_swarmify_upload_accelerator', array( $this, 'ajax_chunk_receiver' ) );

            // This is used by other forms and confuses them. But it gets ignored 
            // for media uploads as we set a custom limit in 'filter_plupload_settings'
            //add_filter( 'upload_size_limit', array( $this, 'filter_upload_size_limit' ) );
        }

	}

	/**
	 * Filter plupload params.
	 * 
	 * @since 1.2.0
	 */
	public function filter_plupload_params( $plupload_params ) {

		$plupload_params['action'] = 'swarmify_upload_accelerator';
		return $plupload_params;

	}

	/**
	 * Filter plupload settings.
	 * 
	 * @since 1.0.0
	 */
	public function filter_plupload_settings( $plupload_settings ) {
		$chunk_size = $this->get_chunk_size( '' );
        $retries = 7;

		$plupload_settings['url'] = admin_url( 'admin-ajax.php' );
		$plupload_settings['filters']['max_file_size'] = $this->filter_upload_size_limit('') . 'b';
		$plupload_settings['chunk_size'] = $chunk_size . 'b';
		$plupload_settings['max_retries'] = $retries;
		return $plupload_settings;

	}

	/**
	 * Return the maximum upload size.
	 * 
	 * Free space of temp directory.
	 * 
	 * @since 1.0.0
	 * 
	 * @return float $bytes Free disk space in bytes.
	 */
	public function filter_upload_size_limit( $unused ) {

        // Check whether the `disk_free_space` function is disabled
        $freeSpaceDisabled = strpos(ini_get("disable_functions"), "disk_free_space");

        if( $freeSpaceDisabled !== false ) {
            $bytes = null;
        } else {
            $bytes = disk_free_space( sys_get_temp_dir() );
        }

        if ( $bytes === false  || is_null($bytes) ) {
			$bytes = 5 * 1024 * 1024 * 1024;
		}
		return $bytes;

	}

    /**
	 * Return the chunk size to use
	 * 
	 * Half of the `post_max_size`.
	 * 
	 * @since 1.0.0
	 * 
	 * @return int $bytes Chunk size for uploads
	 */
    public function get_chunk_size( $unused ) {

        $post_max = ini_get( 'post_max_size' );

        $val = trim($post_max);
        $last = strtolower($val[strlen($val)-1]);
        $val = intval( $val );
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
    
        // Use half of the `post_max_size` as a safe chunk size value
        return intval( $val / 2 );

    }

	/**
	 * Return a file's mime type. 
	 * 
	 * @since 1.2.0
	 * 
	 * @param string $filename File name.
	 * @return var string $mimetype Mime type.
	 */
	public function get_mime_content_type( $filename ) {

		if ( function_exists( 'mime_content_type' ) ) {
			return mime_content_type( $filename );
		}

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME );
			$mimetype = finfo_file( $finfo, $filename );
			finfo_close( $finfo );
			return $mimetype;
		} else {
			ob_start();
			system( 'file -i -b ' . $filename );
			$output = ob_get_clean();
			$output = explode( '; ', $output );
			if ( is_array( $output ) ) {
				$output = $output[0];
			}
			return $output;
		}

	}

	/**
	 * AJAX chunk receiver.
	 * Ajax callback for plupload to handle chunked uploads.
	 * Based on code by Davit Barbakadze
	 * https://gist.github.com/jayarjo/5846636
	 * 
	 * @since 1.2.0
	 */
	public function ajax_chunk_receiver() {

		/** Check that we have an upload and there are no errors. */
		if ( empty( $_FILES ) || $_FILES['async-upload']['error'] ) {
			/** Failed to move uploaded file. */
			die();
		}

		/** Authenticate user. */
		if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
			die();
		}
		check_admin_referer( 'media-form' );

		/** Check and get file chunks. */
		$chunk = isset( $_REQUEST['chunk']) ? intval( $_REQUEST['chunk'] ) : 0;
		$chunks = isset( $_REQUEST['chunks']) ? intval( $_REQUEST['chunks'] ) : 0;

		/** Get file name and path + name. */
		$fileName = isset( $_REQUEST['name'] ) ? $_REQUEST['name'] : $_FILES['async-upload']['name'];
		$filePath = dirname( $_FILES['async-upload']['tmp_name'] ) . '/' . md5( $fileName );


		/** Open temp file. */
		$out = @fopen( "{$filePath}.part", $chunk == 0 ? 'wb' : 'ab' );
		if ( $out ) {

			/** Read binary input stream and append it to temp file. */
			$in = @fopen( $_FILES['async-upload']['tmp_name'], 'rb' );

			if ( $in ) {
				while ( $buff = fread( $in, 4096 ) ) {
					fwrite( $out, $buff );
				}
			} else {
				/** Failed to open input stream. */
				/** Attempt to clean up unfinished output. */
				@fclose( $out );
				@unlink( "{$filePath}.part" );
				die();
			}

			@fclose( $in );
			@fclose( $out );

			@unlink( $_FILES['async-upload']['tmp_name'] );

		} else {
			/** Failed to open output stream. */
			die();
		}

		/** Check if file has finished uploading all parts. */
		if ( ! $chunks || $chunk == $chunks - 1 ) {

			/** Recreate upload in $_FILES global and pass off to WordPress. */
			rename( "{$filePath}.part", $_FILES['async-upload']['tmp_name'] );
			$_FILES['async-upload']['name'] = $fileName;
			$_FILES['async-upload']['size'] = filesize( $_FILES['async-upload']['tmp_name'] );
			$_FILES['async-upload']['type'] = $this->get_mime_content_type( $_FILES['async-upload']['tmp_name'] );
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

			if ( ! isset( $_REQUEST['short'] ) || ! isset( $_REQUEST['type'] ) ) {

				send_nosniff_header();
				nocache_headers();
				wp_ajax_upload_attachment();
				die( '0' );

			} else {

				$post_id = 0;
				if ( isset( $_REQUEST['post_id'] ) ) {
					$post_id = absint( $_REQUEST['post_id'] );
					if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) )
						$post_id = 0;
				}

				$id = media_handle_upload( 'async-upload', $post_id );
				if ( is_wp_error( $id ) ) {
					echo '<div class="error-div error">
					<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __( 'Dismiss' ) . '</a>
					<strong>' . sprintf( __( '&#8220;%s&#8221; has failed to upload.' ), esc_html( $_FILES['async-upload']['name'] ) ) . '</strong><br />' .
					esc_html( $id->get_error_message() ) . '</div>';
					exit;
				}

				if ( isset( $_REQUEST['short'] ) && $_REQUEST['short'] ) {
					// Short form response - attachment ID only.
					echo $id;
				} elseif ( isset( $_REQUEST['type'] ) ) {
					// Long form response - big chunk o html.
					$type = $_REQUEST['type'];

					/**
					 * Filter the returned ID of an uploaded attachment.
					 *
					 * The dynamic portion of the hook name, `$type`, refers to the attachment type,
					 * such as 'image', 'audio', 'video', 'file', etc.
					 *
					 * @since 1.2.0
					 *
					 * @param int $id Uploaded attachment ID.
					 */
					echo apply_filters( "async_upload_{$type}", $id );
				}

			}

		}

		die();

	}

}

/** Instantiate the plugin class. */
$swarmify_upload_accelerator = SwarmifyUploadAccelerator::get_instance();
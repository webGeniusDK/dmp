<?php

class WPBDP_CategoryIconsModule {

    public static function instance() {
        static $instance = null;

        if ( !$instance ) {
            $instance = new self;
        }

        return $instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'wpbdp_modules_init', array( $this, 'init' ) );
        add_action( 'wpbdp_register_settings', array( $this, 'register_settings' ) );

        add_action( 'wpbdp_enqueue_scripts', array( $this, '_enqueue_scripts' ) );
    }

    public function init() {
        if ( !get_option( 'wpbdp[category_images]', null ) ) {
            update_option( 'wpbdp[category_images]', array( 'images' => array(), 'temp' => array() ) );
        }

        add_filter( 'wpbdp_categories_list_attributes', array( $this, '_categories_list_attributes' ), 10, 1 );
        add_filter( 'wpbdp_categories_list_item', array( $this, '_categories_list_item'), 10, 2 );
        add_filter( 'wpbdp_categories_list_item_css', array( $this, '_categories_list_item_css'), 10, 2 );

        if ( wpbdp_get_option( 'categories-use-images' ) )
            add_filter( 'wpbdp_categories_list_anidate_children', '__return_false' );
    }

    public function register_settings( &$api ) {
        wpbdp_register_settings_group( 'category_images', __('Category Images', 'wpbdp-categories' ), 'categories_enhanced' );
        wpbdp_register_setting( array( 'id' => 'categories-use-images', 'name' => __( 'Display the category list using images', 'wpbdp-categories' ), 'type' => 'checkbox', 'default' => true, 'group' => 'category_images' ) );
        wpbdp_register_setting( array( 'id' => 'categories-images-width', 'name' => __( 'Category image width (px)', 'wpbdp-categories' ), 'type' => 'number', 'default' => '80', 'group' => 'category_images' ) );
        wpbdp_register_setting( array( 'id' => 'categories-images-height', 'name' => __( 'Category image height (px)', 'wpbdp-categories' ), 'type' => 'number', 'default' => '80', 'group' => 'category_images' ) );
    }

    public function admin_init() {
        add_action('wpbdp_category_edit_form_fields', array($this, '_category_edit_form_fields'));
        add_action('wp_ajax_wpbdp-category-images-upload', array($this, '_upload_image'));
        add_action('admin_head-edit-tags.php', array($this, '_upload_image_scripts'));
        add_action('admin_head-term.php', array($this, '_upload_image_scripts'));
        add_action('edited_term', array($this, '_category_update'), 10, 3);

        add_filter( 'manage_edit-wpbdp_category_columns', array( $this, '_admin_category_columns' ) );
        add_filter( 'manage_wpbdp_category_custom_column', array( $this, '_admin_custom_category_column' ), 10, 3 );
    }

    /*
     * Category images.
     */
    private function register_temp_image( $term_id, $image_path ) {
        $images = $this->get_temp_images( $term_id );
        $images[] = array( 'file' => _wp_relative_upload_path( $image_path ) );
        $this->set_temp_images( $term_id, $images );
    }

    private function get_temp_images($term_id) {
        $category_images = get_option('wpbdp[category_images]');

        if ($term_id) {
            return isset($category_images['temp'][$term_id]) ? $category_images['temp'][$term_id] : array();
        } else {
            return isset($category_images['temp']['noterm']) ? $category_images['temp']['noterm'] : array();
        }
    }

    private function set_temp_images($term_id, $images=array()) {
        $category_images = get_option('wpbdp[category_images]');

        if ($term_id) {
            $category_images['temp'][$term_id] = $images;
        } else {
            $category_images['temp']['noterm'] = $images;
        }

        update_option('wpbdp[category_images]', $category_images);
    }

    private function get_term_image($term_id) {
        $upload_dir = wp_upload_dir();
        $base_upload_dir = realpath( $upload_dir['basedir'] );

        $category_images = get_option( 'wpbdp[category_images]' );

        if ( ! isset( $category_images['images'][ $term_id ] ) )
            return null;

        $data = $category_images['images'][ $term_id ];

        // A full path may have been accidentally stored.
        // See https://github.com/drodenbaugh/BusinessDirectoryPlugin/issues/2605
        if ( file_exists( $data['file'] ) ) {
            $data['path'] = $data['file'];
            unset( $data['file'] );
        }

        // Update data to new format. Since 3.6.2.
        if ( ! isset( $data['file'] ) ) {
            $real_path = realpath( $data['path'] );
            $relative_path = _wp_relative_upload_path( $real_path );

            if ( $real_path != $relative_path ) {
                $data['file'] = $relative_path;
            } else if ( 0 === strpos( $real_path, $base_upload_dir ) ) {
                $data['file'] = ltrim( str_replace( $base_upload_dir, '', $real_path ), '/' );
            } else {
                // path is no longer valid
                return null;
            }

            unset( $data['url'] );
            unset( $data['path'] );

            $category_images['images'][ $term_id ] = $data;
            update_option('wpbdp[category_images]', $category_images);
        }

        $data['url'] = trailingslashit( $upload_dir['baseurl'] ) . $data['file'];
        $data['path'] = trailingslashit( $base_upload_dir ) . $data['file'];

        return $data;
    }

    private function set_term_image($term_id, $image_path, $do_cleanup=true) {
        $upload_dir = wp_upload_dir();
        $image_file = _wp_relative_upload_path( $image_path );

        if ( $do_cleanup ) {
            $temp_images = $this->get_temp_images( $term_id );

            if ( $current_image = $this->get_term_image( $term_id ) ) {
                if ( $current_image['file'] != $image_file )
                    $temp_images[] = $current_image;
            }

            foreach ( $temp_images as $img ) {
                if ( $img['file'] != $image_file ) {
                    $path = trailingslashit( $upload_dir['basedir'] ) . $img['file'];

                    if ( $path && file_exists( $path ) )
                        @unlink( realpath( $path ) );
                }
            }

            $this->set_temp_images( $term_id, array() );
        }

        $category_images = get_option('wpbdp[category_images]');

        if ( $image_path && file_exists( $image_path ) ) {
            $category_images['images'][$term_id] = array( 'file' => $image_file );
        } else {
            unset( $category_images['images'][ $term_id ] );
        }

        update_option('wpbdp[category_images]', $category_images);
    }

    public function _admin_category_columns($columns_) {
        $columns = array();

        foreach (array_keys($columns_) as $key) {
            $columns[$key] = $columns_[$key];

            if ($key == 'name')
                $columns['term-image'] = _x( 'Image', 'wpbdp-customizations', 'wpbdp-categories' );
        }

        return $columns;
    }

    public function _admin_custom_category_column($out, $column_name, $term_id) {
        if ($column_name != 'term-image')
            return $out;

        $term = get_term($term_id, WPBDP_CATEGORY_TAX);

        $html  = '';
        if ($term_image = $this->get_term_image($term_id)) {
            $html .= sprintf('<img src="%s" class="wpbdp-category-image-admin-thumbnail" />', $term_image['url']);
        } else {
            $html .= '-';
        }

        $html .= '<div class="row-actions">';
        $html .= '<span class="edit">';
        $html .= edit_term_link($term_image ? _x( 'Change Image', 'wpbdp-customizations', 'wpbdp-categories' ) : __('Add Image', 'wpbdp-categories'), '', '', $term, false);
        $html .= '</span>';
        $html .= '</div>';

        return $html;
    }


    public function _upload_image_scripts() {
        $scripts = <<<EOT
            function wpbdp_category_images_done(upload) {
                jQuery('#TB_closeWindowButton').click();
                jQuery('#category-image-input-path').val(upload.file);
                jQuery('#category-image-input-url').val(upload.url);
                jQuery('#category-image-preview .image-preview').html('<img src="' + upload.url + '" />').show();
                jQuery('#category-image-preview a.delete-image').show();
            }

            function wpbdp_category_images_delete() {
                jQuery('#category-image-input-path').val('');
                jQuery('#category-image-input-url').val('');
                jQuery('#category-image-preview .image-preview').html('');
                jQuery('#category-image-preview a.delete-image').hide();

                return false;
            }
EOT;

        echo '<style type="text/css">';
        echo '#category-image-preview img { max-width: 120px; max-height: 120px; border: solid 1px #444; }';
        echo '#category-image-preview { margin-bottom: 10px; }';
        echo '#category-image-preview a.delete-image { display: block; color: red; }';
        echo 'img.wpbdp-category-image-admin-thumbnail { max-width: 50px; }';
        echo '</style>';

        echo '<script type="text/javascript">';
        echo $scripts;
        echo '</script>';
    }

    public function _upload_image() {
        echo '<script type="text/javascript">';
        echo 'parent.jQuery("#TB_window, #TB_iframeContent").width(350).height(150)';
        echo '</script>';

        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            if ($upload = wp_handle_upload($_FILES['image_upload'], array('test_form' => FALSE))) {
                if (!isset($upload['error'])) {
                    $width = intval(wpbdp_get_option('categories-images-width'));
                    $height = intval(wpbdp_get_option('categories-images-height'));

                    // keep images for this term id registered so we dont leave uploaded images that aren't in use
                    $this->register_temp_image( $_GET['term_id'], $upload['file'] );

                    // TODO: resize images (image_resize())

                    echo '<script type="text/javascript">';
                    echo sprintf('parent.wpbdp_category_images_done(%s);', json_encode($upload));
                    echo '</script>';
                } else {
                    print $upload['error'];
                }
            }
        }

        echo '<div class="wrap">';
        echo '<form action="" method="POST" enctype="multipart/form-data">';
        echo '<strong>' . _x( 'Upload Image', 'wpbdp-customizations', 'wpbdp-categories' ) . '</strong><br />';
        echo '<input type="file" name="image_upload" />';
        echo sprintf('<input type="submit" value="%s" class="button" />', __('Upload', 'wpbdp-categories'));
        echo '</form>';
        echo '</div>';
        exit;
    }

    public function _category_edit_form_fields($term) {
        echo '<tr class="form-field">';
        echo '<th scope="row" valign="top">';
        echo '<label for="category-image">';
        echo _x( 'Category Image', 'wpbdp-customizations', 'wpbdp-categories' );
        echo '</label>';
        echo '</th>';
        echo '<td>';

        echo '<div id="category-image-preview">';
        echo '<div class="image-preview">';
        if ($category_image = $this->get_term_image($term->term_id)) {
            echo sprintf('<img src="%s" />', $category_image['url']);
        }
        echo '</div>';
        echo sprintf('<a href="#" onclick="wpbdp_category_images_delete();" class="delete-image" style="display: %s;">%s</a>', $category_image ? 'block' : 'none', __('Delete', 'wpbdp-categories'));
        echo '</div>';

        echo sprintf('<input id="category-image-input-path" type="hidden" name="category_image[path]" value="%s" />', $category_image ? $category_image['path'] : '');
        echo sprintf('<input id="category-image-input-url" type="hidden" name="category_image[url]" value="%s" />', $category_image ? $category_image['url']: '');

        echo sprintf('<a href="%s" class="thickbox button button-primary">%s</a>',
                     add_query_arg(array('action' => 'wpbdp-category-images-upload',
                                         'term_id' => $term->term_id,
                                         'TB_iframe' => 1),
                                   admin_url('admin-ajax.php')),
                     __('Upload Image', 'wpbdp-categories')  );
        echo '</td>';
        echo '</tr>';
    }

    public function _category_update($term_id, $tt_id, $taxonomy) {
        if (isset($_POST['category_image'])) {
            $_POST = stripslashes_deep( $_POST );

            $path = wpbdp_getv($_POST['category_image'], 'path', null);
            $url = wpbdp_getv($_POST['category_image'], 'url', null);

            if (!empty($path) && !empty($url)) {
                $this->set_term_image( $term_id, $path, true );
            } else {
                $this->set_term_image( $term_id, null, true );
            }
        }
    }

    public function _categories_list_attributes( $attributes ) {
        $attributes['class'] .= 'wpbdp-categories-' . wpbdp_get_option( 'categories-columns' ) . '-columns-no-bp';

        if ( wpbdp_get_option( 'categories-use-images' ) ) {
            $attributes['class'] .= ' with-images';
        } else {
            $attributes['class'] .= ' without-images';
        }

        $attributes['data-breakpoints-class-prefix'] = 'wpbdp-categories-' . wpbdp_get_option( 'categories-columns' ) . '-columns';

        return $attributes;
    }

    public function _categories_list_item_css( $css, $term ) {
        if ( !wpbdp_get_option('categories-use-images') )
            return $css;

        $image = $this->get_term_image($term->term_id);

        if ( $image ) {
            return $css . ' with-image ';
        }

        return $css . ' no-image ';
    }

    public function _categories_list_item($item_html, $term) {
        if ( !wpbdp_get_option('categories-use-images') )
            return $item_html;

        $image = $this->get_term_image($term->term_id);

        $image_html = sprintf( '<a href="%1$s" class="wpbdp-category-icon-link"><img src="%2$s" alt="%4$s" class="category-image" style="%3$s" /></a>',
            get_term_link( $term, WPBDP_CATEGORY_TAX ),
            $image ? $image['url'] : plugins_url( '/resources/placeholder.png', __FILE__ ),
            sprintf(
                'max-width: %dpx; max-height: %dpx;',
                 wpbdp_get_option( 'categories-images-width' ),
                 wpbdp_get_option( 'categories-images-height' )
            ),
            $term->name
        );

        $item_html = $image_html . $item_html;
        return $item_html;
    }

    public function _enqueue_scripts() {
        wp_enqueue_style(
            'wpbdp-category-icons-module',
            plugins_url( '/resources/styles.min.css', __FILE__ ),
            array( 'wpbdp-base-css' ),
            WPBDP_CategoriesModule::VERSION
        );
    }
}

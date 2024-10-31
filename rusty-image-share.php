<?php
/**
* Plugin Name: Rusty Social Image Share
* Plugin URI: https://widgetmedia.co/wp/rusty-image-share/
* Description: Keep your original Featured Image proportions whilst sharing to Facebook.
* Version: 1.0
* Author: RustyBadRobot
* Author URI: https://widgetmedia.co/
*
*/

if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

if ( !class_exists( 'RustyFbShare' ) ) {

    class RustyFbShare {

      var $menu_id;

 	    public $capability;


 	    public function __construct() {

        load_plugin_textdomain('rusty-fb-share', false, '/rusty-fb-share/localization');

 		    add_action('admin_menu',                              array($this, 'add_admin_menu'));
 		    add_action('admin_enqueue_scripts',                   array($this, 'admin_enqueues'));
 		    add_action('wp_head',                                 array($this, 'rusty_facebook_open_graph'), 0 );

 		    add_filter('intermediate_image_sizes_advanced',       array($this, 'rusty_fb_share_upload'), 10, 2 );
 		    add_filter( 'wp_generate_attachment_metadata',        array($this, 'rusty_fb_share_regenerate'), 10, 2 );

 	    }

 	    /**
 	    * Register the management page
 	    *
 	    * @access public
 	    * @since 1.0
 	    */
 	    public function add_admin_menu() {
 	        $this->menu_id = add_management_page(__('Rusty Image Share', 'rusty-fb-share' ), __( 'Rusty Image Share', 'rusty-fb-share' ), 'manage_options', 'rusty-fb-share', array($this, 'rusty_share_interface') );
 	    }

 	    public function admin_enqueues($hook_suffix) {

 	        if ($hook_suffix != $this->menu_id) {
 	            return;
 	        }

 	        wp_enqueue_style('rusty-custom-style', plugins_url('style.css', __FILE__), array(), '1.0');
 	        wp_enqueue_style( 'wp-color-picker' );
 	        wp_enqueue_script( 'cpa_custom_js', plugins_url( 'jquery.custom.js', __FILE__ ), array( 'jquery', 'wp-color-picker' ), '', true  );

 	    }


 	    /**
 	    * The user interface
 	    *
 	    * @access public
 	    * @since 1.0
 	    */
 	    public function rusty_share_interface() {

 		    global $wpdb;
 		    ?>

 		    <div id="message" class="updated fade" style="display:none"></div>

 		    <div class="wrap rusty-wrap">

 		        <?php

 		        // If the button was clicked
 		        if ( !empty($_POST['rusty-fb-share'] ) ) {

 		            // Form nonce check
 		            check_admin_referer('rusty-fb-share');

 		            if (!empty($_POST["rusty_share_settings"]['background'])) {

 		                $postBg = sanitize_hex_color( $_POST["rusty_share_settings"]['background'] );

                    if ( $postBg ) {
 		                    update_option( 'rusty_fb_share_background', $postBg, null );
 		                }

 		            }

 		        }

 		        ?>

 		        <h1><?php _e('Rusty Facebook Image Share', 'rusty-fb-share'); ?></h1>

 		        <div class="card">

 		            <h2 class="title"><?php _e('Background Options', 'rusty-fb-share'); ?></h2>

 		            <p><span class="description"><?php printf( __( 'Select the background for your Facebook share image.', 'rusty-fb-share' ) ); ?></span></p>

 		            <form action="<?php echo admin_url( 'tools.php?page=rusty-fb-share' ); ?>" method="post">

 		                <?php wp_nonce_field('rusty-fb-share') ?>

 		                <?php $background = get_option( 'rusty_fb_share_background', '#ffffff' ); ?>

 		                <input type="text" name="rusty_share_settings[background]" value="<?php echo esc_attr( $background ); ?>" data-default-color="#ffffff" class="rusty-color-picker" />

 		                <input type="submit" name="rusty-fb-share" id="rusty-fb-share" class="button-primary" value="<?php _e( 'Save Changes', 'rusty-fb-share' ); ?>" aria-label="Save Changes"/>

 		            </form>

 		        </div>

		    </div>

 		    <?php
 	    }

 	    public function rusty_fb_share_img($filePath, $savePath, $tn_w, $tn_h, $type){

            try{
                $info = getimagesize($filePath);

            } catch (Exception $e){

                return;

            }

            switch ($type) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($filePath);
                    break;
                case 'image/gif':
                    $source = imagecreatefromgif($filePath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($filePath);
                    break;
                default:
                    return;
            }

            $src_w = imagesx($source);
            $src_h = imagesy($source);

            $x_ratio = $tn_w / $src_w;
            $y_ratio = $tn_h / $src_h;

            if (($src_w <= $tn_w) && ($src_h <= $tn_h)) {
                $new_w = $src_w;
                $new_h = $src_h;
            } elseif (($x_ratio * $src_h) < $tn_h) {
                $new_h = ceil($x_ratio * $src_h);
                $new_w = $tn_w;
            } else {
                $new_w = ceil($y_ratio * $src_w);
                $new_h = $tn_h;
            }

            $newpic = imagecreatetruecolor(round($new_w), round($new_h));
            imagecopyresampled($newpic, $source, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
            $final = imagecreatetruecolor($tn_w, $tn_h);

            global $wpdb;

            $hex = get_option( 'rusty_fb_share_background', '#ffffff' );
            $hex_color = str_replace('#', '', $hex);
            $split_hex_color = str_split( $hex_color, 2 );
            $r = hexdec( $split_hex_color[0] );
            $g = hexdec( $split_hex_color[1] );
            $b = hexdec( $split_hex_color[2] );

            $backgroundColor = imagecolorallocate($final, $r, $g, $b);

            imagefill($final, 0, 0, $backgroundColor);

            imagecopy($final, $newpic, (($tn_w - $new_w)/ 2), (($tn_h - $new_h) / 2), 0, 0, $new_w, $new_h);

            if (imagejpeg($final, $savePath, 82)) {
                return;
            }

            return;

 	    }

        public function rusty_fb_share_upload( $sizes, $metadata  ) {

            if (! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {

                $info = pathinfo( $metadata['file'] );

                $dir = wp_upload_dir();

                $ext  = empty( $info['extension'] ) ? '' : '.' . $info['extension'];

                $image = $dir["basedir"] . "/" . $info["dirname"] . "/" . $info["basename"];

                $name = basename( $metadata['file'], $ext );
                $filetype = wp_check_filetype($image);
                $type = $filetype["type"];

                if ($metadata['width'] >= 1200 && $metadata['height'] >= 630) {
                    $width = 1200;
                    $height = 630;
                } elseif ($metadata['width'] >= 600 && $metadata['height'] >= 315) {
                    $width = 600;
                    $height = 315;
                } else {
                    $width = 200;
                    $height = 200;
                }

                $dest = $dir["basedir"] . "/" . $info["dirname"] . "/" . $name . "-" . $width . "x" . $height . $ext;

                $sizes['rusty-fb-share'] = array(
                    'width'  => $width,
                    'height' => $height,
                    'crop'   => true
                );

            }

            return $sizes;

        }


        public function rusty_fb_share_regenerate( $metadata, $attachment_id  ) {

            $info = pathinfo( $metadata['file'] );
            $dir = wp_upload_dir();
            $ext  = empty( $info['extension'] ) ? '' : '.' . $info['extension'];
            $image = $dir["basedir"] . "/" . $info["dirname"] . "/" . $info["basename"];
            $name = basename( $metadata['file'], $ext );
            $filetype = wp_check_filetype($image);
            $type = $filetype["type"];

            if ($metadata['width'] >= 1200 && $metadata['height'] >= 630) {
                $width = 1200;
                $height = 630;
            } elseif ($metadata['width'] >= 600 && $metadata['height'] >= 315) {
                $width = 600;
                $height = 315;
            } else {
                $width = 200;
                $height = 200;
            }

            $dest = $dir["basedir"] . "/" . $info["dirname"] . "/" . $name . "-" . $width . "x" . $height . $ext;

            $this->rusty_fb_share_img($image, $dest, $width, $height, $type);

            return $metadata;

        }

        /*
        to do check seo plugins installed and hook into them

        private function isYoastActive(){
            $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
            var_dump($active_plugins);
            foreach($active_plugins as $plugin){
                if(strpos($plugin, 'wp-seo')){
                    return true;
                }
            }
            return false;
        }
        */

        public function rusty_facebook_open_graph() {
            global $post, $_wp_additional_image_sizes;

            if ( !is_singular() ) {
                return;
 	        }

 	        if( has_post_thumbnail( $post->ID ) ) {

 	            $thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'rusty-fb-share' );

 	            echo '<meta property="og:image" content="' . esc_attr( $thumbnail_src[0] ) . '" />';
 	            echo '<meta property="og:image:width" content="' . esc_attr( $thumbnail_src[1] ) . '" />';
 	            echo '<meta property="og:image:height" content="' . esc_attr( $thumbnail_src[2] ) . '" />';

 	        }

        }

    }

    //Start
    $RustyFbShare = new RustyFbShare();

}

?>

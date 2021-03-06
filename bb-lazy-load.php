<?php
/*
Plugin Name: Beaver Builder - Lazy Load
Description: Lazy loads background images set using Beaver Builder. Also will serve .webp if setting is selected.
Author: Clay Harmon
Version: 1.1 
*/

require __DIR__.'/vendor/plugin-update-checker/plugin-update-checker.php';
$bbll_update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/clayharmon/bb-lazy-load/',
	__FILE__,
	'bbll'
);
$bbll_update_checker->getVcsApi()->enableReleaseAssets();


function bbll_load_scripts() {
  if ( class_exists( 'FLBuilder' ) ) {

    if(!isset($_GET['fl_builder']) && !is_admin() && !wp_doing_ajax()){
      wp_enqueue_script( 'bbll-intersection-observer', 'https://cdn.jsdelivr.net/npm/intersection-observer@0.5.1/intersection-observer.min.js', array(), '0.5.1', true );
      wp_enqueue_script( 'bbll-lazyload', 'https://cdn.jsdelivr.net/npm/vanilla-lazyload@17.3/dist/lazyload.min.js', array(), '11.0.6', true );
      
      wp_enqueue_script( 'bbll-custom', plugins_url( '/assets/scripts.min.js', __FILE__ ), array('jquery'), '0.3', true);
      $bbll_bg_store = get_option('bbll_bg_store');
      wp_localize_script( 'bbll-custom', 'bbll_bg_obj', $bbll_bg_store );

      wp_enqueue_style( 'bbll-styles', plugins_url( '/assets/styles.min.css', __FILE__ ),'', '0.1');
    }
  }
}
add_action( 'wp_enqueue_scripts', 'bbll_load_scripts', 20);

function bbll_load_filters() {
  if ( class_exists( 'FLBuilder' ) ) {

    if(!isset($_GET['fl_builder']) && !is_admin() && !wp_doing_ajax()){
      add_filter( 'fl_builder_render_css', 'bbll_builder_render_css', 10, 3 );
    }
  }
}
add_action( 'init', 'bbll_load_filters');

function bbll_admin_nonce_notice() {
  if(isset($_GET['nonce_verify']) && $_GET['nonce_verify'] === 'false'){
    echo '<div class="error notice"><p>Sorry, your nonce did not verify. Please try again.</p></div>';
  }
}
add_action( 'admin_notices', 'bbll_admin_nonce_notice' );

add_action( 'admin_action_bbll_admin_form', 'bbll_admin_form_action' );
function bbll_admin_form_action(){
  if ( !isset( $_POST['bbll_admin_verify'] ) || !wp_verify_nonce( $_POST['bbll_admin_verify'], 'bbll_admin_update_settings' ) ) {
    wp_redirect( $_SERVER['HTTP_REFERER'] . '&nonce_verify=false');
    exit();
  }
  $bbll_options = [];

  if( isset( $_POST['bbll_option_webp'] ) ){
    $value = esc_sql( $_POST['bbll_option_webp'] );
    $bbll_options['webp'] = $value;
  }

  if( isset( $_POST['bbll_option_all_bb_bg_images'] ) ){
    $value = esc_sql( $_POST['bbll_option_all_bb_bg_images'] );
    $bbll_options['all_bb_bg_images'] = $value;
  }

  update_option('bbll_store', $bbll_options);
  FLBuilderModel::delete_asset_cache_for_all_posts();
  wp_redirect( $_SERVER['HTTP_REFERER'] . '&nonce_verify=true' );
  exit();
}

function bbll_add_settings_page(){
  add_options_page(
    'BB Lazy Load',
    'BB Lazy Load',
    'manage_options',
    'bbll',
    'bbll_settings_html'
  );
}
add_action( 'admin_menu', 'bbll_add_settings_page' );
function bbll_checkbox_html($options_array, $option_name, $label){
  $bbll_options = $options_array;
  $ischecked = (isset($bbll_options[$option_name]) && $bbll_options[$option_name]) ? 'checked' : '';

  $output = '';
  $output .= '<div style="padding:5px 0;">';
  $output .= '<label for="bbll_option_'.$option_name.'"><input name="bbll_option_'.$option_name.'" type="checkbox" id="bbll_option_'.$option_name.'" value="1" '.$ischecked.' >'.$label.'</label>';
  $output .= '</div>';
  return $output;
}
function bbll_settings_html(){
  $bbll_options = get_option('bbll_store');
  ?>
  <div class="wrap">
    <h1>Beaver Builder Lazy Load</h1>
    <form style="padding:10px 0;" method="POST" action="<?php echo admin_url( 'admin.php' ); ?>">
      <?php echo bbll_checkbox_html($bbll_options, 'webp', 'Enable .webp? <em>exampleimage.jpg.webp</em>'); ?>

      <?php echo bbll_checkbox_html($bbll_options, 'all_bb_bg_images', 'Enable lazy load for all Beaver Builder background images?'); ?>

      <?php wp_nonce_field( 'bbll_admin_update_settings', 'bbll_admin_verify' ); ?>
      
      <input type="hidden" name="action" value="bbll_admin_form" />
      <input style="margin:10px 0;" type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />
    </form>
  </div>
  <?php
}

function bbll_builder_render_css( $css, $nodes, $global_settings ) {
  static $has_run = 0;
  $has_run = $has_run + 1; 

  $bbll_options = get_option('bbll_store');

  if(!isset($bbll_options['all_bb_bg_images']) || !$bbll_options['all_bb_bg_images']){
    update_option( 'bbll_bg_store', []);
    return $css;
  }

  $bg_matches = array();
  $bg_store = array();
  $bg_medias = array();
  if(preg_match_all('/(?:@media\((.*?)\) {\n)?(?:[ \t]*)?(.*?) {\n.*?(?:\n.*?)?background(?:-image)?:[ ]?url\([ ]?[\'"]?(.*?)[\'"]?\)/', $css, $bg_matches)) {
    for($i=0;$i<count($bg_matches[3]);$i++){
      $media = ($bg_matches[1][$i] === '') ? '' : '('.$bg_matches[1][$i].')';
      $selector = $bg_matches[2][$i];
      $image = $bg_matches[3][$i];
      if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
        if(isset($bbll_options['webp']) && $bbll_options['webp']){
          $imageArr = explode('.', $image);
          $extension = array_pop( $imageArr );
          if($extension === 'jpg' || $extension === 'png') {
            $image .= '.webp';
          } 
        }
      }

      if(!in_array($media, $bg_medias)) {
        array_push($bg_medias, $media);
      }

      if(array_key_exists($selector, $bg_store)) {
        array_push($bg_store[$selector], [
          "media" => $media,
          "image" => $image
        ]);
      } else {
        $bg_store[$selector] = [[
          "media" => $media,
          "image" => $image
        ]];
      }
      

      $css = preg_replace('/\n.*?background(?:-image)?:[ ]?url\([ ]?[\'"]?(.*?)[\'"]?\)/', "", $css);
    }
  };
  if($has_run === 1){
    update_option( 'bbll_bg_store', [
      'data' => $bg_store,
      'medias' => $bg_medias
     ]);
  }
  return $css;
}


register_deactivation_hook( __FILE__, 'bbll_deactivation' );

function bbll_deactivation(){
  FLBuilderModel::delete_asset_cache_for_all_posts();
  delete_option('bbll_bg_store');
}
<?php
/*
Plugin Name: Beaver Builder - Lazy Load
Description: Lazy loads background images set using Beaver Builder. Also will serve .webp if setting is selected.
Author: Clay Harmon
Version: 0.4.8
*/

require __DIR__.'/vendor/plugin-update-checker/plugin-update-checker.php';
$bbll_update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/clayharmon/bb-lazy-load/',
	__FILE__,
	'bbll'
);
$bbll_update_checker->getVcsApi()->enableReleaseAssets();


function bbll_load_module_add_filters() {
  $bbll_active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

  if ( class_exists( 'FLBuilder' ) ) {

    if(!isset($_GET['fl_builder']) && !is_admin() && !wp_doing_ajax()){
      add_filter( 'fl_builder_row_attributes', 'bbll_builder_render_attrs_row', 10, 2 );
      add_filter( 'fl_builder_column_attributes', 'bbll_builder_render_attrs_col', 10, 2 );
      if ( in_array( 'wp-rocket/wp-rocket.php', $bbll_active_plugins) ) {
        add_filter( 'rocket_buffer', 'bbll_builder_render_content', 10, 1);
      } else if ( in_array( 'litespeed-cache/litespeed-cache.php', $bbll_active_plugins) ) {
        add_filter( 'litespeed_buffer_before', 'bbll_builder_render_content', 0, 1); 
      } else {
        add_filter('bbll_final_output', 'bbll_builder_render_content', 0, 1);
      }
      add_filter( 'fl_builder_render_css', 'bbll_builder_render_css', 10, 3 );
      wp_enqueue_script( 'bbll-intersection-observer', 'https://cdn.jsdelivr.net/npm/intersection-observer@0.5.1/intersection-observer.min.js', array(), '0.5.1', true );
      wp_enqueue_script( 'bbll-lazyload', 'https://cdn.jsdelivr.net/npm/vanilla-lazyload@11.0.6/dist/lazyload.min.js', array(), '11.0.6', true );
      
      wp_enqueue_script( 'bbll-custom', plugins_url( '/assets/scripts.min.js', __FILE__ ), array('jquery'), '0.1', true);
      $bbll_bg_store = get_option('bbll_bg_store');
      wp_localize_script( 'bbll-custom', 'bbll_bg_obj', $bbll_bg_store );

      wp_enqueue_style( 'bbll-styles', plugins_url( '/assets/styles.min.css', __FILE__ ),'', '0.1');
    }
  }
}
add_action( 'init', 'bbll_load_module_add_filters' );

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

  if( isset( $_POST['bbll_option_column_images'] ) ){
    $value = esc_sql( $_POST['bbll_option_column_images'] );
    $bbll_options['column_images'] = $value;
  }

  if( isset( $_POST['bbll_option_row_images'] ) ){
    $value = esc_sql( $_POST['bbll_option_row_images'] );
    $bbll_options['row_images'] = $value;
  }

  if( isset( $_POST['bbll_option_row_parallax'] ) ){
    $value = esc_sql( $_POST['bbll_option_row_parallax'] );
    $bbll_options['row_parallax'] = $value;
  }

  if( isset( $_POST['bbll_option_image_html'] ) ){
    $value = esc_sql( $_POST['bbll_option_image_html'] );
    $bbll_options['image_html'] = $value;
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

      <?php echo bbll_checkbox_html($bbll_options, 'column_images', 'Enable lazy loading for column background images?'); ?>

      <?php echo bbll_checkbox_html($bbll_options, 'row_images', 'Enable lazy loading for row background images?'); ?>

      <?php echo bbll_checkbox_html($bbll_options, 'row_parallax', 'Enable lazy loading for row parallax images?'); ?>

      <?php echo bbll_checkbox_html($bbll_options, 'image_html', 'Enable lazy load for all Beaver Builder img tags? <strong>(Experimental)</strong>'); ?>

      <?php wp_nonce_field( 'bbll_admin_update_settings', 'bbll_admin_verify' ); ?>
      
      <input type="hidden" name="action" value="bbll_admin_form" />
      <input style="margin:10px 0;" type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />
    </form>
  </div>
  <?php
}

// We need to grab the final HTML output.
// https://stackoverflow.com/questions/772510/wordpress-filter-to-modify-final-html-output

$bbll_active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
if(
  !in_array( 'litespeed-cache/litespeed-cache.php', $bbll_active_plugins) && 
  !in_array( 'wp-rocket/wp-rocket.php', $bbll_active_plugins)
  ){
  add_action( 'init', 'bbll_process_start' );
  add_action( 'shutdown', 'bbll_process_end', 0 );
}

function bbll_process_start() { ob_start(); }
function bbll_process_end() {
    $final = '';
    
    $levels = ob_get_level();

    for ($i = 0; $i < $levels; $i++) {
        $final .= ob_get_clean();
    }

    // Apply any filters to the final output
    echo apply_filters('bbll_final_output', $final);
}

function bbll_builder_render_content($content){

  if(empty($content)){
    return $content;
  }
  // DOMDocument messes up scripts.. We need to remove them and re-add them.
  // https://stackoverflow.com/questions/33426788/domdocument-removing-closing-tag-within-script-tags
  $pattern = "/<script[\s\S]*?>[\s\S]*?<\/script>/";
  preg_match_all($pattern, $content, $matches);
  $simple = array();
  $complete = array();
  $matches = array_unique( $matches[0] );

  foreach ( $matches as $match ) {
    $id = uniqid('script_');
    $uniqueScript = "<script id=\"$id\"></script>";
    $simple[] = $uniqueScript;
    $complete[] = $match;
  }
  $content = str_replace($complete, $simple, $content);
  
  $doc = new DOMDocument();
  // We're dealing with non-well-formed HTML
  // Solution: https://stackoverflow.com/questions/1148928/disable-warnings-when-loading-non-well-formed-html-by-domdocument-php
  $libxml_error_state = libxml_use_internal_errors(true);

  // Convert to UTF-8
  $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

  $doc->loadHTML($content);

  libxml_clear_errors();
  libxml_use_internal_errors($libxml_error_state);

  $xpath = new DomXpath($doc);
  foreach ($xpath->query('//@data-bbll') as $el) {
    $url = $el->nodeValue;
    $child = $el->ownerElement->childNodes->item(1);
    $class = $child->getAttribute('class');
    $child->setAttribute('class', $class . ' bbll');
    $child->setAttribute('data-bg', $url);
  }

  $bbll_options = get_option('bbll_store');
  if(isset($bbll_options['image_html']) && $bbll_options['image_html'] && !isset($_GET['fl_builder'])){
    $images = $doc->getElementsByTagName('img');
    foreach($images as $img){
      $src = $img->getAttribute('src');
      $srcset = $img->getAttribute('srcset');
      $sizes = $img->getAttribute('sizes');
      $class = $img->getAttribute('class');

      if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
        if(isset($bbll_options['webp']) && $bbll_options['webp']){
          $src .= '.webp';

          if(!empty($srcset)){
            $old_srcset = explode(' ', $srcset);
            $new_srcset = array();
            for($i = 0; $i < count($old_srcset); $i++){
              if($i % 2 === 0){
                $old_srcset[$i] .= '.webp';
              }
              array_push($new_srcset, $old_srcset[$i]);
            }
            $srcset = implode(' ', $new_srcset);
          }
        }
      }

      $img->removeAttribute('src');
      $img->removeAttribute('srcset');
      $img->removeAttribute('sizes');

      $img->setAttribute('class', $class . ' bbll');
      $img->setAttribute('data-src', $src);
      $img->setAttribute('data-srcset', $srcset);
      $img->setAttribute('data-sizes', $sizes);
    }
  }
  $content = $doc->saveHTML();
  $content = str_replace($simple, $complete, $content);
  return $content;
}

function bbll_builder_render_attrs_row( $attrs, $container ) {
  $type = $container->settings->bg_type;
  if($type === 'photo'){
    if(isset($container->settings->bg_image_src)){
      $image = $container->settings->bg_image_src;
    } else {
      return $attrs;
    }
  } else if($type === 'parallax'){
    if(isset($container->settings->bg_parallax_image_src)){
      $image = $container->settings->bg_parallax_image_src;
    } else {
      return $attrs;
    }
  } else {
    return $attrs;
  }

  $bbll_options = get_option('bbll_store');

  if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
    if(isset($bbll_options['webp']) && $bbll_options['webp']){
      $imageArr = explode('.', $image);
      $extension = array_pop( $imageArr );
      if($extension === 'jpg' || $extension === 'png') {
        $image .= '.webp';
      } 
    }
  }

  if($container->settings->bg_type === 'photo' && isset($bbll_options['row_images']) && $bbll_options['row_images']){
    $attrs['data-bbll'] = 'url('.$image.')';
  }

  if($container->settings->bg_type === 'parallax' && isset($bbll_options['row_parallax']) && $bbll_options['row_parallax']){
    $attrs['data-parallax-image'] = '';
    $attrs['data-bbll'] = 'url('.$image.')';
  }
  return $attrs;
}

function bbll_builder_render_attrs_col( $attrs, $container ) {
  if(!isset($container->settings->bg_image_src)) return $attrs;

  $bbll_options = get_option('bbll_store');
  $image = $container->settings->bg_image_src;

  if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
    if(isset($bbll_options['webp']) && $bbll_options['webp']){
      $imageArr= explode('.', $image);
      $extension = array_pop( $imageArr );
      if($extension === 'jpg' || $extension === 'png') {
        $image .= '.webp';
      } 
    }
  }

  if($container->settings->bg_type === 'photo' && isset($bbll_options['column_images']) && $bbll_options['column_images']){
    $attrs['data-bbll'] = 'url('.$image.')';
  }
  return $attrs;
}

function bbll_builder_render_css( $css, $nodes, $global_settings ) {
  static $has_run = 0;
  $has_run = $has_run + 1; 

  $bbll_options = get_option('bbll_store');

  $bg_matches = array();
  $bg_store = array();
  if(preg_match_all('/(.*?) {\n.*?background(?:-image)?:[ ]?url\([ ]?[\'"]?(.*?)[\'"]?\)/', $css, $bg_matches)) {
    for($i=0;$i<count($bg_matches[2]);$i++){
      $image = $bg_matches[2][$i];
      if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
        if(isset($bbll_options['webp']) && $bbll_options['webp']){
          $imageArr = explode('.', $image);
          $extension = array_pop( $imageArr );
          if($extension === 'jpg' || $extension === 'png') {
            $image .= '.webp';
          } 
        }
      }
      array_push($bg_store, [
        "selector" => $bg_matches[1][$i],
        "image" => $image
      ]);
      $css = preg_replace('/\n.*?background(?:-image)?:[ ]?url\([ ]?[\'"]?(.*?)[\'"]?\)/', "", $css);
    }
  };
  if($has_run === 1){
    update_option( 'bbll_bg_store', $bg_store);
  }
  return $css;
}


register_deactivation_hook( __FILE__, 'bbll_deactivation' );

function bbll_deactivation(){
  FLBuilderModel::delete_asset_cache_for_all_posts();
}
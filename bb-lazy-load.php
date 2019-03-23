<?php
/*
Plugin Name: Beaver Builder - Lazy Load
Description: Lazy loads background images set using Beaver Builder. Also will serve .webp if setting is selected.
Author: Clay Harmon
Version: 0.3.1
*/

require 'plugin-update-checker/plugin-update-checker.php';
$bbll_update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/clayharmon/bb-lazy-load/',
	__FILE__,
	'bbll'
);
$bbll_update_checker->getVcsApi()->enableReleaseAssets();

function bbll_load_module_add_filters() {
  if ( class_exists( 'FLBuilder' ) ) {
    add_filter( 'fl_builder_row_attributes', 'bbll_builder_render_attrs_row', 10, 2 );
    add_filter( 'fl_builder_column_attributes', 'bbll_builder_render_attrs_col', 10, 2 );

    $bbll_options = get_option('bbll_store');
    if(isset($bbll_options['image_html']) && $bbll_options['image_html'] && !isset($_GET['fl_builder'])){
      add_filter( 'fl_builder_render_module_content', 'bbll_builder_render_module_html', 10, 2);
    }
    if(!isset($_GET['fl_builder'])){
      add_filter( 'fl_builder_render_css', 'bbll_builder_render_css', 10, 3 );
      add_action( 'wp_footer', 'bbll_lazyload_bgs' );
      add_action( 'wp_head', 'bbll_custom_styles' );
    }
  }
}
add_action( 'init', 'bbll_load_module_add_filters' );

add_action( 'admin_action_bbll_admin_form', 'bbll_admin_form_action' );
function bbll_admin_form_action(){
    //var_dump($_POST);
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
    wp_redirect( $_SERVER['HTTP_REFERER'] );
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
      
      <input type="hidden" name="action" value="bbll_admin_form" />
      <input style="margin:10px 0;" type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />
    </form>
  </div>
  <?php
}

function bbll_builder_render_attrs_row( $attrs, $container ) {
  if(!isset($container->settings->bg_image_src)) return $attrs;

  $bbll_options = get_option('bbll_store');
  $image = $container->settings->bg_image_src;

  if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
    if(isset($bbll_options['webp']) && $bbll_options['webp']){
      $image .= '.webp';
    }
  }

  if($container->settings->bg_type === 'photo' && isset($bbll_options['row_images']) && $bbll_options['row_images']){
    $attrs['data-bb-lazy-load-bgurl'] = $image;
  }

  if($container->settings->bg_type === 'parallax' && isset($bbll_options['row_parallax']) && $bbll_options['row_parallax']){
    $attrs['data-parallax-image'] = '';
    $attrs['data-bb-lazy-load-bgurl'] = $image;
  }
  return $attrs;
}

function bbll_builder_render_attrs_col( $attrs, $container ) {
  if(!isset($container->settings->bg_image_src)) return $attrs;

  $bbll_options = get_option('bbll_store');
  $image = $container->settings->bg_image_src;

  if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
    if(isset($bbll_options['webp']) && $bbll_options['webp']){
      $image .= '.webp';
    }
  }

  if($container->settings->bg_type === 'photo' && isset($bbll_options['column_images']) && $bbll_options['column_images']){
    $attrs['data-bb-lazy-load-bgurl'] = $image;
  }
  return $attrs;
}

function bbll_builder_render_module_html($content, $module) {
  $matches = array();
  $images = array();
  // we'll need to activate this via a setting.
  /*
  if(preg_match_all('/(https?:\/\/.*\.(?:png|jpg))/i', $content, $images)){
    for($i=0;$i<count($images[1]);$i++){
      $content = str_replace($images[1][$i], $images[1][$i].'.webp', $content);
    }
  }
  */
  if(preg_match_all('/<img(.*?)>/', $content, $matches)){
    for($i=0;$i<count($matches[1]);$i++){
      $content = str_replace('<img'.$matches[1][$i].'>', '<noscript data-bbll-html="1"><img'.$matches[1][$i].'></noscript>', $content);
    }
  }
  return $content;
}

function bbll_builder_render_css( $css, $nodes, $global_settings ) {
  $matches = array();
  $bbll_options = get_option('bbll_store');
  if (isset($bbll_options['row_images']) && $bbll_options['row_images'] && preg_match_all('/\.fl-node-(.*?)\ >\ (?:.fl-row-content-wrap)\ {[ \n]?[ \t]?background-image:[ ]?url\([\'"]?(.*?)\)/', $css, $matches)) {
    for($i=0;$i<count($matches[2]);$i++){
      $css = str_replace("\n\tbackground-image: url(".$matches[2][$i].");\n", "", $css);
    }
  }
  if (isset($bbll_options['column_images']) && $bbll_options['column_images'] && preg_match_all('/\.fl-node-(.*?)\ >\ (?:.fl-col-content)\ {[ \n]?[ \t]?background-image:[ ]?url\([\'"]?(.*?)\)/', $css, $matches)) {
    for($i=0;$i<count($matches[2]);$i++){
      $css = str_replace("\n\tbackground-image: url(".$matches[2][$i].");\n", "", $css);
    }
  }
  return $css;
}

function bbll_lazyload_bgs(){
echo '<script>document.addEventListener("DOMContentLoaded", function() {
  let lazyImages = [].slice.call(document.querySelectorAll("[data-bb-lazy-load-bgurl], [data-bbll-html]"));
  let active = false;

  const lazyLoad = function() {
    if (active === false) {
      active = true;

      setTimeout(function() {
        lazyImages.forEach(function(lazyImage) {
          var html = lazyImage.dataset.bbllHtml;
          if(html){
            html = lazyImage;
            lazyImage = lazyImage.parentElement;
          }
          if ((lazyImage.getBoundingClientRect().top <= window.innerHeight && lazyImage.getBoundingClientRect().bottom >= 0) && getComputedStyle(lazyImage).display !== "none") {
            var src = lazyImage.dataset.bbLazyLoadBgurl;
            if(src){
              var wrapper = lazyImage.querySelectorAll(".fl-row-content-wrap, .fl-col-content");
              wrapper[0].style.backgroundImage = "url("+src+")";
              // lazyImage.removeAttribute("data-bb-lazy-load-bgurl");
              lazyImages = lazyImages.filter(function(image) {
                return image !== lazyImage;
              });
            }
            if(html){
              lazyImage.className += " " + "bbll-photo-img";
              lazyImage.innerHTML = html.innerHTML;
              lazyImages = lazyImages.filter(function(image) {
                return image !== html;
              });
            }



            if (lazyImages.length === 0) {
              document.removeEventListener("scroll", lazyLoad);
              window.removeEventListener("resize", lazyLoad);
              window.removeEventListener("orientationchange", lazyLoad);
            }
          }
        });

        active = false;
      }, 200);
    }
  };
  lazyLoad();

  document.addEventListener("scroll", lazyLoad);
  window.addEventListener("resize", lazyLoad);
  window.addEventListener("orientationchange", lazyLoad);
});</script>';
}


function bbll_custom_styles(){
  echo '
  <style>
  div[data-bb-lazy-load-bgurl].fl-row .fl-row-content-wrap, div[data-bb-lazy-load-bgurl].fl-col .fl-col-content{
    background-image: url(data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==);
  }
  div[data-bb-lazy-load-bgurl].fl-row .fl-row-content-wrap, div[data-bb-lazy-load-bgurl].fl-col .fl-col-content{
    -webkit-transition: background-image 0.3s;
    transition: background-image 0.3s;
  }
  .bbll-photo-img {
    -webkit-animation: bbllfadein 0.3s; /* Safari, Chrome and Opera > 12.1 */
       -moz-animation: bbllfadein 0.3s; /* Firefox < 16 */
        -ms-animation: bbllfadein 0.3s; /* Internet Explorer */
         -o-animation: bbllfadein 0.3s; /* Opera < 12.1 */
            animation: bbllfadein 0.3s;
  }
  @keyframes bbllfadein {
    from { opacity: 0; }
    to   { opacity: 1; }
  }

  /* Firefox < 16 */
  @-moz-keyframes bbllfadein {
      from { opacity: 0; }
      to   { opacity: 1; }
  }

  /* Safari, Chrome and Opera > 12.1 */
  @-webkit-keyframes bbllfadein {
      from { opacity: 0; }
      to   { opacity: 1; }
  }

  /* Internet Explorer */
  @-ms-keyframes bbllfadein {
      from { opacity: 0; }
      to   { opacity: 1; }
  }

  /* Opera < 12.1 */
  @-o-keyframes bbllfadein {
      from { opacity: 0; }
      to   { opacity: 1; }
  }
 </style>';
}

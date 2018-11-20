<?php
/*
Plugin Name: Beaver Builder - Lazy Load
Description: Lazy loads background images set using Beaver Builder. Also will serve .webp if setting is selected.
Author: Clay Harmon
Version: 0.1
*/
function bbll_load_module_examples() {
  if ( class_exists( 'FLBuilder' ) ) {
    add_filter( 'fl_builder_row_attributes', 'bbll_builder_render_attrs', 10, 2 );
    add_filter( 'fl_builder_column_attributes', 'bbll_builder_render_attrs', 10, 2 );
    add_filter( 'fl_builder_render_css', 'bbll_builder_render_css', 10, 3 );
    add_action( 'wp_footer', 'bbll_lazyload_bgs' );
    add_action( 'wp_head', 'bbll_custom_styles' );
  }
}
add_action( 'init', 'bbll_load_module_examples' );

function bbll_builder_render_attrs( $attrs, $container ) {
  if(!isset($container->settings->bg_image_src)) return $attrs;

  $image = $container->settings->bg_image_src;

  if ((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)) {
    // we'll need to activate this via a setting.
    // $image .= '.webp';
  }

  if($container->settings->bg_type === 'photo'){
    $attrs['data-bb-lazy-load-bgurl'] = $image;
  }

  if($container->settings->bg_type === 'parallax'){
    $attrs['data-parallax-image'] = '';
    $attrs['data-bb-lazy-load-bgurl'] = $image;
  }
  return $attrs;
}

function bbll_builder_render_css( $css, $nodes, $global_settings ) {
  $matches = array();
  if (preg_match_all('/\.fl-node-(.*?)\ >\ (?:.fl-row-content-wrap|.fl-col-content)\ {[ \n]?[ \t]?background-image:[ ]?url\([\'"]?(.*?)\)/', $css, $matches)) {
    for($i=0;$i<count($matches[2]);$i++){
      $css = str_replace("\n\tbackground-image: url(".$matches[2][$i].");\n", "", $css);
    }
  }
  return $css;
}

function bbll_lazyload_bgs(){
echo '<script>document.addEventListener("DOMContentLoaded", function() {
  let lazyImages = [].slice.call(document.querySelectorAll("[data-bb-lazy-load-bgurl]"));
  let active = false;

  const lazyLoad = function() {
    if (active === false) {
      active = true;

      setTimeout(function() {
        lazyImages.forEach(function(lazyImage) {
          if ((lazyImage.getBoundingClientRect().top - 100 <= window.innerHeight && lazyImage.getBoundingClientRect().bottom + 100 >= 0) && getComputedStyle(lazyImage).display !== "none") {
            var src = lazyImage.dataset.bbLazyLoadBgurl;
            var wrapper = lazyImage.querySelectorAll(".fl-row-content-wrap, .fl-col-content");
            wrapper[0].style.backgroundImage = "url("+src+")";
            // lazyImage.removeAttribute("data-bb-lazy-load-bgurl");

            lazyImages = lazyImages.filter(function(image) {
              return image !== lazyImage;
            });

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
  [data-bb-lazy-load-bgurl] .fl-row-content-wrap, [data-bb-lazy-load-bgurl] .fl-col-content{
    background-image: url(data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==);
  }
  [data-bb-lazy-load-bgurl] .fl-row-content-wrap, [data-bb-lazy-load-bgurl] .fl-col-content{
    -webkit-transition: background-image 0.3s;
    transition: background-image 0.3s;
  }
 </style>';
}

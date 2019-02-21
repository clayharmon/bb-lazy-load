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
    //add_filter( 'fl_builder_render_module_content', 'bbll_builder_render_module_html', 10, 2);
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
  if (preg_match_all('/\.fl-node-(.*?)\ >\ (?:.fl-row-content-wrap|.fl-col-content)\ {[ \n]?[ \t]?background-image:[ ]?url\([\'"]?(.*?)\)/', $css, $matches)) {
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
  [data-bb-lazy-load-bgurl] .fl-row-content-wrap, [data-bb-lazy-load-bgurl] .fl-col-content{
    background-image: url(data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==);
  }
  [data-bb-lazy-load-bgurl] .fl-row-content-wrap, [data-bb-lazy-load-bgurl] .fl-col-content{
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

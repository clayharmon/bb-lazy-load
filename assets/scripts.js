var bbll_bg_store = bbll_bg_obj;
for(var i=0; i<bbll_bg_store.length; i++){
  var cssSelector = bbll_bg_store[i]['selector'];
  var bg_image = bbll_bg_store[i]['image'];
  jQuery(cssSelector).addClass('bbll').attr('data-bg', 'url('+bg_image+')');
}
var lazyLoadInstance = new LazyLoad({ elements_selector: ".bbll" });
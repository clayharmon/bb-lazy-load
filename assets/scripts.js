const bbll_bg_store = bbll_bg_obj;
const lazyLoadInstance = new LazyLoad({ elements_selector: ".bbll" });

const bbll_selectors = Object.keys(bbll_bg_store.data);
const bbll_medias = bbll_bg_store.medias;

const bbll_matchedMedia = () => {
  bbll_selectors.forEach(selector => {
    let items = bbll_bg_store.data[selector];

    let image;
    items.forEach(item => {
      let mq = window.matchMedia(item.media);
      if(mq.matches) image = item.image;
    });

    const $el = jQuery(selector);
    $el.addClass('bbll').attr('data-bg', image);
    LazyLoad.resetStatus($el[0]);
  });

  lazyLoadInstance.update();
}

bbll_matchedMedia();

bbll_medias.forEach(media => {
  const mq = window.matchMedia(media);
  mq.addEventListener('change', bbll_matchedMedia);
});

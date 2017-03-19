/**
 * @file
 * Video formatter logic.
 */

(function ($, Drupal) {
  Drupal.behaviors.afterCloseSwipe = {
    attach: function (context) {

      function toggleVideo() {
        var iframe = document.getElementsByClassName('iframe-video');
        for (var i = 0; i < iframe.length; i++) {
          iframe[i].contentWindow.postMessage('{"event":"command","func":"' + 'pauseVideo' + '","args":""}', '*');

          var src = iframe[i].src;
          if (src.indexOf("vimeo") >= 0) {
            var player = $f(iframe[i]);
            player.api('pause');
          }
        }
      }

      $('.pswp__button--close').bind( "mouseup touchend", function(e){
          toggleVideo();
      });

      $('.pswp__button--arrow--right').bind( "mouseup touchend", function(e){
        toggleVideo();
      });

      $('.pswp__button--arrow--left').bind( "mouseup touchend", function(e){
        toggleVideo();
      });

      $(document).bind( "mouseup touchend", function(e){
        var cl = $('.pswp__button--close');
        var bl = $('.pswp__button--arrow--left');
        var br = $('.pswp__button--arrow--right');
        var share = $('.pswp__button--share');
        var fs = $('.pswp__button--fs');
        var tt = $('.pswp__share-tooltip');
        var div = $(".video-wrapper");
        if (
          !cl.is(e.target) &&
          !bl.is(e.target) &&
          !br.is(e.target) &&
          !div.is(e.target) &&
          !tt.is(e.target) &&
          !share.is(e.target) &&
          !fs.is(e.target) &&
          div.has(e.target).length === 0 &&
          tt.has(e.target).length === 0
        ) {
          //$('.wrapper .video-wrapper').remove();
          toggleVideo();
        }
      });

    }
  }
})(jQuery, Drupal);

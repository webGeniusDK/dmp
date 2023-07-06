"use strict";

(function ($) {
  /****************************************************
   *   Equalbox: Make boxes in a item grid equal height
   ****************************************************/
  function equalHeight(container) {
    $(container).each(function () {
      var _this = this;

      var eqSize = 0;
      var boxDataVal = $(this).attr('data-eb-size');

      if (boxDataVal !== undefined) {
        if (boxDataVal === "sm") {
          eqSize = 479;
        } else if (boxDataVal === "md") {
          eqSize = 767;
        } else if (boxDataVal === "lg") {
          eqSize = 991;
        } else if (boxDataVal === "xl") {
          eqSize = 1199;
        } else {
          eqSize = boxDataVal - 1;
        }
      } else {
        //if the equalbox container doesnt have the data-eb-size attribute
        eqSize = 0;
      }

      var _loop = function _loop() {
        var highestBox = 0;
        var eqSelector = '.equal-box-' + i;

        if ($(window).width() > eqSize) {
          $(eqSelector, _this).each(function () {
            $(this).innerHeight('auto');

            if ($(this).innerHeight() > highestBox) {
              highestBox = $(this).innerHeight();
            }
          });
          $(eqSelector, _this).innerHeight(highestBox);
        } else {
          $(eqSelector, _this).innerHeight('auto');
        }
      };

      for (var i = 1; i <= 5; i++) {
        _loop();
      }
    });
  }
  /**********************************************************
   *   Defer image load
   **********************************************************/


  function deferImages() {
    if ($('img').length) {
      var imgDefer = document.querySelectorAll('img');

      for (var i = 0; i < imgDefer.length; i++) {
        if (imgDefer[i].getAttribute('data-src')) {
          imgDefer[i].setAttribute('src', imgDefer[i].getAttribute('data-src'));
          imgDefer[i].removeAttribute('data-src');
        }
      }
    }

    if ($('iframe').length) {
      var _imgDefer = document.querySelectorAll('iframe');

      for (var _i = 0; _i < _imgDefer.length; _i++) {
        if (_imgDefer[_i].getAttribute('data-src')) {
          _imgDefer[_i].setAttribute('src', _imgDefer[_i].getAttribute('data-src'));

          _imgDefer[_i].removeAttribute('data-src');
        }
      }
    }

    if ($('script').length) {
      var _imgDefer2 = document.querySelectorAll('script');

      for (var _i2 = 0; _i2 < _imgDefer2.length; _i2++) {
        if (_imgDefer2[_i2].getAttribute('data-src')) {
          _imgDefer2[_i2].setAttribute('src', _imgDefer2[_i2].getAttribute('data-src'));

          _imgDefer2[_i2].removeAttribute('data-src');
        }
      }
    }

    if (document.getElementsByTagName('figure')) {
      var inlineCssDefer = document.getElementsByTagName('figure');

      for (var j = 0; j < inlineCssDefer.length; j++) {
        if (inlineCssDefer[j].getAttribute('data-src')) {
          inlineCssDefer[j].setAttribute("style", "background-image:url('" + inlineCssDefer[j].getAttribute('data-src') + "');");
          inlineCssDefer[j].removeAttribute('data-src');
        }
      }
    }

    if ($('a[data-src]').length) {
      var inlineLinkCSS = $('a[data-src]');

      for (var k = 0; k < inlineLinkCSS.length; k++) {
        if (inlineLinkCSS[k].getAttribute('data-src')) {
          inlineLinkCSS[k].setAttribute("style", "background-image:url('" + inlineLinkCSS[k].getAttribute('data-src') + "');");
          inlineLinkCSS[k].removeAttribute('data-src');
        }
      }
    }

    if ($('*[data-bg-src]').length) {
      var _inlineLinkCSS = $('*[data-bg-src]');

      for (var _k = 0; _k < _inlineLinkCSS.length; _k++) {
        if (_inlineLinkCSS[_k].getAttribute('data-bg-src')) {
          _inlineLinkCSS[_k].setAttribute("style", "background-image:url('" + _inlineLinkCSS[_k].getAttribute('data-bg-src') + "');");

          _inlineLinkCSS[_k].removeAttribute('data-bg-src');
        }
      }
    }

    if ($('article[data-src]').length) {
      var inlineArticleCss = $('article[data-src]');

      for (var _k2 = 0; _k2 < inlineArticleCss.length; _k2++) {
        if (inlineArticleCss[_k2].getAttribute('data-src')) {
          inlineArticleCss[_k2].setAttribute("style", "background-image:url('" + inlineArticleCss[_k2].getAttribute('data-src') + "');");

          inlineArticleCss[_k2].removeAttribute('data-src');
        }
      }
    }
  }
  /*******************************************************************
   *   Windows Load
   *****************************************************************/


  $(window).load(function () {
    /****   Init Equalbox system *******************/
    var equalboxContainer = $('.equalbox-container');

    if (equalboxContainer.length) {
      setTimeout(function () {
        equalHeight(equalboxContainer);
      }, 200);
      $(window).resize(function () {
        equalHeight(equalboxContainer);
      });
    }
    /** Fade in selected content after windows load *****/


    setTimeout(function () {
      $('.fade-in-block').css('opacity', 1).fadeIn(4000);
    }, 500);
    /** Fade in selected content after windows load *****/

    setTimeout(function () {
      $('.page-section').css('opacity', 1).fadeIn(4000);
    }, 500);
    /** Init Defer Images *****/

    deferImages();
  });
  /*******************************************************************
   *   Document Ready
   *****************************************************************/

  $(document).ready(function () {});
})(jQuery);
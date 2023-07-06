(function ($) {
    /****************************************************
     *   Equalbox: Make boxes in a item grid equal height
     ****************************************************/
    function equalHeight(container) {
        $(container).each(function () {
            let eqSize = 0;
            const boxDataVal = $(this).attr('data-eb-size');
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

            for (var i = 1; i <= 5; i++) {
                let highestBox = 0;
                let eqSelector = '.equal-box-' + i;
                if ($(window).width() > eqSize) {
                    $(eqSelector, this).each(function () {
                        $(this).innerHeight('auto');
                        if ($(this).innerHeight() > highestBox) {
                            highestBox = $(this).innerHeight();
                        }
                    });
                    $(eqSelector, this).innerHeight(highestBox);
                } else {
                    $(eqSelector, this).innerHeight('auto');
                }
            }
        });
    }

    /**********************************************************
     *   Defer image load
     **********************************************************/
    function deferImages() {
        if ($('img').length) {
            var imgDefer = document.querySelectorAll('img');
            for (let i = 0; i < imgDefer.length; i++) {
                if (imgDefer[i].getAttribute('data-src')) {
                    imgDefer[i].setAttribute('src', imgDefer[i].getAttribute('data-src'));
                    imgDefer[i].removeAttribute('data-src');
                }
            }
        }
        if ($('iframe').length) {
            let imgDefer = document.querySelectorAll('iframe');
            for (let i = 0; i < imgDefer.length; i++) {
                if (imgDefer[i].getAttribute('data-src')) {
                    imgDefer[i].setAttribute('src', imgDefer[i].getAttribute('data-src'));
                    imgDefer[i].removeAttribute('data-src');
                }
            }
        }
        if ($('script').length) {
            let imgDefer = document.querySelectorAll('script');
            for (let i = 0; i < imgDefer.length; i++) {
                if (imgDefer[i].getAttribute('data-src')) {
                    imgDefer[i].setAttribute('src', imgDefer[i].getAttribute('data-src'));
                    imgDefer[i].removeAttribute('data-src');
                }
            }
        }
        if (document.getElementsByTagName('figure')) {
            let inlineCssDefer = document.getElementsByTagName('figure');
            for (let j = 0; j < inlineCssDefer.length; j++) {
                if (inlineCssDefer[j].getAttribute('data-src')) {
                    inlineCssDefer[j].setAttribute("style", "background-image:url('" + inlineCssDefer[j].getAttribute('data-src') + "');");
                    inlineCssDefer[j].removeAttribute('data-src');
                }
            }
        }
        if ($('a[data-src]').length) {
            let inlineLinkCSS = $('a[data-src]');

            for (let k = 0; k < inlineLinkCSS.length; k++) {
                if (inlineLinkCSS[k].getAttribute('data-src')) {
                    inlineLinkCSS[k].setAttribute("style", "background-image:url('" + inlineLinkCSS[k].getAttribute('data-src') + "');");
                    inlineLinkCSS[k].removeAttribute('data-src');
                }
            }
        }
        if ($('*[data-bg-src]').length) {
            let inlineLinkCSS = $('*[data-bg-src]');

            for (let k = 0; k < inlineLinkCSS.length; k++) {
                if (inlineLinkCSS[k].getAttribute('data-bg-src')) {
                    inlineLinkCSS[k].setAttribute("style", "background-image:url('" + inlineLinkCSS[k].getAttribute('data-bg-src') + "');");
                    inlineLinkCSS[k].removeAttribute('data-bg-src');
                }
            }
        }
        if ($('article[data-src]').length) {
            let inlineArticleCss = $('article[data-src]');

            for (let k = 0; k < inlineArticleCss.length; k++) {
                if (inlineArticleCss[k].getAttribute('data-src')) {
                    inlineArticleCss[k].setAttribute("style", "background-image:url('" + inlineArticleCss[k].getAttribute('data-src') + "');");
                    inlineArticleCss[k].removeAttribute('data-src');
                }
            }
        }
    }


    /*******************************************************************
     *   Windows Load
     *****************************************************************/
    $(window).load(function () {

        /****   Init Equalbox system *******************/
        const equalboxContainer = $('.equalbox-container');
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
    $(document).ready(function () {


    });

})(jQuery);
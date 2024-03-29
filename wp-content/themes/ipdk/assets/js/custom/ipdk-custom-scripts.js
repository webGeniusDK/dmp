(function ($) {

    /*******************************************************************
     *   IPDK Sliders
     *****************************************************************/

    const ipdkSliders = () => {
        let options;

        $('.ipdk-slider').each(function () {
            if ($(this).hasClass('favorite-list')) {
                options = {
                    infinite: true,
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    arrows: true,
                    swipeToSlide: true,
                    mobileFirst: true,
                    dots: true,
                    responsive: [
                        {
                            breakpoint: 800,
                            settings: "unslick"
                        },
                        {
                            breakpoint: 480,
                            settings: {
                                slidesToShow: 2,
                                slidesToScroll: 1,
                                fade: false,
                                arrows: true,
                                dots: true,
                            }
                        }
                    ]
                }
            }
            $('.ipdk-slider').not('.slick-initialized').slick(options);
            $('.ipdk-slider').not('.slick-initialized').slick('setPosition');
        })
    }
    $(document).ready(function () {

        if ($('.ipdk-slider').length) {
            setTimeout(function () {
                ipdkSliders();
            }, 1000);
            $(window).resize(function () {
                ipdkSliders();
            });
        }
    });

    const singleListingGallery = () => {
        const mainImage = $('.single-listing-main-image');
        const thumbnailButtons = $('.single-listing-image');
        const seeAllImagesBtn = $('.see-all-images-btn');

        // Change main image on thumbnail button click and swap images
        thumbnailButtons.on('click', function() {
            const clickedThumbnail = $(this);
            const newMainImage = clickedThumbnail.css('background-image');
            const currentMainImage = mainImage.css('background-image');

            // Swap images
            mainImage.css('background-image', newMainImage);
            clickedThumbnail.css('background-image', currentMainImage);
        });

        // Open in Lity modal on main image click
        mainImage.on('click', function() {
            openGalleryModal(thumbnailButtons, mainImage);
        });

        // Open all images in modal on 'See All Images' button click
        seeAllImagesBtn.on('click', function() {
            openGalleryModal(thumbnailButtons, mainImage);
        });

        // Adjust visibility of 'See All Images' button and thumbnail list
        const imageCount = thumbnailButtons.length;
        if (imageCount > 4) {
            seeAllImagesBtn.addClass('more-images');
            thumbnailButtons.slice(4).hide(); // Show only first four thumbnails
        }
    };

    const openGalleryModal = (thumbnailButtons, mainImage) => {
        // Get the URL of the current main image
        const currentMainImageUrl = mainImage.css('background-image').replace(/url\((['"])?(.*?)\1\)/g, '$2');

        // Get all thumbnail image URLs
        let thumbnailImages = thumbnailButtons.map(function() {
            return $(this).css('background-image').replace(/url\((['"])?(.*?)\1\)/g, '$2');
        }).get();

        // Ensure the current main image is in the array
        if (!thumbnailImages.includes(currentMainImageUrl)) {
            thumbnailImages.unshift(currentMainImageUrl);
        }

        const sliderHtml = '<div class="single-listing-slider">' + thumbnailImages.map(img => `<div><img src="${img}" alt="Image"></div>`).join('') + '</div>';

        // Open Lity modal
        lity(sliderHtml);
    };

    //singleListingGallery();

    /*******************************************************************
     *   Add state to select dropdowns
     *****************************************************************/
    const selects = $('select');
    if (selects.length) {
        selects.each(function () {
            $(this).parent().addClass('select-parent');
            const labelWidth = $(this).parent().find('label').width();
            $(this).parent().find('select').css('minWidth', labelWidth + 50 + 'px');

            $(this).on('click', function () {
                if (!$(this).parent().hasClass('select-active')) {
                    $(this).parent().addClass('select-active');
                } else {
                    if ($(this).val() === '') {
                        $(this).parent().removeClass('select-active');
                    }
                }
            });
            $(this).on('blur', function () {
                if ($(this).val() === '' || $(this).val() === '-1') {
                    $(this).parent().removeClass('select-active');
                }
            });
        });
    }


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
        singleListingGallery();

        // Attach event listener to the document for Lity's 'open' event
        $(document).on('lity:open', function(event, lityInstance) {
            // Initialize Slick Slider
            console.log('Lightbox opened');
            // Delay the initialization of Slick Slider
            setTimeout(function() {
                $('.single-listing-slider').slick({
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    arrows: true,
                    dots: true
                });
            }, 100); // Adjust the timeout duration if necessary
        });

        /****   Init ipdk Sliders *******************/

        if ($('.ipdk-slider').length) {
                ipdkSliders();
            $(window).resize(function () {
                ipdkSliders();
            });
        }
    });

})(jQuery);
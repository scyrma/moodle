define([
        'jquery',
        'theme_school/jquery-bxslider',
        'theme_school/jquery-cslider',
        'theme_school/modernizr.custom.28468',
        'theme_school/custom_menu'
    ], function($, bxslider, cslider, modernizer, customMenu) {

    var initCourseSlider = function() {
        var minSlides,
            width = $(window).width();

        if (width <= 767) {
            minSlides = 1;
        } else {
            minSlides = 4;
        }
        $('.popular-courses-slider').bxSlider({
            speed: 1500,
            nextSelector: '#slider-next',
            prevSelector: '#slider-prev',
            nextText: '<button class="btn-link border-0"><i class="fa fa-2x fa-chevron-circle-right"></i></button>',
            prevText: '<button class="btn-link border-0"><i class="fa fa-2x fa-chevron-circle-left"></i></button>',
            pager: false,
            minSlides: minSlides,
            maxSlides: minSlides,
            slideWidth: 5000,
            adaptiveHeight: true,
            auto: false,
            pause: 7000,
            onSliderLoad: function() {
                $('.popular-courses-slider').css('visibility', 'visible');
            }
        });
    };

    var initPage = function() {
        if ($("html").attr("dir") == "rtl") {
            $(".landing-page").addClass("dir-rtl");
            $(".course-items").attr('dir', 'ltr');
            $(".slidergrid").attr('dir', 'ltr');
        }

        $('#da-slider').cslider();
    };

    return {
        init: function() {
            initCourseSlider();
            initPage();
            customMenu.init();
        }
    };
});

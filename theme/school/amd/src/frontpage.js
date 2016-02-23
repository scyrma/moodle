define(['jquery', 'theme_school/jquery-bxslider', 'theme_school/jquery-cslider', 'theme_school/modernizr.custom.28468'], function($) {
    var initCourseSlider = function() {
        var minSlides,
            width = $(window).width();

        if (width <= 767) {
            minSlides = 1;
        } else {
            minSlides = 4;
        }
        sliderCourses = $('.popular-courses-slider').bxSlider({
            speed: 1500,
            nextSelector: '#slider-next',
            prevSelector: '#slider-prev',
            pager: false,
            minSlides: minSlides,
            maxSlides: minSlides,
            slideWidth: 5000,
            adaptiveHeight: true,
            auto: false,
            pause: 7000,
        });

        $(".bx-prev, .bx-next").html("");
    };

    var initNavBar = function() {
        $('html').click(function() {
            $('.menu').removeClass('usermenu-show');
            $('.usermenu').removeClass('active-drop-user-menu');
        });

        $('.menu').css('display', 'none');
        $('.menubar').on("click", function(e) {
            $('.menu').addClass('usermenu-show');
            $('.usermenu').addClass('active-drop-user-menu');
            $('.nav-collapse').removeClass('in').removeAttr('style');
            $('.btn-navbar').addClass('collapsed');
            e.stopPropagation();
        });

        $(".btn-navbar").on("click", function() {
            if ($('#custommenu').val() == "nologin") {
                $(this).toggleClass("active-drop active-drop-nologin");
            } else if ($('#custommenu').val() == "nologinselfreg"){
                $(this).toggleClass("active-drop active-drop-nologin-selfreg");
            } else {
                $(this).toggleClass("active-drop");
            }
            $('.menu').removeClass('usermenu-show');
            $('.usermenu').removeClass('active-drop-user-menu');

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
            initNavBar();
            initPage();
        }
    };
});

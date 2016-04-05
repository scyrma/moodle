define([
        'jquery',
        'theme_school/jquery-bxslider',
        'theme_school/jquery-cslider',
        'theme_school/modernizr.custom.28468',
        'theme_school/custom_menu'
    ], function($, bxslider, cslider, modernizer, customMenu) {

    var toggleUserMenu = function(e) {
        $('.menu').toggleClass('usermenu-show');
        $('.nav-collapse').toggleClass('in').removeAttr('style');
        $('.btn-navbar').toggleClass('collapsed');
        var userMenu = $('.usermenu');

        if (userMenu.attr('aria-expanded') == "false") {
            userMenu.attr('aria-expanded', "true");
        } else {
            userMenu.attr('aria-expanded', "false");
        }

        userMenu.toggleClass('active-drop-user-menu');
    };

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
        $('html').click(function(e) {
            var userMenu = $(e.target).closest('.usermenu');

            // If we didn't click in the user menu then close it.
            if (!userMenu.length) {
                $('.menu').removeClass('usermenu-show');
                $('.usermenu').removeClass('active-drop-user-menu');
            }
        });

        $('.menu').css('display', 'none');
        $('.usermenu').on("click", function(e) {
            toggleUserMenu(e);
        });

        $('.usermenu').on("keypress", function(e) {
            if (!e.ctrlKey && !e.shiftKey && !e.altKey && !e.metaKey) {
                if (e.keyCode == 13 || e.keyCode == 32) {
                    toggleUserMenu(e);
                }
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
            initNavBar();
            initPage();
            customMenu.init();
        }
    };
});

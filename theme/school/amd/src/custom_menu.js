define(['jquery'], function($) {

    var customMenu = $('#custom-menu');

    var toggleButtonMode = function(breakWidth) {
        var currentWindowWidth = $(window).width();
        // This is the cutoff for hiding the custom menu anyway.
        if (currentWindowWidth < 980) {
            return;
        }

        if (currentWindowWidth <= breakWidth) {
            customMenu.addClass('button-mode');
        } else {
            customMenu.removeClass('button-mode');
        }
    }

    var breakWidth = $(window).width();
    if (customMenu.length) {
        var windowWidth = $(window).width();
        var navElementsWidth = customMenu.outerWidth() + customMenu.siblings().outerWidth();

        var diffWidth = windowWidth - navElementsWidth;
        if (diffWidth < 0) {
            diffWidth = 0;
        }

        // The +10 gives us a buffer;
        breakWidth = breakWidth - diffWidth + 10;
    }

    return {
        init: function() {
            if (customMenu.length) {
                customMenu.find('.custom-menu-toggle').click(function(e) {
                    customMenu.toggleClass('active');
                });

                $('html').click(function(e) {
                    var menuList = $(e.target).closest('.menu-list-container');
                    var menuToggle = $(e.target).closest('.custom-menu-toggle');
                    if (!menuList.length && !menuToggle.length) {
                        customMenu.removeClass('active');
                    }
                });

                toggleButtonMode(breakWidth);
                $(window).resize(function() {
                    toggleButtonMode(breakWidth);
                });
            }
        }
    };
});

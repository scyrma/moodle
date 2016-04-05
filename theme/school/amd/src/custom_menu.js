define(['jquery'], function($) {

    var customMenu = $('#custom-menu');
    var breakWidth = 0;

    var calculateBreakWidth = function() {
        if (breakWidth) {
            return breakWidth;
        }

        if (customMenu.length) {
            var windowWidth = $(window).width();
            var siblings = customMenu.siblings();
            var menuWidth = customMenu.outerWidth();
            var siblingWidth = 0;
            siblings.each(function(index, sibling) {
                sibling = $(sibling);
                if (sibling.is(':visible')) {
                    siblingWidth += sibling.outerWidth();
                }
            });
            var navElementsWidth = menuWidth + siblingWidth;

            var diffWidth = windowWidth - navElementsWidth;
            if (diffWidth < 0) {
                // The navbar elements are taking up more than the width of the screen,
                // which means they are in responsive mode, so we don't need to do anything.
                breakWidth = windowWidth - diffWidth;
            } else {
                // The +10 gives us a buffer;
                breakWidth = windowWidth - diffWidth + 10;
            }
            return breakWidth;
        } else {
            return 0;
        }
    };

    var toggleButtonMode = function() {
        var currentWindowWidth = $(window).width();
        // This is the cutoff for hiding the custom menu anyway.
        if (currentWindowWidth < 980) {
            return;
        }

        var currentBreakWidth = calculateBreakWidth();
        if (currentBreakWidth) {
            if (currentWindowWidth <= breakWidth) {
                customMenu.addClass('button-mode');
            } else {
                customMenu.removeClass('button-mode');
            }
        }
    };

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

                toggleButtonMode();
                $(window).resize(function() {
                    toggleButtonMode();
                });
            }
        }
    };
});

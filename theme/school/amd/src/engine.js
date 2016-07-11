define(['jquery', 'theme_bootstrapbase/bootstrap', 'theme_school/custom_menu'], function($, bootstrap, customMenu) {

    var initCourses = function() {
        $('.btn-view-list').click(function(){
            $('.course-items').addClass('course-items-list-view');
            $('.course-items').removeClass('course-items-grid-view');
            $('.view-toggle a').removeClass('active');
            $(this).addClass('active');
        });

        $('.btn-view-grid').click(function(){
            $('.course-items').removeClass('course-items-list-view');
            $('.course-items').addClass('course-items-grid-view');
            $('.view-toggle a').removeClass('active');
            $(this).addClass('active');
        });

        $('.btn-togle-details').click(function(){
            $(this).toggleClass('active');
            $(this).parents('.course-item-cont').find('.for-list-view-wr').slideToggle('active');

            if ($(this).attr('aria-expanded') == 'true') {
                $(this).attr('aria-expanded', 'false');
            } else {
                $(this).attr('aria-expanded', 'true');
            }
        });

        $('#category-picker-filter').on('click', function() {
            $('#all-category-picker-container').toggleClass('hidden');
            $('#subcategory-picker-container').toggleClass('hidden');
            $(this).toggleClass('active');
        });
    };

    var initAdminSearch = function() {
        $(".adminsearchform").submit(function(){
            if ($('.adminsearchform input[type="text"]').val().length < 1 ) {
                return false;
            }
        });
    };

    var initTooltips = function() {
        $('[data-toggle="tooltip"]').tooltip();
    };

    var initBlockPanels = function() {
        $('#block-region-side-pre').addClass('left-menu-close');
        $(".side-pre-menu").on("click",function() {
           $(this).toggleClass('is-active');
           $('#block-region-side-pre').toggleClass('left-menu-open');
           $('.nav-collapse').toggleClass('zindexclass');
        });
    };

    var initNavBar = function() {
        $('html').click(function(e) {
            var userMenu = $(e.target).closest('.usermenu');

            // If we didn't click in the user menu.
            if (!userMenu.length) {
                $('.menulist').removeClass('usermenu-show active-drop-user-menuinner');
            }
        });

        $('.menulist').css('display', 'none');
        $('.usermenu').on("click",function(e) {
            $('.menulist').toggleClass('usermenu-show active-drop-user-menuinner');
        });
    };

    return {
        init: function() {
            initCourses();
            initAdminSearch();
            initTooltips();
            initBlockPanels();
            initNavBar();
            customMenu.init();
        }
    };
});

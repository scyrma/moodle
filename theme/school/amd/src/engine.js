define(['jquery', 'theme_bootstrapbase/bootstrap'], function($) {

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

        $('document').ready(function() {
            var outerHeight = $('#region-main').outerHeight();
            $('#block-region-side-post').css('min-height', outerHeight+'px');
            $('#block-region-side-pre').css('min-height', outerHeight+'px');
        });
    };

    var initNavBar = function() {
        $('html').click(function(e) {
            $('.menulist').removeClass('usermenu-show active-drop-user-menuinner');

            var parentMenu = $(e.target).closest('.nav-collapse');
            if (!parentMenu.length) {
                $('.nav-collapse').removeClass('in').removeAttr('style');
            }
        });

        $('.menulist').css('display', 'none');
        $('.usermenu').on("click",function(e) {
            $('.menulist').toggleClass('usermenu-show active-drop-user-menuinner');
            $('.nav-collapse').toggleClass('in').removeAttr('style');
            $('.btn-navbar').toggleClass('collapsed');
            e.stopPropagation();
        });

        $(".btn-navbar").on("click",function(e) {
            if ($('#custommenu').val() == "nologinselfreg"){
                $('.nav-collapse').toggleClass('in').removeAttr('style').addClass("inner-active-drop-nologin-selfreg");
            } else {
                $('.nav-collapse').toggleClass('in').removeAttr('style');
            }

            $('.usermenu-show').removeClass('usermenu-show');
            e.stopPropagation();
        });
    };

    return {
        init: function() {
            initCourses();
            initAdminSearch();
            initTooltips();
            initBlockPanels();
            initNavBar();
        }
    };
});

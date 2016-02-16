jQuery(document).ready(function() {

// BEGIN script for grid or list courses list view
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
	});
// END script for grid or list courses list view

// BEGIN script for basic .adminsearchform validation
	$(".adminsearchform").submit(function(){
		if ($('.adminsearchform input[type="text"]').val().length < 1 ) {
			return false;
		};
	});
// END script for basic .adminsearchform validation


/*tooltip for column3home.php*/
// TODO: MAKE THIS WORK.
//$('[data-toggle="tooltip"]').tooltip();

/*
$("li.dropdown").on("click",function() {
   $(this).toggleClass('open');
});
*/

$('#block-region-side-pre').addClass('left-menu-close');
$(".side-pre-menu").on("click",function() {
   $(this).toggleClass('is-active');
   $('#block-region-side-pre').toggleClass('left-menu-open');
   $('.nav-collapse').toggleClass('zindexclass');
});

$('html').click(function() {
    $('.menulist').removeClass('usermenu-show active-drop-user-menuinner');
});

$('.menulist').css('display', 'none');
$('.usermenu').on("click",function(e) {
	$('.menulist').addClass('usermenu-show active-drop-user-menuinner');
	$('.nav-collapse').removeClass('in').removeAttr('style');
	$('.btn-navbar').addClass('collapsed');
    e.stopPropagation();
});

$(".btn-navbar").on("click",function() {
	if ($('#custommenu').val() == "nologinselfreg"){
		$('.nav-collapse').toggleClass('in').removeAttr('style').addClass("inner-active-drop-nologin-selfreg");
	} else {
		$('.nav-collapse').toggleClass('in').removeAttr('style');
	}

	$('.usermenu-show').removeClass('usermenu-show');
});

$('#category-picker-filter').on('click', function() {
    $('#all-category-picker-container').toggleClass('hidden');
    $('#subcategory-picker-container').toggleClass('hidden');
    $(this).toggleClass('active');
});

$( window ).load(function() {
  var outerHeight = $('#region-main').outerHeight();
	$('#block-region-side-post').css('min-height', outerHeight+'px');
	$('#block-region-side-pre').css('min-height', outerHeight+'px');
	});

});



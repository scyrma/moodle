jQuery(document).ready(function() {
	$('.bxslidergrid').bxSlider({
    adaptiveHeight: true,
    nextSelector: '#bxslidergrid-next',
    prevSelector: '#bxslidergrid-prev',
    pager: false
    });
    $(".bx-prev, .bx-next").html("");
// BEGIN script for slider on landing page
	var _minSlides,
		_width = $(window).width();

	if (_width <= 767) {
		_minSlides = 1;
	} else {
		_minSlides = 4;
	}
	_sliderCourses = $('.popular-courses-slider').bxSlider({
		speed: 1500,
		nextSelector: '#slider-next',
		prevSelector: '#slider-prev',
		pager: false,
		minSlides: _minSlides,
		maxSlides: _minSlides,
		slideWidth: 5000,
		adaptiveHeight: true,
		auto: false,
		pause: 7000,
	});
// END script for slider on landing page

/*add class for frontpage.php*/
if($('.news-updates-extraclass').length == 1) {
	$('.news-updates-extraclass').each(function() {
		$(this).addClass('news-updates-item1');
	});
} else if($('.news-updates-extraclass').length == 2) {
	$('.news-updates-extraclass').each(function() {
		$(this).addClass('news-updates-item2');
	});
} else if($('.news-updates-extraclass').length == 3) {
	$('.news-updates-extraclass').each(function() {
		$(this).addClass('news-updates-item3');
	});
}
if($('.block-links-item-extraclass').length == 1) {
	$('.block-links-item-extraclass').each(function() {
		$(this).addClass('block-links-item1');
	});
} else if($('.block-links-item-extraclass').length == 2) {
	$('.block-links-item-extraclass').each(function() {
		$(this).addClass('block-links-item2');
	});
} else if($('.block-links-item-extraclass').length == 3) {
	$('.block-links-item-extraclass').each(function() {
		$(this).addClass('block-links-item3');
	});
}

$('.menu').css('display', 'none');
$('.menubar').on("click",function() {
	$('.menu').toggleClass('usermenu-show');
	$('.usermenu').toggleClass('active-drop-user-menu');
	$('.nav-collapse').removeClass('in').removeAttr('style');
	$('.btn-navbar').addClass('collapsed');
});

$(".btn-navbar").on("click",function() {
	if ($('#custommenu').val() == "nologin") {
		$(this).addClass("active-drop").addClass("active-drop-nologin");
	} else if ($('#custommenu').val() == "nologinselfreg"){
		$(this).addClass("active-drop").addClass("active-drop-nologin-selfreg");
	} else {
		$(this).addClass("active-drop");
	}
	$('.menu').removeClass('usermenu-show');
	$('.usermenu').removeClass('active-drop-user-menu');

});
if ($("html").attr("dir") == "rtl") {
	$(".landing-page").addClass("dir-rtl");
	$(".course-items").attr('dir', 'ltr');
	$(".slidergrid").attr('dir', 'ltr');
}

$(".bx-prev, .bx-next").html("");
});

jQuery(document).ready(function() {

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
});
$(".btn-navbar").on("click",function() {
	$('.usermenu-show').removeClass('usermenu-show');
});
});
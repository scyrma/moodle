define(['jquery'], function($) {
		
	/*
	 * Slider object.
	 */
	if($(".da-slide").attr("data-autoplay") == 2) {
		var autoplayset = false;
	}
	if($(".da-slide").attr("data-autoplay") == 1) {
		var autoplayset = true;
	}
	$.Slider = function( options, element ) {
	
		this.$el	= $( element );
		
		this._init( options );
		
	};
	
	$.Slider.defaults 		= {
		current		: 0, 	// index of current slide
		bgincrement	: 0,	// increment the bg position (parallax effect) when sliding
		autoplay	: autoplayset,// slideshow on / off
		interval	: $(".da-slide").attr("data-interval")  // time between transitions
    };
	
	$.Slider.prototype 	= {
		_init 				: function( options ) {
			
			this.options 		= $.extend( true, {}, $.Slider.defaults, options );
			
			this.$slides		= this.$el.children('div.da-slide');
			this.slidesCount	= this.$slides.length;
			
			this.current		= this.options.current;
			
			if( this.current < 0 || this.current >= this.slidesCount ) {
			
				this.current	= 0;
			
			}
			
			this.$slides.eq( this.current ).addClass( 'da-slide-current' );
			
			var $navigation		= $( '<nav class="da-dots"/>' );
			for( var i = 0; i < this.slidesCount; ++i ) {
			
				$navigation.append( '<span/>' );
			
			}
			$navigation.appendTo( this.$el );
			
			this.$pages			= this.$el.find('nav.da-dots > span');
			this.$navNext		= this.$el.find('span.da-arrows-next');
			this.$navPrev		= this.$el.find('span.da-arrows-prev');
			
			this.isAnimating	= false;
			
			this.bgpositer		= 0;
			
			this.cssAnimations	= Modernizr.cssanimations;
			this.cssTransitions	= Modernizr.csstransitions;
			
			if( !this.cssAnimations || !this.cssAnimations ) {
				
				this.$el.addClass( 'da-slider-fb' );
			
			}
			
			this._updatePage();
			
			// load the events
			this._loadEvents();
			
			// slideshow
			if( this.options.autoplay ) {
			
				this._startSlideshow();
			
			}
			
		},
		_navigate			: function( page, dir ) {
			
			var $current	= this.$slides.eq( this.current ), $next, _self = this;
			
			if( this.current === page || this.isAnimating ) return false;
			
			this.isAnimating	= true;
			
			// check dir
			var classTo, classFrom, d;
			
			if( !dir ) {
			
				( page > this.current ) ? d = 'next' : d = 'prev';
			
			}
			else {
			
				d = dir;
			
			}
				
			if( this.cssAnimations && this.cssAnimations ) {
				
				if( d === 'next' ) {
				
					classTo		= 'da-slide-toleft';
					classFrom	= 'da-slide-fromright';
					++this.bgpositer;
				
				}
				else {
				
					classTo		= 'da-slide-toright';
					classFrom	= 'da-slide-fromleft';
					--this.bgpositer;
				
				}
				
				this.$el.css( 'background-position' , this.bgpositer * this.options.bgincrement + '% 0%' );
			
			}
			
			this.current	= page;
			
			$next			= this.$slides.eq( this.current );
			
			if( this.cssAnimations && this.cssAnimations ) {
			
				var rmClasses	= 'da-slide-toleft da-slide-toright da-slide-fromleft da-slide-fromright';
				$current.removeClass( rmClasses );
				$next.removeClass( rmClasses );
				
				$current.addClass( classTo );
				$next.addClass( classFrom );
				
				$current.removeClass( 'da-slide-current' );
				$next.addClass( 'da-slide-current' );
				
			}
			
			// fallback
			if( !this.cssAnimations || !this.cssAnimations ) {
				
				$next.css( 'left', ( d === 'next' ) ? '100%' : '-100%' ).stop().animate( {
					left : '0%'
				}, 1000, function() { 
					_self.isAnimating = false; 
				});
				
				$current.stop().animate( {
					left : ( d === 'next' ) ? '-100%' : '100%'
				}, 1000, function() { 
					$current.removeClass( 'da-slide-current' ); 
				});
				
			}
			
			this._updatePage();
			
		},
		_updatePage			: function() {
            var _self = this;
			this.$pages.removeClass( 'da-dots-current' );
            this.$slides.each(function(index, slide) {
                _self._ariaHideSlide(slide);
            });

			this.$pages.eq( this.current ).addClass( 'da-dots-current' );
			this._ariaShowSlide(this.$slides.eq( this.current ));
		},
        _ariaHideSlide: function(slide) {
            slide = $(slide);

            if (slide.attr('aria-hidden') != 'true') {
                slide.attr('aria-hidden', 'true').attr('tabindex', '-1').removeAttr('aria-live');
                slide.find('*').each(function(index, child) {
                    child = $(child);

                    var existingIndex = child.attr('tabindex');
                    if (typeof existingIndex == 'undefined' || existingIndex == '') {
                        existingIndex = 'null';
                    }

                    child.attr('data-old-tabindex', existingIndex);
                    child.attr('tabindex', '-1');
                });
            }
        },
        _ariaShowSlide: function(slide) {
            slide = $(slide);
            if (slide.attr('aria-hidden') != 'false') {
                slide.attr('aria-hidden', 'false').attr('tabindex', '0');
                slide.find('*').each(function(index, child) {
                    child = $(child);
                    var existingIndex = child.attr('data-old-tabindex');
                    if (existingIndex == 'null') {
                        child.removeAttr('tabindex');
                    } else {
                        child.attr('tabindex', existingIndex);
                    }

                    child.removeAttr('data-old-tabindex');
                });
            }
        },
        _nextSlide: function() {
            if( this.options.autoplay ) {
                clearTimeout( this.slideshow );
                this.options.autoplay	= autoplayset;
            }

            var page = ( this.current < this.slidesCount - 1 ) ? page = this.current + 1 : page = 0;
            this._navigate( page, 'next' );
            this.$slides.eq( this.current ).attr('aria-live', 'assertive');
        },
        _previousSlide: function() {
            if( this.options.autoplay ) {
                clearTimeout( this.slideshow );
                this.options.autoplay	= autoplayset;
            }

            var page = ( this.current > 0 ) ? page = this.current - 1 : page = this.slidesCount - 1;
            this._navigate( page, 'prev' );
            this.$slides.eq( this.current ).attr('aria-live', 'assertive');
        },
		_startSlideshow		: function() {
		
			var _self	= this;
			
			this.slideshow	= setTimeout( function() {
				
				var page = ( _self.current < _self.slidesCount - 1 ) ? page = _self.current + 1 : page = 0;
				_self._navigate( page, 'next' );
				
				if( _self.options.autoplay ) {
				
					_self._startSlideshow();
				
				}
			
			}, this.options.interval );
		
		},
        _stopSlideshow: function() {
            clearTimeout(this.slideshow);
        },
		page				: function( idx ) {
			
			if( idx >= this.slidesCount || idx < 0 ) {
			
				return false;
			
			}
			
			if( this.options.autoplay ) {
			
				clearTimeout( this.slideshow );
				this.options.autoplay	= autoplayset;
			
			}
			
			this._navigate( idx );
			
		},
		_loadEvents			: function() {
			
			var _self = this;
			
            _self.$el.focusin(function(e) {
                _self._stopSlideshow();
            });

            _self.$el.focusout(function(e) {
                if (!_self.$el.find(e.relatedTarget).length) {
                    _self._startSlideshow();
                }
            });

			this.$pages.on( 'click.cslider', function( event ) {
				
				_self.page( $(this).index() );
				return false;
				
			});
			
			this.$navNext.on( 'click.cslider', function( event ) {
				_self._nextSlide();
				return false;
			});
            this.$navNext.keypress(function(e) {
                if (e.keyCode == 13 || e.keyCode == 32) {
                    if (!e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey) {
                        _self._nextSlide();
                    }
                }
            });
			
			this.$navPrev.on( 'click.cslider', function( event ) {
				_self._previousSlide();
				return false;
			});
            this.$navPrev.keypress(function(e) {
                if (e.keyCode == 13 || e.keyCode == 32) {
                    if (!e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey) {
                        _self._previousSlide();
                    }
                }
            });
			
			if( this.cssTransitions ) {
			
				if( !this.options.bgincrement ) {
					
					this.$el.on( 'webkitAnimationEnd.cslider animationend.cslider OAnimationEnd.cslider', function( event ) {
						
						if(
                            event.originalEvent.animationName === 'toRightAnim4' ||
                            event.originalEvent.animationName === 'rtlToRightAnim4' ||
                            event.originalEvent.animationName === 'toLeftAnim4' ||
                            event.originalEvent.animationName === 'rtlToLeftAnim4') {
							
							_self.isAnimating	= false;
						
						}	
						
					});
					
				}
				else {
				
					this.$el.on( 'webkitTransitionEnd.cslider transitionend.cslider OTransitionEnd.cslider', function( event ) {
					
						if( event.target.id === _self.$el.attr( 'id' ) )
							_self.isAnimating	= false;
						
					});
				
				}
			
			}
			
		}
	};
	
	var logError 			= function( message ) {
		if ( this.console ) {
			console.error( message );
		}
	};
	
	$.fn.cslider			= function( options ) {
	
		if ( typeof options === 'string' ) {
			
			var args = Array.prototype.slice.call( arguments, 1 );
			
			this.each(function() {
			
				var instance = $.data( this, 'cslider' );
				
				if ( !instance ) {
					logError( "cannot call methods on cslider prior to initialization; " +
					"attempted to call method '" + options + "'" );
					return;
				}
				
				if ( !$.isFunction( instance[options] ) || options.charAt(0) === "_" ) {
					logError( "no such method '" + options + "' for cslider instance" );
					return;
				}
				
				instance[ options ].apply( instance, args );
			
			});
		
		} 
		else {
		
			this.each(function() {
			
				var instance = $.data( this, 'cslider' );
				if ( !instance ) {
					$.data( this, 'cslider', new $.Slider( options, this ) );
				}
			});
		
		}
		
		return this;
		
	};
	
});

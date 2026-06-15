/* global jQuery, wp */
( function ( $ ) {
	'use strict';

	// Bộ chọn ảnh (Bot Custom Icon) dùng Media Library của WordPress.
	$( document ).on( 'click', '.tvn-media-pick', function ( e ) {
		e.preventDefault();

		var $btn  = $( this );
		var $wrap = $btn.closest( '.tvn-media' );
		var $url  = $wrap.find( '.tvn-media-url' );
		var $prev = $wrap.find( '.tvn-media-preview' );

		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		var frame = wp.media( {
			title: 'Chọn ảnh icon',
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var src = att.url;
			if ( att.sizes && att.sizes.thumbnail ) {
				src = att.sizes.thumbnail.url;
			}
			$url.val( att.url );
			$prev.html( '<img src="' + src + '" alt="" />' );
		} );

		frame.open();
	} );
} )( jQuery );

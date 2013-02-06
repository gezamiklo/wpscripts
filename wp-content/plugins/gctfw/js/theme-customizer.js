( function( $ ){
	wp.customize( 'color_scheme', function( value ) {
		value.bind( function( to ) {
			alert( to )
		} );
	} );
	wp.customize( 'font_scheme', function( value ) {
		value.bind( function( to ) {
			alert( to )
		} );
	} );
} )( jQuery );
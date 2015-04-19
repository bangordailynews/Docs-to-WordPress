var D2WPOptions = {
	parseTimeout: null,
	submit: function(){
		
		jQuery.post( ajaxurl, ( data = {
			action: 'docs_to_wp_save_options',
			d2w_cid: jQuery('#docs_to_wp_client_id').val(),
			d2w_secret: jQuery('#docs_to_wp_client_secret').val(),
			d2w_origin: jQuery('#docs_to_wp_origin_share_link').data('folder_id'),
			d2w_dest: jQuery('#docs_to_wp_destination_share_link').data('folder_id')
		}), function( response ) {
			console.log( data );
			console.log( response );
		}, 'json' );
		
	},
	parse: function() {
		var elemid = jQuery(this).attr('id');
		clearTimeout(D2WPOptions.parseTimeout);
		D2WPOptions.parseTimeout = setTimeout(function(){

			var parser = document.createElement('a');
			parser.href = jQuery('#' + elemid).val();

			var params = {};
			var parts = parser.search.substr(1).split('&');
			for( var part in parts ){

				var part = parts[part].split('=');
				params[ decodeURIComponent( part[ 0 ] ) ] = decodeURIComponent( part[ 1 ] );
				
			}

			if( params.id ){
				jQuery('#' + elemid).data('folder_id', params.id);
				jQuery('#' + elemid).next().html("<br />ID: <strong>" + params.id + "</strong><br />");
			}
	
		}, 500);
	},
	rebuildURL: function( elem ) {
		
		if( !elem.val().length )
			return;

		// https://drive.google.com/folderview?id=0AvbaiFDF9adfs8ALJDfadsf9JLKSDFjavadvasdf&usp=sharing
		var id = elem.val();
		elem.val( 'https://drive.google.com/folderview?id=' + id );
		elem.data('folder_id', id);
		elem.next().html("<br />ID: <strong>" + id + "</strong><br />");

	},
	init: function() {

		jQuery('#docs_to_wp_options_submit').click( D2WPOptions.submit );
		jQuery('#docs_to_wp_origin_share_link').keyup( D2WPOptions.parse );
		jQuery('#docs_to_wp_destination_share_link').keyup( D2WPOptions.parse );
		D2WPOptions.rebuildURL( jQuery('#docs_to_wp_origin_share_link') );
		D2WPOptions.rebuildURL( jQuery('#docs_to_wp_destination_share_link') );
		jQuery('#docs_to_wp_options_check_gdocs').click( function() {
			jQuery.get(ajaxurl, {
				action: 'check_gdocs'
			}, function( data ){
				console.log(data);
			}, 'json');
		});
	}
};

jQuery(document).ready( D2WPOptions.init );

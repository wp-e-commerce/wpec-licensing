jQuery(document).ready(function ($) {
	
	$('input#wpec_license_enabled').on( 'change', function() {
		var license_enabled = $('#wpec_license_enabled').is(':checked');
		var $toggled_rows = $('.wpec_lic_toggled_row');

		if ( ! license_enabled ) {
			$toggled_rows.hide();
			return;
		}

		$toggled_rows.show();
	});

	$('input[name="wpec_lic_is_lifetime"]').change( function() {
		var unlimited = $(this).val();
		if ( unlimited == 1 ) {
			$('#wpec_license_length_wrapper').hide();
		} else {
			$('#wpec_license_length_wrapper').show();
		}
	});
	
	if($('.license-datepicker').length > 0 ) {
		var dateFormat = 'yy-mm-dd';
		$('.license-datepicker').datepicker({dateFormat: dateFormat});
	}
	
	$( '#license-unlimited' ).change( function() {
		var $this = $(this);
		if( $this.attr( 'checked' ) ) {
			$( '#license_duarion' ).val('none');
		} else if( 'none' == $( '#license_duarion' ).val() ) {
			$( '#license_duarion' ).val('').trigger('focus');
		}
	});

	$( '#activation-unlimited' ).change( function() {
		var $this = $(this);
		if( $this.attr( 'checked' ) ) {
			$( '#active_limit' ).val('unlimited');
		} else if( 'unlimited' == $( '#active_limit' ).val() ) {
			$( '#active_limit' ).val('').trigger('focus');
		}
	});
	
	$('.wpec-lic-limit-change').click(function(e) {
		e.preventDefault();
		var button = $(this),
			direction = button.data('action'),
			data = {
				action: 'wpec_license_change_limit',
				license: button.data('id'),
				todo: button.data('action'),
			};
		button.toggleClass('button-disabled');
		$.post(ajaxurl, data, function(response, status) {
			button.toggleClass('button-disabled');
			$('#wpec-lic-' + data.license + '-limit').text( response );
		});
	});

	//License creating scripts
	//Search for user
	$('.license-user-search').keyup(function() {
		var user_search = $(this).val();
		$('.license-ajax-user').show();
		data = {
			action: 'license_search_users',
			user_name: user_search,
			license_nonce: license_vars.license_member_nonce
		};

		$.ajax({
		 type: "POST",
		 data: data,
		 dataType: "json",
		 url: ajaxurl,
			success: function (search_response) {

				$('.license-ajax-user').hide();

				$('#license_user_search_results').html('');

				if(search_response.id == 'found') {
					$(search_response.results).appendTo('#license_user_search_results');
				} else if(search_response.id == 'fail') {
					$('#license_user_search_results').html(search_response.msg);
				}
			}
		});
	});
	$('body').on('click', '#license_user_search_results a', function(e) {
		e.preventDefault();
		$('#license-user-id').val( $(this).data('login-id') );
		$('#license-user-name').val( $(this).data('login-name') );
		$('#license_user_search_results').html('');
	});
	//Search for products
		$('.license-product-search').keyup(function() {
		var product_search = $(this).val();
		$('.license-ajax-product').show();
		data = {
			action: 'license_search_products',
			product_search: product_search,
			license_nonce: license_vars.license_product_nonce
		};

		$.ajax({
		 type: "POST",
		 data: data,
		 dataType: "json",
		 url: ajaxurl,
			success: function (search_response) {

				$('.license-ajax-product').hide();

				$('#license_product_search_results').html('');

				if(search_response.id == 'found') {
					$(search_response.results).appendTo('#license_product_search_results');
				} else if(search_response.id == 'fail') {
					$('#license_product_search_results').html(search_response.msg);
				}
			}
		});
	});
	$('body').on('click', '#license_product_search_results a', function(e) {
		e.preventDefault();
		$('#license-product-id').val( $(this).data('product-id') );
		$('#license-product-name').val( $(this).data('product-name') );
		$('#license_product_search_results').html('');
	});

});
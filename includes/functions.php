<?php
function wpec_lic_calc_license_expiration( $product_id, $purchase_time ) {

	$current_time       = current_time( 'timestamp' );
	$last_day           = cal_days_in_month( CAL_GREGORIAN, date( 'n', $current_time ), date( 'Y', $current_time ) );

	$expiration_length 	= get_post_meta( $product_id, '_wpec_lic_exp_length', true );
	$expiration_unit 	= get_post_meta( $product_id, '_wpec_lic_exp_unit', true );
	$is_lifetime 		= get_post_meta( $product_id, '_wpec_lic_download_lifetime', true );
	
	if( $is_lifetime == '1' ) {
		$expiration = 'lifetime';
	} else {
		
		$expiration 	= date( 'Y-m-d H:i:s', strtotime( '+' . $expiration_length . ' ' . $expiration_unit . ' 23:59:59', strtotime( $purchase_time ) ) );
		
		if( date( 'j', $current_time ) == $last_day && 'day' != $expiration_unit ) {
			$expiration = date( 'Y-m-d H:i:s', strtotime( $expiration . ' +2 days' ) );
		}		
		
	}
	
	return $expiration;
}


function wpec_license_set_license_status ( $licenseid, $status ) {
	global $wpdb;
	
	
	$license = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `id` = '".$licenseid."' LIMIT 1", ARRAY_A );
	
	$current_status = $license['active'];

	if( strtolower( $current_status ) === strtolower( $status ) ) {
		return; // Statuses are the same
	}
	
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpec_licensing_licenses SET active = '%d' WHERE `id` = '%d' LIMIT 1", $status, $licenseid ) );
}


function wpec_license_renew_license( $license_id = 0 ) {
	global $wpdb;

	$license = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `id` = '".$license_id."' LIMIT 1", ARRAY_A );
	
	$expiration = $license['expiration_date'];
	
	if ( $expiration == 'lifetime' )
		return;

	// If expiration is less than today's time() then we need to renew it from time() now
	// that way renewing won't just renew them and expire immediately.
	// i.g. if they renew a license in 2011, it should be active now, not renew until 2012
	if ( strtotime( $expiration ) < time() ) {
		$expiration = time();
		$new_expiration = wpec_lic_calc_license_expiration( $license['product_id'], date("Y-m-d H:i:s", $expiration ) );
	} else {
		// Set license expiration date
		$new_expiration = wpec_lic_calc_license_expiration( $license['product_id'], $expiration );
	}

	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpec_licensing_licenses SET active = '1', expiration_date = '%s' WHERE `id` = '%d' LIMIT 1", $new_expiration, $license_id ) );
}

//Misc admin processing functions
function wpec_license_processing_functions() {
	
	if( ! is_admin() )
	return;
	
	if( ! empty( $_POST ) ) {
		
		// create a new license for user
		if( isset( $_POST['license-action'] ) && $_POST['license-action'] == 'add-manual-license' ) {

			if( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have permission to perform this action.', 'wpec_license' ) );
			}

			if( empty( $_POST['user'] ) || empty( $_POST['product_id'] ) ) {
				$url = admin_url( 'admin.php?page=wpec_license' );
				wp_safe_redirect( esc_url_raw( $url ) ); exit;
			}
			
			$date = empty( $_POST['product_purchase'] ) ? date("Y-m-d H:i:s") : $_POST['product_purchase'];
			
			wpec_license_manual_valid_license_key( $_POST['product_id'], $_POST['user_id'], $date );
			
			$url = admin_url( 'admin.php?page=wpec_license' );
			wp_safe_redirect( esc_url_raw( $url ) ); exit;
		}
		
	}

}
add_action( 'admin_init', 'wpec_license_processing_functions' );

//Create a license key and checks for duplicates
function wpec_license_manual_valid_license_key( $product_id, $userid, $purchase_date ) {
	global $wpdb;
	
	do {
		$key = WPEC_Licensing_Orders::generate_license_key();
		$api_check = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$key."' LIMIT 1", ARRAY_A );
	} while ( $api_check != null );
	
	$license_valid = wpec_lic_calc_license_expiration( $product_id,  $purchase_date );

	$user = get_user_by( 'id', $userid );
	$customer_email = $user->user_email;
	$activation_limit = get_post_meta( $product_id,'_wpec_lic_limit',true );
	$product_name = get_the_title( $product_id );
	
	$wpdb->query( "INSERT INTO `{$wpdb->prefix}wpec_licensing_licenses` ( `email` , `license_key` , `active_limit`, `active`, `purchase_id`, `cart_item_id`, `product_name`, `product_id`, `user_id`, `purchase_date`, `expiration_date` )
				VALUES ( '$customer_email', '$key', '$activation_limit', '1', '0', '0', '".$product_name."', '".$product_id."', '". $userid ."', '". $purchase_date ."', '".$license_valid."' );");
}
//

function wpec_lic_get_file_extension( $str ) {
	$parts = explode( '.', $str );
	return end( $parts );
}

/**
 * Get the file content type
 *
 * @access   public
 * @param    string    file extension
 * @return   string
 */
function wpec_lic_get_file_ctype( $extension ) {
	switch( $extension ):
		case 'ac'       : $ctype = "application/pkix-attr-cert"; break;
		case 'adp'      : $ctype = "audio/adpcm"; break;
		case 'ai'       : $ctype = "application/postscript"; break;
		case 'aif'      : $ctype = "audio/x-aiff"; break;
		case 'aifc'     : $ctype = "audio/x-aiff"; break;
		case 'aiff'     : $ctype = "audio/x-aiff"; break;
		case 'air'      : $ctype = "application/vnd.adobe.air-application-installer-package+zip"; break;
		case 'apk'      : $ctype = "application/vnd.android.package-archive"; break;
		case 'asc'      : $ctype = "application/pgp-signature"; break;
		case 'atom'     : $ctype = "application/atom+xml"; break;
		case 'atomcat'  : $ctype = "application/atomcat+xml"; break;
		case 'atomsvc'  : $ctype = "application/atomsvc+xml"; break;
		case 'au'       : $ctype = "audio/basic"; break;
		case 'aw'       : $ctype = "application/applixware"; break;
		case 'avi'      : $ctype = "video/x-msvideo"; break;
		case 'bcpio'    : $ctype = "application/x-bcpio"; break;
		case 'bin'      : $ctype = "application/octet-stream"; break;
		case 'bmp'      : $ctype = "image/bmp"; break;
		case 'boz'      : $ctype = "application/x-bzip2"; break;
		case 'bpk'      : $ctype = "application/octet-stream"; break;
		case 'bz'       : $ctype = "application/x-bzip"; break;
		case 'bz2'      : $ctype = "application/x-bzip2"; break;
		case 'ccxml'    : $ctype = "application/ccxml+xml"; break;
		case 'cdmia'    : $ctype = "application/cdmi-capability"; break;
		case 'cdmic'    : $ctype = "application/cdmi-container"; break;
		case 'cdmid'    : $ctype = "application/cdmi-domain"; break;
		case 'cdmio'    : $ctype = "application/cdmi-object"; break;
		case 'cdmiq'    : $ctype = "application/cdmi-queue"; break;
		case 'cdf'      : $ctype = "application/x-netcdf"; break;
		case 'cer'      : $ctype = "application/pkix-cert"; break;
		case 'cgm'      : $ctype = "image/cgm"; break;
		case 'class'    : $ctype = "application/octet-stream"; break;
		case 'cpio'     : $ctype = "application/x-cpio"; break;
		case 'cpt'      : $ctype = "application/mac-compactpro"; break;
		case 'crl'      : $ctype = "application/pkix-crl"; break;
		case 'csh'      : $ctype = "application/x-csh"; break;
		case 'css'      : $ctype = "text/css"; break;
		case 'cu'       : $ctype = "application/cu-seeme"; break;
		case 'davmount' : $ctype = "application/davmount+xml"; break;
		case 'dbk'      : $ctype = "application/docbook+xml"; break;
		case 'dcr'      : $ctype = "application/x-director"; break;
		case 'deploy'   : $ctype = "application/octet-stream"; break;
		case 'dif'      : $ctype = "video/x-dv"; break;
		case 'dir'      : $ctype = "application/x-director"; break;
		case 'dist'     : $ctype = "application/octet-stream"; break;
		case 'distz'    : $ctype = "application/octet-stream"; break;
		case 'djv'      : $ctype = "image/vnd.djvu"; break;
		case 'djvu'     : $ctype = "image/vnd.djvu"; break;
		case 'dll'      : $ctype = "application/octet-stream"; break;
		case 'dmg'      : $ctype = "application/octet-stream"; break;
		case 'dms'      : $ctype = "application/octet-stream"; break;
		case 'doc'      : $ctype = "application/msword"; break;
		case 'docx'     : $ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"; break;
		case 'dotx'     : $ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.template"; break;
		case 'dssc'     : $ctype = "application/dssc+der"; break;
		case 'dtd'      : $ctype = "application/xml-dtd"; break;
		case 'dump'     : $ctype = "application/octet-stream"; break;
		case 'dv'       : $ctype = "video/x-dv"; break;
		case 'dvi'      : $ctype = "application/x-dvi"; break;
		case 'dxr'      : $ctype = "application/x-director"; break;
		case 'ecma'     : $ctype = "application/ecmascript"; break;
		case 'elc'      : $ctype = "application/octet-stream"; break;
		case 'emma'     : $ctype = "application/emma+xml"; break;
		case 'eps'      : $ctype = "application/postscript"; break;
		case 'epub'     : $ctype = "application/epub+zip"; break;
		case 'etx'      : $ctype = "text/x-setext"; break;
		case 'exe'      : $ctype = "application/octet-stream"; break;
		case 'exi'      : $ctype = "application/exi"; break;
		case 'ez'       : $ctype = "application/andrew-inset"; break;
		case 'f4v'      : $ctype = "video/x-f4v"; break;
		case 'fli'      : $ctype = "video/x-fli"; break;
		case 'flv'      : $ctype = "video/x-flv"; break;
		case 'gif'      : $ctype = "image/gif"; break;
		case 'gml'      : $ctype = "application/srgs"; break;
		case 'gpx'      : $ctype = "application/gml+xml"; break;
		case 'gram'     : $ctype = "application/gpx+xml"; break;
		case 'grxml'    : $ctype = "application/srgs+xml"; break;
		case 'gtar'     : $ctype = "application/x-gtar"; break;
		case 'gxf'      : $ctype = "application/gxf"; break;
		case 'hdf'      : $ctype = "application/x-hdf"; break;
		case 'hqx'      : $ctype = "application/mac-binhex40"; break;
		case 'htm'      : $ctype = "text/html"; break;
		case 'html'     : $ctype = "text/html"; break;
		case 'ice'      : $ctype = "x-conference/x-cooltalk"; break;
		case 'ico'      : $ctype = "image/x-icon"; break;
		case 'ics'      : $ctype = "text/calendar"; break;
		case 'ief'      : $ctype = "image/ief"; break;
		case 'ifb'      : $ctype = "text/calendar"; break;
		case 'iges'     : $ctype = "model/iges"; break;
		case 'igs'      : $ctype = "model/iges"; break;
		case 'ink'      : $ctype = "application/inkml+xml"; break;
		case 'inkml'    : $ctype = "application/inkml+xml"; break;
		case 'ipfix'    : $ctype = "application/ipfix"; break;
		case 'jar'      : $ctype = "application/java-archive"; break;
		case 'jnlp'     : $ctype = "application/x-java-jnlp-file"; break;
		case 'jp2'      : $ctype = "image/jp2"; break;
		case 'jpe'      : $ctype = "image/jpeg"; break;
		case 'jpeg'     : $ctype = "image/jpeg"; break;
		case 'jpg'      : $ctype = "image/jpeg"; break;
		case 'js'       : $ctype = "application/javascript"; break;
		case 'json'     : $ctype = "application/json"; break;
		case 'jsonml'   : $ctype = "application/jsonml+json"; break;
		case 'kar'      : $ctype = "audio/midi"; break;
		case 'latex'    : $ctype = "application/x-latex"; break;
		case 'lha'      : $ctype = "application/octet-stream"; break;
		case 'lrf'      : $ctype = "application/octet-stream"; break;
		case 'lzh'      : $ctype = "application/octet-stream"; break;
		case 'lostxml'  : $ctype = "application/lost+xml"; break;
		case 'm3u'      : $ctype = "audio/x-mpegurl"; break;
		case 'm4a'      : $ctype = "audio/mp4a-latm"; break;
		case 'm4b'      : $ctype = "audio/mp4a-latm"; break;
		case 'm4p'      : $ctype = "audio/mp4a-latm"; break;
		case 'm4u'      : $ctype = "video/vnd.mpegurl"; break;
		case 'm4v'      : $ctype = "video/x-m4v"; break;
		case 'm21'      : $ctype = "application/mp21"; break;
		case 'ma'       : $ctype = "application/mathematica"; break;
		case 'mac'      : $ctype = "image/x-macpaint"; break;
		case 'mads'     : $ctype = "application/mads+xml"; break;
		case 'man'      : $ctype = "application/x-troff-man"; break;
		case 'mar'      : $ctype = "application/octet-stream"; break;
		case 'mathml'   : $ctype = "application/mathml+xml"; break;
		case 'mbox'     : $ctype = "application/mbox"; break;
		case 'me'       : $ctype = "application/x-troff-me"; break;
		case 'mesh'     : $ctype = "model/mesh"; break;
		case 'metalink' : $ctype = "application/metalink+xml"; break;
		case 'meta4'    : $ctype = "application/metalink4+xml"; break;
		case 'mets'     : $ctype = "application/mets+xml"; break;
		case 'mid'      : $ctype = "audio/midi"; break;
		case 'midi'     : $ctype = "audio/midi"; break;
		case 'mif'      : $ctype = "application/vnd.mif"; break;
		case 'mods'     : $ctype = "application/mods+xml"; break;
		case 'mov'      : $ctype = "video/quicktime"; break;
		case 'movie'    : $ctype = "video/x-sgi-movie"; break;
		case 'm1v'      : $ctype = "video/mpeg"; break;
		case 'm2v'      : $ctype = "video/mpeg"; break;
		case 'mp2'      : $ctype = "audio/mpeg"; break;
		case 'mp2a'     : $ctype = "audio/mpeg"; break;
		case 'mp21'     : $ctype = "application/mp21"; break;
		case 'mp3'      : $ctype = "audio/mpeg"; break;
		case 'mp3a'     : $ctype = "audio/mpeg"; break;
		case 'mp4'      : $ctype = "video/mp4"; break;
		case 'mp4s'     : $ctype = "application/mp4"; break;
		case 'mpe'      : $ctype = "video/mpeg"; break;
		case 'mpeg'     : $ctype = "video/mpeg"; break;
		case 'mpg'      : $ctype = "video/mpeg"; break;
		case 'mpg4'     : $ctype = "video/mpeg"; break;
		case 'mpga'     : $ctype = "audio/mpeg"; break;
		case 'mrc'      : $ctype = "application/marc"; break;
		case 'mrcx'     : $ctype = "application/marcxml+xml"; break;
		case 'ms'       : $ctype = "application/x-troff-ms"; break;
		case 'mscml'    : $ctype = "application/mediaservercontrol+xml"; break;
		case 'msh'      : $ctype = "model/mesh"; break;
		case 'mxf'      : $ctype = "application/mxf"; break;
		case 'mxu'      : $ctype = "video/vnd.mpegurl"; break;
		case 'nc'       : $ctype = "application/x-netcdf"; break;
		case 'oda'      : $ctype = "application/oda"; break;
		case 'oga'      : $ctype = "application/ogg"; break;
		case 'ogg'      : $ctype = "application/ogg"; break;
		case 'ogx'      : $ctype = "application/ogg"; break;
		case 'omdoc'    : $ctype = "application/omdoc+xml"; break;
		case 'onetoc'   : $ctype = "application/onenote"; break;
		case 'onetoc2'  : $ctype = "application/onenote"; break;
		case 'onetmp'   : $ctype = "application/onenote"; break;
		case 'onepkg'   : $ctype = "application/onenote"; break;
		case 'opf'      : $ctype = "application/oebps-package+xml"; break;
		case 'oxps'     : $ctype = "application/oxps"; break;
		case 'p7c'      : $ctype = "application/pkcs7-mime"; break;
		case 'p7m'      : $ctype = "application/pkcs7-mime"; break;
		case 'p7s'      : $ctype = "application/pkcs7-signature"; break;
		case 'p8'       : $ctype = "application/pkcs8"; break;
		case 'p10'      : $ctype = "application/pkcs10"; break;
		case 'pbm'      : $ctype = "image/x-portable-bitmap"; break;
		case 'pct'      : $ctype = "image/pict"; break;
		case 'pdb'      : $ctype = "chemical/x-pdb"; break;
		case 'pdf'      : $ctype = "application/pdf"; break;
		case 'pki'      : $ctype = "application/pkixcmp"; break;
		case 'pkipath'  : $ctype = "application/pkix-pkipath"; break;
		case 'pfr'      : $ctype = "application/font-tdpfr"; break;
		case 'pgm'      : $ctype = "image/x-portable-graymap"; break;
		case 'pgn'      : $ctype = "application/x-chess-pgn"; break;
		case 'pgp'      : $ctype = "application/pgp-encrypted"; break;
		case 'pic'      : $ctype = "image/pict"; break;
		case 'pict'     : $ctype = "image/pict"; break;
		case 'pkg'      : $ctype = "application/octet-stream"; break;
		case 'png'      : $ctype = "image/png"; break;
		case 'pnm'      : $ctype = "image/x-portable-anymap"; break;
		case 'pnt'      : $ctype = "image/x-macpaint"; break;
		case 'pntg'     : $ctype = "image/x-macpaint"; break;
		case 'pot'      : $ctype = "application/vnd.ms-powerpoint"; break;
		case 'potx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.template"; break;
		case 'ppm'      : $ctype = "image/x-portable-pixmap"; break;
		case 'pps'      : $ctype = "application/vnd.ms-powerpoint"; break;
		case 'ppsx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.slideshow"; break;
		case 'ppt'      : $ctype = "application/vnd.ms-powerpoint"; break;
		case 'pptx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.presentation"; break;
		case 'prf'      : $ctype = "application/pics-rules"; break;
		case 'ps'       : $ctype = "application/postscript"; break;
		case 'psd'      : $ctype = "image/photoshop"; break;
		case 'qt'       : $ctype = "video/quicktime"; break;
		case 'qti'      : $ctype = "image/x-quicktime"; break;
		case 'qtif'     : $ctype = "image/x-quicktime"; break;
		case 'ra'       : $ctype = "audio/x-pn-realaudio"; break;
		case 'ram'      : $ctype = "audio/x-pn-realaudio"; break;
		case 'ras'      : $ctype = "image/x-cmu-raster"; break;
		case 'rdf'      : $ctype = "application/rdf+xml"; break;
		case 'rgb'      : $ctype = "image/x-rgb"; break;
		case 'rm'       : $ctype = "application/vnd.rn-realmedia"; break;
		case 'rmi'      : $ctype = "audio/midi"; break;
		case 'roff'     : $ctype = "application/x-troff"; break;
		case 'rss'      : $ctype = "application/rss+xml"; break;
		case 'rtf'      : $ctype = "text/rtf"; break;
		case 'rtx'      : $ctype = "text/richtext"; break;
		case 'sgm'      : $ctype = "text/sgml"; break;
		case 'sgml'     : $ctype = "text/sgml"; break;
		case 'sh'       : $ctype = "application/x-sh"; break;
		case 'shar'     : $ctype = "application/x-shar"; break;
		case 'sig'      : $ctype = "application/pgp-signature"; break;
		case 'silo'     : $ctype = "model/mesh"; break;
		case 'sit'      : $ctype = "application/x-stuffit"; break;
		case 'skd'      : $ctype = "application/x-koan"; break;
		case 'skm'      : $ctype = "application/x-koan"; break;
		case 'skp'      : $ctype = "application/x-koan"; break;
		case 'skt'      : $ctype = "application/x-koan"; break;
		case 'sldx'     : $ctype = "application/vnd.openxmlformats-officedocument.presentationml.slide"; break;
		case 'smi'      : $ctype = "application/smil"; break;
		case 'smil'     : $ctype = "application/smil"; break;
		case 'snd'      : $ctype = "audio/basic"; break;
		case 'so'       : $ctype = "application/octet-stream"; break;
		case 'spl'      : $ctype = "application/x-futuresplash"; break;
		case 'spx'      : $ctype = "audio/ogg"; break;
		case 'src'      : $ctype = "application/x-wais-source"; break;
		case 'stk'      : $ctype = "application/hyperstudio"; break;
		case 'sv4cpio'  : $ctype = "application/x-sv4cpio"; break;
		case 'sv4crc'   : $ctype = "application/x-sv4crc"; break;
		case 'svg'      : $ctype = "image/svg+xml"; break;
		case 'swf'      : $ctype = "application/x-shockwave-flash"; break;
		case 't'        : $ctype = "application/x-troff"; break;
		case 'tar'      : $ctype = "application/x-tar"; break;
		case 'tcl'      : $ctype = "application/x-tcl"; break;
		case 'tex'      : $ctype = "application/x-tex"; break;
		case 'texi'     : $ctype = "application/x-texinfo"; break;
		case 'texinfo'  : $ctype = "application/x-texinfo"; break;
		case 'tif'      : $ctype = "image/tiff"; break;
		case 'tiff'     : $ctype = "image/tiff"; break;
		case 'torrent'  : $ctype = "application/x-bittorrent"; break;
		case 'tr'       : $ctype = "application/x-troff"; break;
		case 'tsv'      : $ctype = "text/tab-separated-values"; break;
		case 'txt'      : $ctype = "text/plain"; break;
		case 'ustar'    : $ctype = "application/x-ustar"; break;
		case 'vcd'      : $ctype = "application/x-cdlink"; break;
		case 'vrml'     : $ctype = "model/vrml"; break;
		case 'vsd'      : $ctype = "application/vnd.visio"; break;
		case 'vss'      : $ctype = "application/vnd.visio"; break;
		case 'vst'      : $ctype = "application/vnd.visio"; break;
		case 'vsw'      : $ctype = "application/vnd.visio"; break;
		case 'vxml'     : $ctype = "application/voicexml+xml"; break;
		case 'wav'      : $ctype = "audio/x-wav"; break;
		case 'wbmp'     : $ctype = "image/vnd.wap.wbmp"; break;
		case 'wbmxl'    : $ctype = "application/vnd.wap.wbxml"; break;
		case 'wm'       : $ctype = "video/x-ms-wm"; break;
		case 'wml'      : $ctype = "text/vnd.wap.wml"; break;
		case 'wmlc'     : $ctype = "application/vnd.wap.wmlc"; break;
		case 'wmls'     : $ctype = "text/vnd.wap.wmlscript"; break;
		case 'wmlsc'    : $ctype = "application/vnd.wap.wmlscriptc"; break;
		case 'wmv'      : $ctype = "video/x-ms-wmv"; break;
		case 'wmx'      : $ctype = "video/x-ms-wmx"; break;
		case 'wrl'      : $ctype = "model/vrml"; break;
		case 'xbm'      : $ctype = "image/x-xbitmap"; break;
		case 'xdssc'    : $ctype = "application/dssc+xml"; break;
		case 'xer'      : $ctype = "application/patch-ops-error+xml"; break;
		case 'xht'      : $ctype = "application/xhtml+xml"; break;
		case 'xhtml'    : $ctype = "application/xhtml+xml"; break;
		case 'xla'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xlam'     : $ctype = "application/vnd.ms-excel.addin.macroEnabled.12"; break;
		case 'xlc'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xlm'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xls'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xlsx'     : $ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"; break;
		case 'xlsb'     : $ctype = "application/vnd.ms-excel.sheet.binary.macroEnabled.12"; break;
		case 'xlt'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xltx'     : $ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.template"; break;
		case 'xlw'      : $ctype = "application/vnd.ms-excel"; break;
		case 'xml'      : $ctype = "application/xml"; break;
		case 'xpm'      : $ctype = "image/x-xpixmap"; break;
		case 'xsl'      : $ctype = "application/xml"; break;
		case 'xslt'     : $ctype = "application/xslt+xml"; break;
		case 'xul'      : $ctype = "application/vnd.mozilla.xul+xml"; break;
		case 'xwd'      : $ctype = "image/x-xwindowdump"; break;
		case 'xyz'      : $ctype = "chemical/x-xyz"; break;
		case 'zip'      : $ctype = "application/zip"; break;
		default         : $ctype = "application/force-download";
	endswitch;
	if( wp_is_mobile() ) {
		$ctype = 'application/octet-stream';
	}
	return $ctype;
}

function wpec_lic_is_func_disabled( $function ) {
	$disabled = explode( ',',  ini_get( 'disable_functions' ) );
	return in_array( $function, $disabled );
}

function wpec_lic_readfile_chunked( $file, $retbytes = true ) {
	$chunksize = 1024 * 1024;
	$buffer    = '';
	$cnt       = 0;
	$handle    = @fopen( $file, 'r' );
	if ( $size = @filesize( $file ) ) {
		header("Content-Length: " . $size );
	}
	if ( false === $handle ) {
		return false;
	}
	while ( ! @feof( $handle ) ) {
		$buffer = @fread( $handle, $chunksize );
		echo $buffer;
		if ( $retbytes ) {
			$cnt += strlen( $buffer );
		}
	}
	$status = @fclose( $handle );
	if ( $retbytes && $status ) {
		return $cnt;
	}
	return $status;
}

function wpec_lic_deliver_download( $file = '', $redirect = false ) {
	if( $redirect ) {
		header( 'Location: ' . $file );
	} else {
		// Read the file and deliver it in chunks
		wpec_lic_readfile_chunked( $file );
	}
}

/**
 * Return license data based on license_key
 * @param string $license_key
 *
 * @return bool|null|string
 */
function wpec_lic_get_license_by_key( $license_key ) {
	global $wpdb;
	
	static $cache = array();
	
	if( isset( $cache[ $license_key ] ) ) {
		return $cache[ $license_key ];
	}
	
	$license = $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}wpec_licensing_licenses` WHERE `license_key` = '".$license_key."' LIMIT 1", ARRAY_A );
	
	if ( $license['id'] != NULL ) {
		$cache[ $license_key ] = $license;
		return $license;
	}
	
	return false;
}

function wpec_lic_get_license_status( $license_key ) {
	global $wpdb;
	
	$expiration = time();
	
	if ( empty( $license_key ) ) {
		return 'invalid';
	}
	
	$license = wpec_lic_get_license_by_key( $license_key );
	
	if ( ! $license['id'] ) {
		return 'invalid';
	}
	
	// grab info about the license
	$license_expires    = strtotime( $license['expiration_date'] );
	$license_key        = $license['license_key'];
	$license_status     = $license['active'];
	$license_prod_id	= $license['product_id'];
	$item_name          = html_entity_decode( $license['product_name'] );
	$url                = ! empty( $args['url'] ) ? $args['url'] : '';

	$is_lifetime_license = $license['expiration_date'] != 'lifetime' ? false : true;
	if ( ! $is_lifetime_license && $expiration > $license_expires ) {
		return 'expired'; // this license has expired
	}
	if ( '1' != $license_status ) {
		return 'disabled'; // this license is not active.
	}
	return 'valid'; // license still active
}

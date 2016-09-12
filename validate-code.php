<?php
	include '../../../wp-load.php';

	$code_id = crc32(strtoupper($_GET['giftcardcodes_input']));
	$result = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(code_id) as code_count FROM " . $wpdb->prefix . "giftcardcodes WHERE code_id = %d AND user_id IS NULL",
			array(
				$code_id
			)
		)
	);
	if($result->code_count == 0) {
		$referrer = explode('?', $_SERVER['HTTP_REFERER']);
		wp_redirect( $referrer[0] . '?error' );
		exit;
	}
	wp_redirect( '/wp-login.php?action=register&giftcardcodes_id=' . $code_id );
	exit;
?>
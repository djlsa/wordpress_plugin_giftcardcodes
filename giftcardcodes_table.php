<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GiftCardCodes_Table extends WP_List_Table {

	function __construct(){
		global $status, $page;
		parent::__construct( array(
			'singular' => 'code',
			'plural' => 'codes',
			'ajax' => false
		) );
	}

	function column_default($item, $column_name){
		$data = $item->$column_name;
		if($column_name == 'user')
			$data = '<a href="user-edit.php?user_id=' . $item->ID . '">' . $item->user . '</a>';
		else if(strstr($column_name, 'date') !== FALSE) {
			if($data != 0)
				$data = date('Y-m-d H:i:s', $data);
			else
				$data = "";
		}
		return $data;
	}

	function get_columns(){
		$columns = array(
			'code' => 'Code',
			'stockist' => 'Stockist',
			'date_created' => 'Date generated',
			'date_redeemed' => 'Date redeemed',
			'user' => 'User'
		);
		return $columns;
	}

	function prepare_items() {
		global $wpdb;

		$per_page = 20;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$current_page = $this->get_pagenum();

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(code_id) AS code_count FROM " . $wpdb->prefix . "giftcardcodes LEFT JOIN " . $wpdb->prefix . "users AS user ON user_id = user.ID",
				array(
				)
			)
		);

		$total_items = $data->code_count;

		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, stockist, date_created, date_redeemed, user.user_login user, user.ID AS ID FROM " . $wpdb->prefix . "giftcardcodes LEFT JOIN " . $wpdb->prefix . "users AS user ON user_id = user.ID ORDER BY date_redeemed DESC, date_created DESC LIMIT %d, %d",
				array(
					($current_page-1)*$per_page,
					$per_page
				)
			)
		);

		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil($total_items/$per_page)
		) );
	}
}
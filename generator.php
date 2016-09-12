<?php

include 'PHPExcel.php';
include 'PHPExcel/Writer/Excel2007.php';
include '../../../wp-load.php';

	function generate($howmany) {
		$codes = array();
		for($i = 0; $i < $howmany; $i++) {
			$fullcode = '';
			do {
				$fullcode = '';
				for($j = 0; $j < 3; $j++) {
					$code = '';
					do {
						$v = '' . time() . ' ' + rand();
						$code = substr(strtoupper(base_convert(crc32($v), 10, 36)), 0, 6);
					} while(strpos($code, '0') !== FALSE || strpos($code, 'O') !== FALSE || strpos($code, 'I') !== FALSE);
					$fullcode .= $code;
					if($j < 2)
						$fullcode .= "-";
				}
			} while(in_array($fullcode, $codes));
			$codes[] = $fullcode;
		}
		return $codes;
	}

	function _get_temp_dir() {
		if (file_exists('/dev/shm') ) { return '/dev/shm'; }
		if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
		if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
		if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
		$tempfile=tempnam(__FILE__,'');
		if (file_exists($tempfile)) {
			unlink($tempfile);
			return realpath(dirname($tempfile));
		}
		return null;
	}

	function write_excel($codes, $filename) {
		global $wpdb;
		$objPHPExcel = new PHPExcel();
		$n = count($codes);
		$objPHPExcel->setActiveSheetIndex(0);
		for($i = 0; $i < $n; $i++) {
			$objPHPExcel->getActiveSheet()->SetCellValue('A' . $i, $codes[$i]);
		}
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
		$file = _get_temp_dir() . '/' . $filename . '.xlsx';
		try {
			$objWriter->save($file);
		} catch (Exception $e) {
		}
		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($file));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));
			ob_clean();
			flush();
			readfile($file);
			@unlink($file);
			exit;
		} else {
			$values = '';
			for($i = 0; $i < $n; $i++) {
				if($i > 0)
					$values .= ',';
				$values .= crc32($codes[$i]);
			}
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "giftcardcodes WHERE code_id IN(" . $values . ")",
					array(
					)
				)
			);
			echo "ERROR CREATING EXCEL FILE";
			exit;
		}
	}

	function write_database($codes, $stockist) {
		global $wpdb;
		$n = count($codes);
		$dup = 0;
		do {
			$crcs = '';
			for($i = 0; $i < $n; $i++) {
				if($i > 0)
					$crcs .= ',';
				$crcs = crc32($codes[$i]);
			}
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(code_id) as code_count FROM " . $wpdb->prefix . "giftcardcodes WHERE code_id IN (" . $crcs . ")",
					array(
					)
				)
			);
			$dup = $result->code_count;
			if($dup > 0)
				$codes = generate($n);
		} while($dup > 0);
		$date = time();
		$values = 'VALUES';
		for($i = 0; $i < $n; $i++) {
			if($i > 0)
				$values .= ',';
			$values .= '(' . crc32($codes[$i]) . ', "' . $codes[$i] . '", "' . $stockist . '", ' . $date . ')';
		}
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"INSERT INTO " . $wpdb->prefix . "giftcardcodes(code_id, code, stockist, date_created) " . $values,
				array(
				)
			)
		);
		return $codes;
	}

	function is_int2($x) {
		return (is_numeric($x) ? intval($x) == $x : false);
	}

	if(is_int2($_GET['n']) === FALSE || $_GET['n'] <= 0) {
		echo 'ERROR';
		exit();
	}
	$codes = generate($_GET['n']);
	$stockist= $_GET['s'];
	$codes = write_database($codes, $stockist);
	$file = date('Y-m-d_H-i-s') . '_' . $stockist;
	write_excel($codes, $file);
?>
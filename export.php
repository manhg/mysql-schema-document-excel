<?php
$dbname = "";
$user = "root";
$pass = "";

/////
ini_set('memory_limit', '1G');
require_once __DIR__ . '/Classes/PHPExcel.php';
$conn = new PDO("mysql:host=127.0.0.1;dbname=$dbname;charset=utf8", $pass, $pass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
* Credit: Adminer
* Get information about fields
* @param string
* @return array array($name => array("field" => , "full_type" => , "type" => , "length" => , "unsigned" => , "default" => , "null" => , "auto_increment" => , "on_update" => , "collation" => , "privileges" => , "comment" => , "primary" => ))
*/
function fields($table) {
    $return = array();
    foreach (get_rows("SHOW FULL COLUMNS FROM `$table`") as $row) {
        preg_match('~^([^( ]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
        $return[$row["Field"]] = array(
            "field" => $row["Field"],
            "full_type" => $row["Type"],
            "type" => $match[1],
            "length" => @$match[2],
            "unsigned" => isset($match[4]) ? ltrim($match[3] . $match[4]) : '',
            "default" => ($row["Default"] != "" || preg_match("~char|set~", $match[1]) ? $row["Default"] : null),
            "null" => ($row["Null"] == "YES"),
            "auto_increment" => ($row["Extra"] == "auto_increment"),
            "on_update" => (preg_match('~^on update (.+)~i', $row["Extra"], $match) ? $match[1] : ""), //! available since MySQL 5.1.23
            "collation" => $row["Collation"],
            "privileges" => array_flip(preg_split('~, *~', $row["Privileges"])),
            "comment" => $row["Comment"],
            "primary" => ($row["Key"] == "PRI"),
        );
    }
    return $return;
}

/* Credit: Adminer */
function indexes($table) {
	$return = array();
	foreach (get_rows("SHOW INDEX FROM `$table`") as $row) {
		$return[$row["Key_name"]]["type"] = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
		$return[$row["Key_name"]]["columns"][] = $row["Column_name"];
		$return[$row["Key_name"]]["lengths"][] = $row["Sub_part"];
		$return[$row["Key_name"]]["descs"][] = null;
	}
	return $return;
}

/* Credit: Adminer */
function get_rows($query) {
    global $conn;
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch()) {
			$return[] = $row;
		}
	}
	return $return;
}

/* Credit: Adminer */
function tables($dbname) {
	$return = array();
    foreach (get_rows("SELECT table_name FROM information_schema.tables WHERE table_schema = '$dbname'") as $row) {
		$return[] = $row['table_name'];
	}
	return $return;
}

function text_trans($col_name) {
	static $map = array();
	if (empty($map)) {
		$trans = explode("\n", file_get_contents(__DIR__ .'/trans.txt'));
		foreach ($trans as $line) {
			$pair = preg_split('/\s+/', $line);
			if (count($pair) == 2) {
				$map[$pair[0]] = $pair[1];
			}
		}
	}
    preg_match_all('/([a-z]+|[0-9]+)/i', $col_name, $matches);
    $translated = '';
    foreach ($matches[1] as $m) {
        $translated .= isset($map[$m]) ? $map[$m] : $m;
    }
    return $translated;
}

function phpxls_border($where) {
	return array(
		'borders' => array(
			$where => array(
				'style' => PHPExcel_Style_Border::BORDER_THIN,
			),
		),
	);
}

$workbook = PHPExcel_IOFactory::load('template.xlsx');
$tmpl_sheet = $workbook->getSheetByName('_template_');

$tables = tables($dbname);

$table_no = 1;
$table_list = [];
/* Customize colums and values */
foreach ($tables as $table) {
	if (strpos($table, '_delete') !== FALSE) continue;
	$BORDER_ALL = phpxls_border('allborders');
	$BORDER_OUTLINE = phpxls_border('outline');
	$worksheet = clone $tmpl_sheet;
	$worksheet->setTitle($table)
		->setCellValue('C1', $table);
	# $table_list[] = array($table_no, $table);
	print_r(sprintf("%s\t%s\n", $table_no, $table));
	$table_no++;
	$workbook->addSheet($worksheet);
	$fields = fields($table);
	$worksheet->insertNewRowBefore(11, count($fields));
	$index = 0;
	$worksheet->setCellValue('H1', date('Y-m-d'));
	$worksheet->setCellValue('I3', 'MyISAM');
	foreach ($fields as $name => $detail) {
		$row = 9 + ++$index;
		$worksheet->setCellValue('A' . $row, $index)
			->setCellValue('B'. $row, text_trans($detail['field']))
			->setCellValue('C'. $row, trim($detail['field']))
			->setCellValue('D'. $row, $detail['type'])
			->setCellValue('E'. $row, $detail['length'])
			->setCellValue('F'. $row, $detail['null'] ? '○' : '')
			->setCellValue('G'. $row, $detail['default'] ?: '')
			->setCellValue('H'. $row, $detail['auto_increment'] ? '○' : '')
			;
		$worksheet->getStyle("A{$row}:H{$row}")->applyFromArray($BORDER_ALL);
		$worksheet->getStyle("I{$row}:J{$row}")->applyFromArray($BORDER_OUTLINE);
	}
	$index_count = 1;
	foreach (indexes($table) as $name => $detail) {
		$row = 13 + ++$index;
		$worksheet
			->setCellValue('A' . $row, $index_count++)
			->setCellValue('B'. $row, $detail['type'])
			->setCellValue('C'. $row, $name)
			->setCellValue('G'. $row, implode(',', $detail['columns']))
			;
		$worksheet->getStyle("A{$row}:B{$row}")->applyFromArray($BORDER_ALL);
		$worksheet->getStyle("C{$row}:F{$row}")->applyFromArray($BORDER_OUTLINE);
		$worksheet->getStyle("G{$row}:J{$row}")->applyFromArray($BORDER_OUTLINE);
	}
}
$tmpl_index = $workbook->getIndex($tmpl_sheet);
$workbook->removeSheetByIndex($tmpl_index);
$writer = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');
$writer->save(__DIR__. sprintf("/output/$dbname-%s.xlsx", date('Y.m.d')));
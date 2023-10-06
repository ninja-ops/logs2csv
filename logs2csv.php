<?php

if (count(get_included_files()) == 1) {
  echo "no direct call\n";
  exit(1);
}

function arguments($argv) {
  $_ARG = array();
  foreach ($argv as $arg) {
    if (preg_match('#^-{1,2}([a-zA-Z0-9\-]*)=?(.*)$#', $arg, $matches)) {
      $key = $matches[1];
      switch ($matches[2]) {
        case '':
        case 'true':
          $arg = true;
          break;
        case 'false':
          $arg = false;
          break;
        default:
          $arg = $matches[2];
      }
      $_ARG[$key] = $arg;
    } else {
      $_ARG['input'][] = $arg;
    }
  }
  return $_ARG;
}

$argz = arguments($argv);
$prog = str_replace(".php", "", basename(__FILE__));

if (isset($argz["help"])) {
  echo $prog . " " . substr(md5(file_get_contents(__FILE__)), 0, 4) . "

this is a program to re-organize log files from an appliance like symantec proxy, where in the comments upfront the fields are defined via '#Fields: field1 field2'

you may ommit via the output-fields parameter the named fields you want as output

usage ./" . $prog . " filename.log

options

  --help
          this output

  --fields=\"field1,field2,field3\"
          name fields if not ommited

  --output-fields=\"field1,field2\"
          fields you wanna see in the output, defaults to all fields found, names are comma separated

  --dont-output-field-names-alphanum=\"1\"
          do not remove all special chars from field names beside alphanum

  --filter-field-name=\"field-name-you-wanne-filter-for\"

  --filter-field-value=\"field-value-to-ignore\"

  --output-separator=\"|\"
          custom separator for output, default \"|\"

  --limit=\"x\"
          limits output to int() x lines\"|\"

  --list-fields
          output some informations about the fields we found from the header

";
  exit(1);
}

if (!isset($argz["input"][1])) {
  echo "no file submitted\n";
  exit(1);
}

$filename = $argz["input"][1];

if ($filename == "STDIN") {
  $filename = "php://stdin";
} else {
  if (!file_exists($filename)) {
    echo "file '" . $filename . "' does not exists\n";
    exit(1);
  }
}


$fields = array();
$separator = "|";
if (isset($argz["output-separator"])) {
  $separator = substr($argz["output-separator"], 0, 1);
}

$output_fields = array();
if (isset($argz["output-fields"])) {
  $output_fields = explode(",", trim($argz["output-fields"]));
  foreach($output_fields as $key=>$value) {
    $output_fields[$key] = trim($value);
  }
}

$filter_field = false;
$filter_field_name = "";
$filter_field_value = "";
if (isset($argz["filter-field-name"])) {
  $filter_field_name = $argz["filter-field-name"];
}

if (isset($argz["filter-field-value"])) {
  $filter_field_value = $argz["filter-field-value"];
}

if (isset($argz["debug"])) {
  print_r($fields);
  die();
}

$fp = fopen($filename, "r");
$read_head = true;

if (isset($argz["fields"])) {
  $read_head = false;
  $fields = explode(",", $argz["fields"]);
}

$head_sent = false;
$items = 0;
while(!feof($fp)) {
  $line = trim(fgets($fp));
  if (substr($line, 0, 1) != "#") {
    $read_head = false;
    if (count($fields) == 0) {
      echo "no field definitions found\n";
      exit();
    }
    if (!$head_sent) {
      $header_output_fields = array();
      foreach($output_fields as $output_field) {
        if (!isset($argz["dont-output-field-names-alpanum"])) {
          $output_field = preg_replace( '/[\W]/', '', $output_field);
        }
        $header_output_fields[] = $output_field;
      }
      echo implode($separator, $header_output_fields) . "\n";
      $head_sent = true;
    }
  }

  if ($read_head) {
    // read head
    if (strpos(" " . $line, "#Fields: ") == 1) {
      $fields = explode(" ", $line);
      array_shift($fields);
      $idx_fields = array_flip($fields);
      if (count($output_fields) > 0) {
        foreach($output_fields as $output_field) {
          if (!in_array($output_field, $fields)) {
            echo "field '" . $output_field . "' is not available in fields\n";
            exit(1);
          }
        }
        if ($filter_field_name != "") {
          if (!in_array($filter_field_name, $fields)) {
            echo "field '" . $filter_field_name . "' is not available in fields\n";
            exit(1);
          }
        }
      } else {
        $output_fields = $fields;
      }
      if (isset($argz["list-fields"])) {
        foreach($fields as $key=>$field) {
          echo $key . ": " . $field ."\n";
        }
        die();
      }
    }
  } else {
    // read data
    if ($line == "") {
      continue;
    }
    $chunks = explode("\"", $line);
    $pos = 0;
    $data = array();
    foreach($chunks as $key=>$value) {
      if ($key % 2 == 0) {
        $value = trim($value);
        if ($value != "") {
          $data_chunks = explode(" ", $value);
          foreach($data_chunks as $data_chunks_value) {
            $data[] = trim($data_chunks_value);
            $pos++;
          }
        }
      } else {
        $data[] = trim($value);
        $pos++;
      }
    }
    $output_data = $data;
    $filter = false;
    if (count($output_fields) > 0) {
      $output_data = array();
      foreach($output_fields as $output_field) {
        $idx = $idx_fields[$output_field];
        $output_data[] = $data[$idx];
      }
    }

    if ($filter_field_name != "") {
      $idx = $idx_fields[$filter_field_name];
      if ($data[$idx] == $filter_field_value) {
        $filter = true;
      }
    } else {
      if ($filter_field_value != "") {
        foreach($data as $value) {
          if ($filter_field_value == $value) {
            $filter = true;
            continue;
          }
        }
      }
    }

    if (!$filter) {
      $items++;
      echo implode($separator, $output_data);
      echo "\n";
    }
    if (isset($argz["limit"])) {
      if ($argz["limit"] <= $items) {
        exit(0);
      }
    }
  }
}
fclose($fp);

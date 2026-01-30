<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

function safe_field(string $s, int $maxLen = 64): string {
  $s = trim($s);
  $s = str_replace([",", "\r", "\n"], [" ", " ", " "], $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return mb_substr($s, 0, $maxLen);
}

$name  = isset($_POST['name'])  ? safe_field((string)$_POST['name'], 32) : '';
$time  = isset($_POST['time'])  ? safe_field((string)$_POST['time'], 16) : '';
$frames = isset($_POST['frames']) ? safe_field((string)$_POST['frames'], 200000) : '';
$masks  = isset($_POST['masks'])  ? safe_field((string)$_POST['masks'], 200000) : '';

if ($name === '') $name = 'anonymous';

// timeは "12.34" のような形式想定（表示・一致に使うので文字列で保持）
if (!preg_match('/^\d+(\.\d{1,2})?$/', $time)) {
  http_response_code(400);
  echo "Invalid time\n";
  exit;
}

// frames/masks は "1/2/3" 形式（空もOK：ただし基本は入る）
if ($frames !== '' && !preg_match('/^\d+(\/\d+)*$/', $frames)) {
  http_response_code(400);
  echo "Invalid frames\n";
  exit;
}
if ($masks !== '' && !preg_match('/^\d+(\/\d+)*$/', $masks)) {
  http_response_code(400);
  echo "Invalid masks\n";
  exit;
}

$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/race_records.txt';

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0777, true);
}

// 追記
$line = date("y/m/d H:i:s") . "," . $name . "," . $time . "," . $frames . "," . $masks . "\n";
$fp = fopen($dataFile, "ab");
if ($fp === false) {
  http_response_code(500);
  echo "Failed to open data file\n";
  exit;
}
flock($fp, LOCK_EX);
fwrite($fp, $line);
flock($fp, LOCK_UN);
fclose($fp);

echo "OK\n";

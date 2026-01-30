<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ランキング再生</title>
  <style>
    body {
      width: 1000px;
      margin: auto;
      background-color: #f0f8ff;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans JP", sans-serif;
      text-align: center;
    }
    h2 {
      background: #b0dcfa;
      padding: 0.5em;
      color: white;
      border-radius: 0.5em;
    }
    canvas {
      display: block;
      margin: 0 auto;
      background: #a0a0a0;
    }
    .stats {
      font-size: 1.2rem;
      margin: 10px 0;
    }
    .stats span {
      display: inline-block;
      min-width: 72px;
    }
    .note { color: #c00; }
  </style>
</head>

<body align="center">
  <h2>レースゲーム（ランキング再生）</h2>

  <div class="stats">
    タイム：<span id="time" style="color:green;">0.00</span>
    スピード：<span id="speed" style="color:red;">0</span> km/h
    残り：<span id="remaining" style="color:blue;">500</span> km
  </div>

  <table border="1" cellspacing="0" align="center"><tr><td>
    <canvas id="gameCanvas" width="500" height="500"></canvas>
  </td></tr></table>

  <div class="note">※再生中は音が鳴ります</div>
  <br>
  <a href="index.html">戻る</a>

<?php
declare(strict_types=1);

function safe_get(string $key): string {
  $v = $_GET[$key] ?? '';
  $v = trim((string)$v);
  $v = str_replace(["\r", "\n"], "", $v);
  return $v;
}

$targetTime = safe_get('time'); // ranking.php で出した time をそのまま渡す
$dataFile = __DIR__ . '/data/race_records.txt';

$found = null;

if (file_exists($dataFile) && $targetTime !== '') {
  $lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $parts = explode(',', $line, 5);
    if (count($parts) < 5) continue;

    $date = $parts[0];
    $name = $parts[1] ?? 'anonymous';
    $time = $parts[2] ?? '';
    $frames = $parts[3] ?? '';
    $masks = $parts[4] ?? '';

    if ($time === $targetTime) {
      $found = [
        'date' => $date,
        'name' => $name,
        'time' => $time,
        'frames' => $frames,
        'masks' => $masks
      ];
      break;
    }
  }
}
?>
<script>
"use strict";

const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

const elTime = document.getElementById("time");
const elSpeed = document.getElementById("speed");
const elRemaining = document.getElementById("remaining");

// ====== Assets ======
const images = {
  player: new Image(),
  playerLeft: new Image(),
  playerRight: new Image(),
  enemyGreen: new Image(),
  enemyBlue: new Image(),
};
images.player.src = "assets/images/player-car.png";
images.playerLeft.src = "assets/images/player-car-left.png";
images.playerRight.src = "assets/images/player-car-right.png";
images.enemyGreen.src = "assets/images/enemy-car-green.png";
images.enemyBlue.src = "assets/images/enemy-car-blue.png";

const bgm = new Audio("assets/audio/bgm-race.mp3");
bgm.loop = true;

function createPolyphonicSfx(src, voices = 4) {
  const pool = Array.from({ length: voices }, () => new Audio(src));
  let idx = 0;
  return {
    play() {
      pool[idx].currentTime = 0;
      pool[idx].play();
      idx = (idx + 1) % pool.length;
    }
  };
}
const sfxAccelerate = createPolyphonicSfx("assets/audio/sfx-accelerate.mp3");
const sfxBrake      = createPolyphonicSfx("assets/audio/sfx-brake.mp3");
const sfxCrash      = createPolyphonicSfx("assets/audio/sfx-crash.mp3");

// ====== RNG ======
const rngState = { x:123456789, y:362436069, z:521288629, w:88675123 };
const DEFAULT_SEED = 88675123;

function rngInit(seed) {
  rngState.x = 123456789;
  rngState.y = 362436069;
  rngState.z = 521288629;
  rngState.w = (seed === undefined) ? DEFAULT_SEED : seed;
}
function rng() {
  let t = (rngState.x ^ (rngState.x << 11));
  rngState.x = rngState.y;
  rngState.y = rngState.z;
  rngState.z = rngState.w;
  rngState.w = (rngState.w ^ (rngState.w >>> 19)) ^ (t ^ (t >>> 8));
  return (rngState.w >>> 0) / 4294967296;
}

// ====== Playback Log (PHP埋め込み) ======
const playbackLog = (function() {
<?php if ($found === null): ?>
  return null;
<?php else:
  $frames = $found['frames'];
  $masks = $found['masks'];
  $frameArr = ($frames !== '') ? explode('/', $frames) : [];
  $maskArr  = ($masks !== '') ? explode('/', $masks) : [];
?>
  const log = {};
<?php
  $n = min(count($frameArr), count($maskArr));
  for ($i = 0; $i < $n; $i++) {
    $f = preg_replace('/\D/', '', $frameArr[$i]);
    $m = preg_replace('/\D/', '', $maskArr[$i]);
    if ($f === '' || $m === '') continue;
    echo "log[$f] = $m;\n";
  }
?>
  return log;
<?php endif; ?>
})();

function clamp(val, min, max){ return Math.max(min, Math.min(max, val)); }
function formatSeconds(sec){ return (Math.round(sec * 100) / 100).toFixed(2); }

let playerX, playerY, playerSpeed;
let enemies;
let remainingDistance;
let isCrashed;
let tick;
let startTimeMs;
let timerId = null;
let crashSoundPlayed = false;

// bitmask: 1=LEFT, 2=UP, 4=RIGHT, 8=DOWN
const INPUT_MASK = { LEFT: 1, UP: 2, RIGHT: 4, DOWN: 8 };

function drawRoad() {
  ctx.fillStyle = "#a0a0a0";
  ctx.fillRect(0, 0, 500, 500);

  ctx.fillStyle = "#ffffff";
  ctx.fillRect(245, (50000 - remainingDistance) % 750 - 250, 10, 250);

  if (remainingDistance > 49925) {
    ctx.fillStyle = "#ffffff";
    ctx.font = "40px 'ＭＳ ゴシック'";
    ctx.textAlign = "left";
    ctx.textBaseline = "bottom";
    ctx.fillText("START", 0, 50350 - remainingDistance);
    ctx.fillRect(0, 50350 - remainingDistance, 500, 10);
  }
}

function drawPlayer(mask) {
  const left = (mask & INPUT_MASK.LEFT) !== 0;
  const right = (mask & INPUT_MASK.RIGHT) !== 0;

  if (left) ctx.drawImage(images.playerLeft, playerX, playerY);
  else if (right) ctx.drawImage(images.playerRight, playerX, playerY);
  else ctx.drawImage(images.player, playerX, playerY);
}

function spawnEnemies() {
  if (rng() < 0.001 * playerSpeed + 0.01) {
    const kind = (rng() < 0.5) ? images.enemyGreen : images.enemyBlue;
    enemies.push({ kind, x: rng() * 470, y: -32, size: 16, speed: rng() * 50 });
  }
  if (rng() < 0.01) {
    enemies.push({ kind: images.enemyGreen, x: rng() * 470, y: 500, size: 16, speed: rng() * 50 });
  }
}

function moveEnemies() {
  for (let i = 0; i < enemies.length; i++) {
    const e = enemies[i];
    if (playerX > e.x) e.x += (rng() - 0.3) * 3;
    else e.x -= (rng() - 0.3) * 3;

    e.y += (playerSpeed - e.speed) / 10;

    if (e.y <= -100 || e.y >= 2000) {
      enemies.splice(i, 1);
      i--;
    }
  }
}

function drawEnemiesAndCheckCollision() {
  for (const e of enemies) {
    ctx.drawImage(e.kind, e.x, e.y);

    const dx = (playerX - e.x);
    const dy = (playerY - e.y);
    if ((dx * dx + dy * dy) < (14 + e.size) * (14 + e.size)) isCrashed = true;
  }
}

function renderCrashMessage() {
  ctx.fillStyle = "#000000";
  ctx.font = "60px 'ＭＳ ゴシック'";
  ctx.textAlign = "center";
  ctx.textBaseline = "bottom";
  ctx.fillText("衝突しました", 250, 250);
}

function updateStats(elapsedSeconds) {
  elSpeed.textContent = String(playerSpeed);
  elTime.textContent = formatSeconds(elapsedSeconds);
  elRemaining.textContent = String(Math.max(0, Math.floor(remainingDistance / 100)));
}

function stopLoop() {
  if (timerId !== null) {
    clearTimeout(timerId);
    timerId = null;
  }
}

function startPlayback() {
  if (!playbackLog) {
    drawRoad();
    ctx.fillStyle = "#000000";
    ctx.font = "22px 'ＭＳ ゴシック'";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText("指定の記録が見つかりません", 250, 250);
    return;
  }

  playerX = 234;
  playerY = 360;
  playerSpeed = 0;
  enemies = [];
  remainingDistance = 50000;
  isCrashed = false;
  crashSoundPlayed = false;

  tick = 0;
  startTimeMs = Date.now();

  rngInit(DEFAULT_SEED);
  bgm.play();
  loop();
}

function loop() {
  if (isCrashed) {
    drawRoad();
    renderCrashMessage();
    if (!crashSoundPlayed) {
      crashSoundPlayed = true;
      sfxCrash.play();
      bgm.pause();
      bgm.currentTime = 0;
    }
    return;
  }

  const mask = playbackLog[tick] || 0;

  // 入力適用（再生）
  if (mask & INPUT_MASK.LEFT)  playerX = clamp(playerX - 4, 0, 470);
  if (mask & INPUT_MASK.UP)    { playerSpeed = clamp(playerSpeed + 1, -1, 100); sfxAccelerate.play(); }
  if (mask & INPUT_MASK.RIGHT) playerX = clamp(playerX + 4, 0, 470);
  if (mask & INPUT_MASK.DOWN)  { playerSpeed = clamp(playerSpeed - 2, -1, 100); sfxBrake.play(); }

  tick++;

  const elapsedSeconds = (Date.now() - startTimeMs) / 1000;
  updateStats(elapsedSeconds);

  remainingDistance -= playerSpeed;
  if (remainingDistance <= 0) {
    remainingDistance = 0;
    updateStats(elapsedSeconds);
    bgm.pause();
    bgm.currentTime = 0;
    return;
  }

  drawRoad();
  drawPlayer(mask);

  moveEnemies();
  spawnEnemies();
  drawEnemiesAndCheckCollision();

  timerId = setTimeout(loop, 20);
}

startPlayback();
</script>
</body>
</html>

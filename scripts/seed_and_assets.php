<?php
declare(strict_types=1);

/**
 * CebuTech Hub - CLI Database Reset & SVG Product Image Generator
 * Usage: php scripts/seed_and_assets.php [--reset-db]
 */

require_once __DIR__ . '/../app/Core/Repository.php';

$imgDir = PUBLIC_PATH . '/assets/images';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0775, true);
}

$items = [
    ['file' => 'prod-k75-keyboard.svg', 'bg1' => '#0f172a', 'bg2' => '#1e293b', 'accent' => '#38bdf8', 'badge' => '75% HOT-SWAP', 'title' => 'CubeTech K75', 'sub' => 'Mechanical Keyboard', 'icon' => 'keyboard'],
    ['file' => 'prod-k65-keyboard.svg', 'bg1' => '#111827', 'bg2' => '#1f2937', 'accent' => '#818cf8', 'badge' => '65% WIRELESS', 'title' => 'CubeTech K65', 'sub' => 'Low-Profile Keyboard', 'icon' => 'keyboard'],
    ['file' => 'prod-m1-mouse.svg', 'bg1' => '#064e3b', 'bg2' => '#0f766e', 'accent' => '#34d399', 'badge' => '57° ERGONOMIC', 'title' => 'CubeTech M1 Pro', 'sub' => 'Vertical Wireless Mouse', 'icon' => 'mouse'],
    ['file' => 'prod-aerolight-mouse.svg', 'bg1' => '#31102f', 'bg2' => '#4c1d95', 'accent' => '#f472b6', 'badge' => '55G ULTRA-LIGHT', 'title' => 'AeroLight 55g', 'sub' => '26K DPI E-Sports Mouse', 'icon' => 'mouse'],
    ['file' => 'prod-nova-headphones.svg', 'bg1' => '#1e1b4b', 'bg2' => '#312e81', 'accent' => '#a78bfa', 'badge' => 'HYBRID ANC', 'title' => 'CubeTech Nova', 'sub' => 'Studio ANC Headphones', 'icon' => 'headphones'],
    ['file' => 'prod-pulse-mic.svg', 'bg1' => '#1c1917', 'bg2' => '#292524', 'accent' => '#fb923c', 'badge' => '24-BIT / 96KHZ', 'title' => 'CubeTech Pulse', 'sub' => 'USB-C Condenser Mic', 'icon' => 'mic'],
    ['file' => 'prod-clarity-soundbar.svg', 'bg1' => '#0f172a', 'bg2' => '#334155', 'accent' => '#22d3ee', 'badge' => 'STEREO DAC', 'title' => 'CubeTech Clarity', 'sub' => 'Dual Desktop Soundbar', 'icon' => 'speaker'],
    ['file' => 'prod-sonicbuds.svg', 'bg1' => '#042f2e', 'bg2' => '#134e4a', 'accent' => '#2dd4bf', 'badge' => 'LOW-LATENCY TWS', 'title' => 'SonicBuds Pro', 'sub' => 'ENC Wireless Earbuds', 'icon' => 'headphones'],
    ['file' => 'prod-usbc-cable.svg', 'bg1' => '#172554', 'bg2' => '#1e3a8a', 'accent' => '#60a5fa', 'badge' => '60W PD BRAIDED', 'title' => 'USB-C Cable 1.5m', 'sub' => 'PHP 120.00 Assessment Item', 'icon' => 'cable'],
    ['file' => 'prod-keycap-kit.svg', 'bg1' => '#27272a', 'bg2' => '#3f3f46', 'accent' => '#facc15', 'badge' => 'MAINTENANCE KIT', 'title' => 'Puller & Brush Kit', 'sub' => 'PHP 85.50 Assessment Item', 'icon' => 'tool'],
    ['file' => 'prod-usbc-hub.svg', 'bg1' => '#090d16', 'bg2' => '#1e293b', 'accent' => '#38bdf8', 'badge' => '7-IN-1 4K@60HZ', 'title' => 'Aluminum USB-C Hub', 'sub' => '100W PD Pass-Through', 'icon' => 'hub'],
    ['file' => 'prod-legacy-trackball.svg', 'bg1' => '#1f2937', 'bg2' => '#374151', 'accent' => '#9ca3af', 'badge' => 'ARCHIVED / INACTIVE', 'title' => 'Legacy Trackball', 'sub' => 'Discontinued Model', 'icon' => 'mouse'],
];

foreach ($items as $it) {
    $acc = $it['accent'];
    switch ($it['icon']) {
        case 'keyboard':
            $iconGraphic = '<rect x="130" y="125" width="340" height="125" rx="14" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<rect x="150" y="145" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.35"/>'
                . '<rect x="195" y="145" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.35"/>'
                . '<rect x="240" y="145" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.35"/>'
                . '<rect x="285" y="145" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.35"/>'
                . '<rect x="330" y="145" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.35"/>'
                . '<rect x="375" y="145" width="75" height="22" rx="4" fill="' . $acc . '" opacity="0.55"/>'
                . '<rect x="150" y="177" width="50" height="22" rx="4" fill="' . $acc . '" opacity="0.4"/>'
                . '<rect x="210" y="177" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.3"/>'
                . '<rect x="255" y="177" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.3"/>'
                . '<rect x="300" y="177" width="35" height="22" rx="4" fill="' . $acc . '" opacity="0.3"/>'
                . '<rect x="345" y="177" width="105" height="22" rx="4" fill="' . $acc . '" opacity="0.45"/>'
                . '<rect x="210" y="209" width="180" height="22" rx="4" fill="' . $acc . '" opacity="0.7"/>';
            break;
        case 'mouse':
            $iconGraphic = '<rect x="240" y="105" width="120" height="165" rx="60" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<line x1="300" y1="105" x2="300" y2="175" stroke="' . $acc . '" stroke-width="3"/>'
                . '<rect x="292" y="130" width="16" height="30" rx="8" fill="' . $acc . '"/>'
                . '<path d="M240 175 Q300 185 360 175" fill="none" stroke="' . $acc . '" stroke-width="3" opacity="0.6"/>';
            break;
        case 'headphones':
            $iconGraphic = '<path d="M210 220 V180 A90 90 0 0 1 390 180 V220" fill="none" stroke="' . $acc . '" stroke-width="6" stroke-linecap="round"/>'
                . '<rect x="190" y="195" width="36" height="68" rx="16" fill="' . $acc . '" opacity="0.8"/>'
                . '<rect x="374" y="195" width="36" height="68" rx="16" fill="' . $acc . '" opacity="0.8"/>';
            break;
        case 'mic':
            $iconGraphic = '<rect x="268" y="105" width="64" height="105" rx="32" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<path d="M245 175 A55 55 0 0 0 355 175" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<line x1="300" y1="230" x2="300" y2="268" stroke="' . $acc . '" stroke-width="5"/>'
                . '<line x1="255" y1="268" x2="345" y2="268" stroke="' . $acc . '" stroke-width="5" stroke-linecap="round"/>';
            break;
        case 'speaker':
            $iconGraphic = '<rect x="135" y="145" width="330" height="90" rx="18" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<circle cx="200" cy="190" r="26" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<circle cx="300" cy="190" r="16" fill="' . $acc . '" opacity="0.7"/>'
                . '<circle cx="400" cy="190" r="26" fill="none" stroke="' . $acc . '" stroke-width="4"/>';
            break;
        case 'cable':
            $iconGraphic = '<path d="M180 210 C210 120, 390 260, 420 160" fill="none" stroke="' . $acc . '" stroke-width="8" stroke-linecap="round"/>'
                . '<rect x="155" y="200" width="34" height="22" rx="5" fill="' . $acc . '"/>'
                . '<rect x="410" y="148" width="34" height="22" rx="5" fill="' . $acc . '"/>';
            break;
        case 'tool':
            $iconGraphic = '<rect x="288" y="105" width="24" height="75" rx="6" fill="' . $acc . '" opacity="0.8"/>'
                . '<path d="M272 180 L265 250 L300 265 L335 250 L328 180 Z" fill="none" stroke="' . $acc . '" stroke-width="4"/>';
            break;
        default:
            $iconGraphic = '<rect x="175" y="150" width="250" height="80" rx="14" fill="none" stroke="' . $acc . '" stroke-width="4"/>'
                . '<rect x="205" y="182" width="28" height="14" rx="3" fill="' . $acc . '"/>'
                . '<rect x="250" y="182" width="28" height="14" rx="3" fill="' . $acc . '"/>'
                . '<rect x="295" y="182" width="28" height="14" rx="3" fill="' . $acc . '"/>'
                . '<rect x="340" y="182" width="45" height="14" rx="3" fill="' . $acc . '"/>';
            break;
    }

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 420" width="600" height="420">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$it['bg1']}"/>
      <stop offset="100%" stop-color="{$it['bg2']}"/>
    </linearGradient>
  </defs>
  <rect width="600" height="420" fill="url(#bg)"/>
  <circle cx="510" cy="80" r="140" fill="{$acc}" opacity="0.07"/>
  <circle cx="90" cy="350" r="160" fill="{$acc}" opacity="0.05"/>
  <rect x="36" y="32" width="190" height="30" rx="15" fill="{$acc}" opacity="0.18"/>
  <text x="131" y="52" font-family="system-ui, -apple-system, sans-serif" font-size="12" font-weight="700" fill="{$acc}" text-anchor="middle" letter-spacing="1">{$it['badge']}</text>
  {$iconGraphic}
  <text x="300" y="335" font-family="system-ui, -apple-system, sans-serif" font-size="25" font-weight="800" fill="#f8fafc" text-anchor="middle">{$it['title']}</text>
  <text x="300" y="365" font-family="system-ui, -apple-system, sans-serif" font-size="15" font-weight="500" fill="#94a3b8" text-anchor="middle">{$it['sub']}</text>
</svg>
SVG;

    file_put_contents($imgDir . '/' . $it['file'], $svg);
}

$repo = Repository::getInstance();
if (in_array('--reset-db', $argv ?? [], true)) {
    $repo->resetDatabase();
    echo "Database reset and re-seeded successfully.\n";
}

echo "SVG assets generated (" . count($items) . " files) and database verified (" . $repo->getStorageEngineName() . ").\n";


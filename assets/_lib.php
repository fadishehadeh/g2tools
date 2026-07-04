<?php
/**
 * Shared helpers for the asset management module.
 */

// ── Depreciation ──────────────────────────────────────────────────────────────

function asset_book_value(array $a, ?string $as_of = null): ?float {
    if (!$a['purchase_value'] || !$a['purchase_date'] || $a['depreciation_method'] === 'none') return null;
    $as_of   = $as_of ?? date('Y-m-d');
    $start   = strtotime($a['purchase_date']);
    $end     = strtotime($as_of);
    $years   = max(0, ($end - $start) / (365.25 * 86400));
    $life    = (float)($a['useful_life_years'] ?: 5);
    $salvage = (float)($a['salvage_value'] ?? 0);
    $cost    = (float)$a['purchase_value'];

    if ($a['depreciation_method'] === 'straight_line') {
        $annual  = ($cost - $salvage) / $life;
        $depr    = min($annual * $years, $cost - $salvage);
        return max($salvage, round($cost - $depr, 2));
    }
    if ($a['depreciation_method'] === 'double_declining') {
        $rate  = 2 / $life;
        $value = $cost;
        $yrs   = floor($years);
        for ($i = 0; $i < $yrs && $value > $salvage; $i++) {
            $value = max($salvage, $value * (1 - $rate));
        }
        // partial year
        $frac = $years - $yrs;
        if ($frac > 0) $value = max($salvage, $value * (1 - $rate * $frac));
        return round($value, 2);
    }
    return null;
}

function asset_depreciation_schedule(array $a): array {
    if (!$a['purchase_value'] || !$a['purchase_date'] || $a['depreciation_method'] === 'none') return [];
    $cost    = (float)$a['purchase_value'];
    $salvage = (float)($a['salvage_value'] ?? 0);
    $life    = (int)ceil($a['useful_life_years'] ?: 5);
    $method  = $a['depreciation_method'];
    $rows    = [];
    $bv      = $cost;
    $start_y = (int)date('Y', strtotime($a['purchase_date']));
    $rate    = ($method === 'double_declining') ? 2 / $life : 0;

    for ($y = 1; $y <= $life; $y++) {
        if ($method === 'straight_line') {
            $depr = ($cost - $salvage) / $life;
        } else {
            $depr = $bv * $rate;
        }
        $depr = min($depr, $bv - $salvage);
        $depr = max(0, $depr);
        $bv  -= $depr;
        $rows[] = ['year' => $start_y + $y, 'depreciation' => round($depr, 2), 'book_value' => round($bv, 2)];
    }
    return $rows;
}

// ── QR ───────────────────────────────────────────────────────────────────────

function asset_qr_url(string $tag, int $size = 180): string {
    $data = urlencode('http://localhost/g2forms/assets/view.php?tag=' . $tag);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$data}&margin=6";
}

// ── Log helper ────────────────────────────────────────────────────────────────

function asset_log(int $asset_id, string $action, string $detail = '', ?int $user_id = null): void {
    $uid = $user_id ?? ($_SESSION['g2_user']['id'] ?? null);
    db()->prepare("INSERT INTO asset_activity_log (asset_id,user_id,action,detail) VALUES (?,?,?,?)")
      ->execute([$asset_id, $uid, $action, $detail]);
}

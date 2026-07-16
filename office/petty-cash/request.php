<?php
session_start();
require '../../config.php';
require_login();
$user = current_user();

$user_office = $user['office'] ?? null;
if ($user_office === '') $user_office = null;

// Office is always determined by URL param (entry point) or user assignment — never a form dropdown
$office = $_GET['office'] ?? $_POST['_office'] ?? $user_office ?? null;
if (!array_key_exists($office, OFFICES)) $office = null;
if (!$office && !is_admin()) {
    header('Location: index.php'); exit;
}
if (!$office) $office = 'doha'; // admin with no office param defaults to doha
// Non-admin staff must use their assigned office
if (!is_admin() && $office !== $user_office) {
    $office = $user_office;
}

$error   = '';
$success = false;

/**
 * Calls ocr.space API to extract the largest currency amount from a receipt.
 * Returns float|null. Requires OCR_SPACE_API_KEY defined in config.php.
 */
function ocr_extract_amount(string $file_path): ?float {
    if (!file_exists($file_path)) return null;
    $ext  = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'][$ext] ?? 'image/jpeg';

    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'apikey'      => OCR_SPACE_API_KEY,
            'language'    => 'eng',
            'isTable'     => 'true',
            'file'        => new CURLFile($file_path, $mime, basename($file_path)),
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;

    $json = json_decode($resp, true);
    $text = $json['ParsedResults'][0]['ParsedText'] ?? '';
    if (!$text) return null;

    // Find all numbers that look like currency amounts (e.g. 1,234.50 or 150.00)
    preg_match_all('/[\d,]+\.\d{2}/', $text, $matches);
    if (empty($matches[0])) {
        // Fallback: plain integers
        preg_match_all('/\b\d{2,6}\b/', $text, $matches);
    }
    if (empty($matches[0])) return null;

    $amounts = array_map(fn($v) => (float)str_replace(',', '', $v), $matches[0]);
    rsort($amounts);  // largest first — total is usually the biggest number on a receipt
    return $amounts[0] > 0 ? $amounts[0] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists($office, OFFICES)) $office = 'doha';
    $amount   = (float)$_POST['amount'];
    $category = trim(strip_tags($_POST['category'] ?? ''));
    $desc     = trim(strip_tags($_POST['description'] ?? ''));

    if ($amount <= 0 || !$category || !$desc) {
        $error = 'Please fill in all required fields.';
    } else {
        $receipt = null;
        if (!empty($_FILES['receipt']['name'])) {
            $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png']) && $_FILES['receipt']['size'] <= 5*1024*1024) {
                $dir = __DIR__ . '/../../storage/petty-cash/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'pcr_' . $user['id'] . '_' . date('Ymd_His') . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dir . $fname)) $receipt = $fname;
            } else {
                $error = 'Receipt must be PDF/JPG/PNG under 5MB.';
            }
        }
        if (!$error) {
            // OCR check — extract amount from receipt image/PDF via ocr.space free API
            $ocr_amount = null;
            if ($receipt && defined('OCR_SPACE_API_KEY') && OCR_SPACE_API_KEY) {
                $receipt_path = __DIR__ . '/../../storage/petty-cash/' . $receipt;
                $ocr_amount = ocr_extract_amount($receipt_path);
            }

            db()->prepare("INSERT INTO petty_cash_requests (user_id,office,amount,category,description,receipt,ocr_amount,status) VALUES (?,?,?,?,?,?,?,'unpaid')")
               ->execute([$user['id'], $office, $amount, $category, $desc, $receipt, $ocr_amount]);

            $cur = OFFICES[$office]['currency'];
            $admins = db()->query("SELECT email FROM users WHERE role IN ('finance_admin','it_admin') AND email != ''")->fetchAll();
            $admin_emails = array_column($admins, 'email');
            if ($admin_emails) {
                $subject = "[{$cur}] Petty Cash Request — {$user['name']} ({$cur} " . number_format($amount,2) . ")";
                $body    = "New petty cash request from {$user['name']}.\n\nOffice: " . OFFICES[$office]['label'] . "\nAmount: {$cur} " . number_format($amount,2) . "\nCategory: {$category}\nDescription: {$desc}\n\nLogin to G2 Tools to approve.\n";
                @mail(implode(', ', $admin_emails), $subject, $body, "From: G2 Tools <noreply@g2group.com>\r\n");
            }
            $success = true;
        }
    }
}

$cat_stmt = db()->prepare("SELECT name FROM petty_cash_categories WHERE office=? ORDER BY sort_order,name");
$cat_stmt->execute([$office]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!$categories) $categories = ['Transport','Meals & Entertainment','Office Supplies','Utilities','Maintenance','Other'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Petty Cash Request — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<link rel="stylesheet" href="/form.css">
</head>
<body>
<?php require '../../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php?office=<?= $office ?>">← <?= htmlspecialchars(OFFICES[$office]['label']) ?></a>
  <span class="topbar-title">New Request</span>
</div>
<div class="form-page-wrap">
<div class="form-card" style="max-width:540px">
  <?php if ($success): ?>
  <div style="padding:48px 40px;text-align:center">
    <div style="font-size:48px;margin-bottom:16px">✅</div>
    <div style="font-size:20px;font-weight:800;margin-bottom:8px">Request Submitted</div>
    <div style="font-size:14px;color:#888;margin-bottom:28px">Finance admin has been notified and will review your request.</div>
    <div style="display:flex;gap:10px;justify-content:center">
      <a href="request.php?office=<?= $office ?>" style="padding:10px 20px;background:#FF3D33;color:#fff;border-radius:30px;font-size:13px;font-weight:700;text-decoration:none">New Request</a>
      <a href="index.php?office=<?= $office ?>" style="padding:10px 20px;background:#f6f7f9;color:#555;border-radius:30px;font-size:13px;font-weight:600;text-decoration:none">My Requests</a>
    </div>
  </div>
  <?php else: ?>
  <div class="form-header">
    <div class="fh-text"><h1>Petty Cash Request</h1><p>Submit a reimbursement request for a cash expense</p></div>
    <div class="fh-accent">💸</div>
  </div>
  <div class="form-accent-bar"></div>
  <?php if ($error): ?>
  <div style="margin:16px 24px;padding:12px 16px;background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#dc2626"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="_office" value="<?= htmlspecialchars($office) ?>">
  <div class="form-body">
    <div class="section">
      <div style="background:<?= $office==='beirut'?'#f5f0ff':'#ecf9ff' ?>;border:1px solid <?= $office==='beirut'?'#d8b4fe':'#bae6fd' ?>;border-radius:8px;padding:10px 14px;font-size:13px;color:#555;margin-bottom:16px;display:flex;align-items:center;gap:8px">
        <?= OFFICES[$office]['flag'] ?? '' ?>
        <span>Office: <strong><?= htmlspecialchars(OFFICES[$office]['label']) ?> (<?= OFFICES[$office]['currency'] ?>)</strong></span>
      </div>
      <div class="field">
        <label class="field-label">Amount <span style="color:#FF3D33">*</span></label>
        <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required value="<?= htmlspecialchars($_POST['amount']??'') ?>">
      </div>
      <div class="field">
        <label class="field-label">Category <span style="color:#FF3D33">*</span></label>
        <select name="category" required>
          <option value="">Select category…</option>
          <?php foreach($categories as $c): ?>
          <option value="<?= $c ?>" <?= ($_POST['category']??'')===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="field-label">Description <span style="color:#FF3D33">*</span></label>
        <textarea name="description" rows="3" placeholder="Describe what the expense was for…" required><?= htmlspecialchars($_POST['description']??'') ?></textarea>
      </div>
      <div class="field">
        <label class="field-label">Receipt <span style="font-size:11px;color:#aaa">(PDF, JPG or PNG, max 5MB)</span></label>
        <label style="display:flex;align-items:center;gap:10px;border:2px dashed #e8eaee;border-radius:8px;padding:14px 16px;cursor:pointer" id="rl">
          <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="document.getElementById('rl').querySelector('span').textContent=this.files[0]?.name||'Click to upload receipt'">
          <span style="font-size:13px;color:#aaa">Click to upload receipt</span>
        </label>
      </div>
    </div>
  </div>
  <div class="form-footer">
    <button type="submit" class="submit-btn">Submit Request</button>
  </div>
  </form>
  <?php endif; ?>
</div>
</div>
</div>
</body>
</html>

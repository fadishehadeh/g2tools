<?php
session_start();
require 'config.php';
require_login();

$user = current_user();
$stmt = db()->prepare(
    "SELECT id, form_type, form_data, created_at FROM form_submissions WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$user['id']]);
$submissions = $stmt->fetchAll();

function form_label(string $type): string {
    return $type === 'amex' ? 'Credit Card Auth' : 'Accountability';
}
function form_summary(string $type, array $data): string {
    if ($type === 'amex') {
        $co = !empty($data['company']) ? $data['company'] . ' — ' : '';
        return htmlspecialchars($co . ($data['merchant'] ?? '—'));
    }
    return htmlspecialchars($data['item_name'] ?? '—');
}
function form_serial(string $type, array $data): string {
    if ($type === 'amex' && !empty($data['serial_number'])) {
        return '<span style="font-family:monospace;font-size:12px;color:#555;">' . htmlspecialchars($data['serial_number']) . '</span>';
    }
    return '<span style="color:#ddd">—</span>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Submissions — G2</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
  .page-wrap { padding: 40px 40px 60px; }
  .page-header { margin-bottom: 28px; }
  .page-header h1 { font-size: 22px; font-weight: 800; color: #1a1a1a; }
  .page-header p  { font-size: 13px; color: #999; margin-top: 4px; }

  .table-card { background: #fff; border-radius: 14px; border: 1px solid #e8eaee;
                box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: #f8f9fb; }
  th { padding: 12px 18px; text-align: left; font-size: 11px; font-weight: 700; color: #999;
       text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #eee; }
  td { padding: 14px 18px; font-size: 13px; color: #333; border-bottom: 1px solid #f3f3f3; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafafa; }

  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .badge-amex { background: #fff0f0; color: #FF3D33; }
  .badge-acc  { background: #f0f4ff; color: #4466dd; }

  .dl-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px;
            background: #FF3D33; color: #fff; border-radius: 20px; font-size: 12px;
            font-weight: 700; text-decoration: none; }
  .dl-btn:hover { background: #e8302a; }

  .empty { text-align: center; padding: 60px 24px; color: #bbb; }
  .empty p { font-size: 15px; margin-bottom: 16px; }
  .empty a { display: inline-block; background: #FF3D33; color: #fff; padding: 10px 24px;
             border-radius: 8px; font-weight: 700; font-size: 14px; text-decoration: none; }
</style>
</head>
<body>

<?php require '_sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">My Submissions</span>
  </div>

  <div class="page-wrap">
    <div class="page-header">
      <h1>My Submissions</h1>
      <p>All forms you have submitted — click Download to get the PDF again.</p>
    </div>

    <?php if (empty($submissions)): ?>
      <div class="table-card">
        <div class="empty">
          <p>No submissions yet.</p>
          <a href="/">Submit a form</a>
        </div>
      </div>
    <?php else: ?>
      <div class="table-card">
        <table>
          <thead>
            <tr><th>#</th><th>Form</th><th>Details</th><th>Serial</th><th>Date</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($submissions as $s):
              $data = json_decode($s['form_data'], true); ?>
            <tr>
              <td style="color:#ccc"><?= $s['id'] ?></td>
              <td><span class="badge <?= $s['form_type'] === 'amex' ? 'badge-amex' : 'badge-acc' ?>"><?= form_label($s['form_type']) ?></span></td>
              <td><?= form_summary($s['form_type'], $data) ?></td>
              <td><?= form_serial($s['form_type'], $data) ?></td>
              <td style="color:#888"><?= date('d M Y, H:i', strtotime($s['created_at'])) ?></td>
              <td><a class="dl-btn" href="/download.php?id=<?= $s['id'] ?>">⬇ PDF</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>

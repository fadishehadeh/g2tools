<?php
session_start();
require '../config.php';
require_login();
require_can('assets');

$user  = current_user();
$db    = db();
$uid   = $user['id'];
$admin = is_admin();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $rid    = (int)($_POST['id'] ?? 0);
    $act    = $_POST['act'] ?? '';
    $notes  = trim(strip_tags($_POST['notes'] ?? ''));

    if ($rid && in_array($act, ['approved','rejected'])) {
        $db->prepare("UPDATE asset_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
           ->execute([$act, $uid, $notes ?: null, $rid]);

        // Notify requester
        $req = $db->prepare("SELECT ar.*, u.name req_name, u.email req_email FROM asset_requests ar JOIN users u ON u.id=ar.requested_by WHERE ar.id=?");
        $req->execute([$rid]);
        $r = $req->fetch();
        if ($r && $r['req_email']) {
            require_once '../mailer.php';
            $colour = $act === 'approved' ? '#15803d' : '#b91c1c';
            $label  = $act === 'approved' ? 'Approved ✓' : 'Rejected ✗';
            $subj   = "[$label] Asset Request — {$r['asset_name']}";
            $msg    = "Your asset request for <strong>" . htmlspecialchars($r['asset_name']) . "</strong> has been <strong style='color:{$colour}'>" . ucfirst($act) . "</strong>.";
            if ($notes) $msg .= "<br><br><strong>Note:</strong> " . htmlspecialchars($notes);
            $body = mail_template($subj, "<p>$msg</p>");
            send_mail(['email' => $r['req_email'], 'name' => $r['req_name']], $subj, $body);
        }

        $_SESSION['flash'] = ['type' => $act === 'approved' ? 'ok' : 'warn', 'msg' => 'Request ' . $act . '.'];
        header('Location: requests.php'); exit;
    }
}

// Fetch requests
$status_filter = $_GET['status'] ?? '';
$allowed_statuses = ['pending','approved','rejected'];

if ($admin) {
    $where  = $status_filter && in_array($status_filter, $allowed_statuses) ? "WHERE ar.status = ?" : "WHERE 1=1";
    $params = $status_filter && in_array($status_filter, $allowed_statuses) ? [$status_filter] : [];
    $sql = "SELECT ar.*, u.name req_name, c.name cat_name, c.icon cat_icon,
                   rv.name reviewer_name
            FROM asset_requests ar
            JOIN users u ON u.id = ar.requested_by
            LEFT JOIN asset_categories c ON c.id = ar.category_id
            LEFT JOIN users rv ON rv.id = ar.reviewed_by
            $where ORDER BY FIELD(ar.status,'pending','approved','rejected'), ar.created_at DESC";
} else {
    $where  = $status_filter && in_array($status_filter, $allowed_statuses) ? "AND ar.status = ?" : "";
    $params = $status_filter && in_array($status_filter, $allowed_statuses) ? [$uid, $status_filter] : [$uid];
    $sql = "SELECT ar.*, u.name req_name, c.name cat_name, c.icon cat_icon,
                   rv.name reviewer_name
            FROM asset_requests ar
            JOIN users u ON u.id = ar.requested_by
            LEFT JOIN asset_categories c ON c.id = ar.category_id
            LEFT JOIN users rv ON rv.id = ar.reviewed_by
            WHERE ar.requested_by = ? $where
            ORDER BY FIELD(ar.status,'pending','approved','rejected'), ar.created_at DESC";
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));

$urgency_colors = ['low'=>['#f0fdf4','#15803d'], 'medium'=>['#fffbeb','#d97706'], 'high'=>['#fff5f5','#b91c1c']];
$status_colors  = ['pending'=>['#fffbeb','#d97706'], 'approved'=>['#f0fdf4','#15803d'], 'rejected'=>['#fff5f5','#b91c1c']];

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Requests — G2 Tools</title>
<link rel="stylesheet" href="/sidebar.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f5f7;color:#1a1a1a}
.page{padding:28px 32px;max-width:1100px}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.ph h1{font-size:22px;font-weight:900}
.btn-new{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#FF3D33;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none}
.btn-new:hover{background:#e8302a}
.tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.tab{padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;color:#888;background:#fff;border:1.5px solid #e8eaee;transition:.12s}
.tab:hover,.tab.active{background:#FF3D33;color:#fff;border-color:#FF3D33}
.req-list{display:flex;flex-direction:column;gap:12px}
.req-card{background:#fff;border:1.5px solid #e8eaee;border-radius:14px;padding:0;overflow:hidden;transition:.12s}
.req-card:hover{border-color:#ddd;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.req-top{display:flex;align-items:flex-start;gap:14px;padding:16px 20px}
.req-icon{width:40px;height:40px;border-radius:10px;background:#f4f5f7;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.req-main{flex:1;min-width:0}
.req-name{font-size:15px;font-weight:800;color:#1a1a1a;margin-bottom:4px}
.req-meta{font-size:12px;color:#aaa;display:flex;gap:12px;flex-wrap:wrap}
.req-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.req-body{padding:0 20px 16px;font-size:13px;color:#555;border-top:1px solid #f8f8f8;padding-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:8px}
.req-field{display:flex;flex-direction:column;gap:2px}
.req-flabel{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#aaa}
.req-fval{font-size:13px;color:#333;font-weight:500}
.req-justification{grid-column:1/-1;background:#fafafa;border-radius:8px;padding:10px 12px;font-size:13px;color:#555;line-height:1.6}
.req-actions{padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;gap:8px;align-items:center;background:#fafafa}
.btn-approve{padding:7px 16px;background:#15803d;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer}
.btn-approve:hover{background:#166534}
.btn-reject{padding:7px 16px;background:#fff;color:#b91c1c;border:1.5px solid #fca5a5;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer}
.btn-reject:hover{background:#fff5f5}
.empty{text-align:center;padding:60px 20px;color:#bbb;background:#fff;border:1.5px solid #e8eaee;border-radius:14px}
.empty-icon{font-size:40px;margin-bottom:12px}
.flash{padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:20px}
.flash-ok{background:#f0fdf4;color:#15803d;border:1.5px solid #bbf7d0}
.flash-warn{background:#fff5f5;color:#b91c1c;border:1.5px solid #fecaca}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:32px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:17px;font-weight:800;margin-bottom:10px}
.modal p{font-size:13px;color:#888;margin-bottom:16px}
.modal textarea{width:100%;border:1.5px solid #e8eaee;border-radius:8px;padding:10px 13px;font-size:13px;font-family:inherit;resize:vertical;min-height:72px;outline:none;margin-bottom:12px}
.modal textarea:focus{border-color:#FF3D33}
.modal-btns{display:flex;gap:10px;justify-content:flex-end}
.modal-cancel{padding:9px 18px;background:#f4f5f7;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.modal-confirm{padding:9px 18px;background:#b91c1c;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
</style>
</head>
<body>
<?php require '../_sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
  <a class="topbar-back" href="index.php">← Assets</a>
  <span class="topbar-title">Asset Requests</span>
</div>
<div class="page">

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="ph">
  <h1>Asset Requests <?php if ($pending_count): ?><span style="background:#FF3D33;color:#fff;font-size:12px;padding:2px 9px;border-radius:10px;margin-left:8px;vertical-align:middle"><?= $pending_count ?> pending</span><?php endif; ?></h1>
  <a class="btn-new" href="request-new.php">＋ New Request</a>
</div>

<div class="tabs">
  <a class="tab <?= !$status_filter ? 'active' : '' ?>" href="requests.php">All</a>
  <a class="tab <?= $status_filter==='pending'  ? 'active' : '' ?>" href="?status=pending">⏳ Pending</a>
  <a class="tab <?= $status_filter==='approved' ? 'active' : '' ?>" href="?status=approved">✓ Approved</a>
  <a class="tab <?= $status_filter==='rejected' ? 'active' : '' ?>" href="?status=rejected">✗ Rejected</a>
</div>

<?php if (empty($requests)): ?>
<div class="empty">
  <div class="empty-icon">📋</div>
  <div>No asset requests<?= $status_filter ? ' with this status' : '' ?>.</div>
  <div style="margin-top:12px"><a href="request-new.php" style="color:#FF3D33;font-weight:700;text-decoration:none">Submit your first request →</a></div>
</div>
<?php else: ?>
<div class="req-list">
<?php foreach ($requests as $r):
  [$sbg,$sfg] = $status_colors[$r['status']] ?? ['#f4f5f7','#888'];
  [$ubg,$ufg] = $urgency_colors[$r['urgency']] ?? ['#f4f5f7','#888'];
  $created = (new DateTime($r['created_at']))->format('d M Y');
?>
<div class="req-card">
  <div class="req-top">
    <div class="req-icon"><?= $r['cat_icon'] ?: '🖥️' ?></div>
    <div class="req-main">
      <div class="req-name"><?= htmlspecialchars($r['asset_name']) ?></div>
      <div class="req-meta">
        <?php if ($admin): ?><span>By <?= htmlspecialchars($r['req_name']) ?></span><?php endif; ?>
        <span><?= $created ?></span>
        <?php if ($r['cat_name']): ?><span><?= htmlspecialchars($r['cat_name']) ?></span><?php endif; ?>
        <span>Qty: <?= $r['quantity'] ?></span>
        <?php if ($r['est_cost']): ?><span>Est. QAR <?= number_format($r['est_cost'], 0) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="req-right">
      <span class="pill" style="background:<?= $sbg ?>;color:<?= $sfg ?>"><?= ucfirst($r['status']) ?></span>
      <span class="pill" style="background:<?= $ubg ?>;color:<?= $ufg ?>"><?= ucfirst($r['urgency']) ?> urgency</span>
    </div>
  </div>

  <div class="req-body">
    <div class="req-justification">
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#aaa;display:block;margin-bottom:4px">Justification</span>
      <?= nl2br(htmlspecialchars($r['justification'])) ?>
    </div>
    <?php if ($r['status'] !== 'pending' && $r['reviewer_name']): ?>
    <div class="req-field">
      <span class="req-flabel">Reviewed By</span>
      <span class="req-fval"><?= htmlspecialchars($r['reviewer_name']) ?> · <?= $r['reviewed_at'] ? (new DateTime($r['reviewed_at']))->format('d M Y') : '' ?></span>
    </div>
    <?php if ($r['review_notes']): ?>
    <div class="req-field" style="grid-column:1/-1">
      <span class="req-flabel">Review Note</span>
      <span class="req-fval"><?= nl2br(htmlspecialchars($r['review_notes'])) ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($admin && $r['status'] === 'pending'): ?>
  <div class="req-actions">
    <form method="POST" style="display:contents">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input type="hidden" name="act" value="approved">
      <button class="btn-approve" type="submit">✓ Approve</button>
    </form>
    <button class="btn-reject" onclick="openReject(<?= $r['id'] ?>, '<?= htmlspecialchars($r['asset_name'], ENT_QUOTES) ?>')">✗ Reject</button>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</div>

<!-- Reject modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <h3>Reject Request</h3>
    <p id="rejectDesc">Add an optional note explaining the reason.</p>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="id" id="rejectId">
      <input type="hidden" name="act" value="rejected">
      <textarea name="notes" id="rejectNotes" placeholder="Reason for rejection (optional)…"></textarea>
      <div class="modal-btns">
        <button type="button" class="modal-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="modal-confirm">Reject</button>
      </div>
    </form>
  </div>
</div>
<script>
function openReject(id, name) {
  document.getElementById('rejectId').value = id;
  document.getElementById('rejectDesc').textContent = 'Rejecting request for "' + name + '". Add an optional note.';
  document.getElementById('rejectNotes').value = '';
  document.getElementById('rejectModal').classList.add('open');
}
function closeModal() { document.getElementById('rejectModal').classList.remove('open'); }
document.getElementById('rejectModal').addEventListener('click', function(e) { if (e.target===this) closeModal(); });
</script>
</body>
</html>

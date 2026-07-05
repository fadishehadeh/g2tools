<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/workflow.php';
sm_require_staff();
$staff = sm_current_staff();

$id = (int)($_GET['id'] ?? 0);
$stmt = sm_db()->prepare(
    "SELECT p.*, c.name AS client_name, c.email AS client_email, cal.name AS calendar_name
     FROM posts p JOIN clients c ON c.id=p.client_id JOIN calendars cal ON cal.id=p.calendar_id
     WHERE p.id=?"
);
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { header('Location: ' . SM_BASE_URL . '/posts/'); exit; }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_meta') {
        $title = trim(strip_tags($_POST['title'] ?? ''));
        if (!$title) {
            $msg = 'err|Title is required.';
        } else {
            sm_db()->prepare(
                "UPDATE posts SET title=?,caption=?,notes=?,hashtags=?,platform=?,format=?,technical_specs=?,scheduled_at=?,owner_id=? WHERE id=?"
            )->execute([
                $title,
                $_POST['caption'] ?? null,
                $_POST['notes'] ?? null,
                $_POST['hashtags'] ?? null,
                $_POST['platform'] ?: null,
                $_POST['format'] ?: null,
                $_POST['technical_specs'] ?: null,
                $_POST['scheduled_at'] ?: null,
                (int)($_POST['owner_id'] ?? 0) ?: null,
                $id,
            ]);
            $msg = 'ok|Post details saved.';
        }
    } elseif ($action === 'upload_artwork') {
        if (!empty($_FILES['artwork']['name'])) {
            $ext = strtolower(pathinfo($_FILES['artwork']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','mp4','mov','pdf'];
            if (!in_array($ext, $allowed, true)) {
                $msg = 'err|Unsupported file type.';
            } elseif ($_FILES['artwork']['size'] > 50*1024*1024) {
                $msg = 'err|File exceeds 50MB limit.';
            } else {
                $verRow = sm_db()->prepare("SELECT COALESCE(MAX(version),0)+1 FROM artwork_versions WHERE post_id=? AND deleted_at IS NULL");
                $verRow->execute([$id]);
                $version = (int)$verRow->fetchColumn();

                $fname = 'art_' . $id . '_v' . $version . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['artwork']['tmp_name'], SM_ARTWORK_PATH . $fname)) {
                    sm_db()->prepare(
                        "INSERT INTO artwork_versions (post_id,asset_path,filename,mime_type,file_size,version,uploaded_by) VALUES (?,?,?,?,?,?,?)"
                    )->execute([$id, $fname, $_FILES['artwork']['name'], $_FILES['artwork']['type'], $_FILES['artwork']['size'], $version, $staff['id']]);

                    if ($post['status'] === 'Draft' || $post['status'] === 'Artwork Pending') {
                        sm_db()->prepare("UPDATE posts SET status='Draft' WHERE id=? AND status='Artwork Pending'")->execute([$id]);
                    }
                    $msg = 'ok|Artwork uploaded (version ' . $version . ').';
                } else {
                    $msg = 'err|Upload failed.';
                }
            }
        } else {
            $msg = 'err|Please choose a file.';
        }
    } elseif ($action === 'delete_artwork') {
        $aid = (int)($_POST['artwork_id'] ?? 0);
        sm_db()->prepare("UPDATE artwork_versions SET deleted_at=NOW() WHERE id=? AND post_id=?")->execute([$aid, $id]);
        $msg = 'ok|Artwork version removed.';
    } elseif ($action === 'add_comment') {
        $content = trim(strip_tags($_POST['content'] ?? ''));
        if ($content) {
            sm_db()->prepare(
                "INSERT INTO comments (post_id,author_user_id,author_name,content) VALUES (?,?,?,?)"
            )->execute([$id, $staff['id'], $staff['name'], $content]);
            $msg = 'ok|Comment added.';
        }
    } elseif ($action === 'send_brief') {
        sm_db()->prepare("UPDATE posts SET status='Brief Sent' WHERE id=?")->execute([$id]);
        sm_db()->prepare("INSERT INTO comments (post_id,author_user_id,author_name,content) VALUES (?,?,?,?)")
            ->execute([$id, $staff['id'], $staff['name'], 'Creative brief sent.']);
        @mail($post['client_email'] ?: 'noreply@g2group.com', 'Creative Brief — ' . $post['title'],
            "A creative brief has been prepared for: {$post['title']}\n\nCaption: {$post['caption']}\nNotes: {$post['notes']}\n\n— G2 SM Calendar Tool",
            "From: " . MAILJET_SENDER_NAME . " <" . MAILJET_SENDER_EMAIL . ">\r\n");
        $msg = 'ok|Creative brief sent.';
    } elseif ($action === 'request_artwork') {
        sm_db()->prepare("UPDATE posts SET status='Artwork Pending' WHERE id=?")->execute([$id]);
        $msg = 'ok|Marked as awaiting artwork.';
    } elseif ($action === 'send_review') {
        $active = sm_db()->prepare("SELECT COUNT(*) FROM artwork_versions WHERE post_id=? AND deleted_at IS NULL");
        $active->execute([$id]);
        if ((int)$active->fetchColumn() === 0) {
            $msg = 'err|Upload artwork before sending for client review.';
        } else {
            sm_db()->prepare("UPDATE posts SET status='In Review', rejection_feedback=NULL WHERE id=?")->execute([$id]);
            sm_db()->prepare("INSERT INTO comments (post_id,author_user_id,author_name,content) VALUES (?,?,?,?)")
                ->execute([$id, $staff['id'], $staff['name'], 'Sent to client for review.']);
            sm_db()->prepare("INSERT INTO notifications (title,body,link) VALUES (?,?,?)")
                ->execute(['Post sent for client review', $post['title'] . ' — ' . $post['client_name'], SM_BASE_URL . '/posts/detail.php?id=' . $id]);
            $msg = 'ok|Sent for client review. (Client portal review link goes live in the next phase.)';
        }
    } elseif ($action === 'mark_published') {
        sm_db()->prepare("UPDATE posts SET status='Published' WHERE id=?")->execute([$id]);
        sm_db()->prepare(
            "INSERT INTO publish_jobs (post_id,mode,status,created_by) VALUES (?,'manual','success',?)"
        )->execute([$id, $staff['id']]);
        $job_id = sm_db()->lastInsertId();
        sm_db()->prepare("INSERT INTO publish_attempts (job_id,attempt_no,status,response) VALUES (?,1,'success',?)")
            ->execute([$job_id, 'Marked as manually published by ' . $staff['name']]);
        $msg = 'ok|Marked as published.';
    } elseif ($action === 'archive') {
        sm_db()->prepare("UPDATE posts SET archived = 1 - archived WHERE id=?")->execute([$id]);
        $msg = 'ok|Post updated.';
    }

    // Reload post after any mutation
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}
[$mt,$mm] = $msg ? explode('|',$msg,2) : ['',''];

$artworks = sm_db()->prepare(
    "SELECT a.*, u.name AS uploader_name FROM artwork_versions a
     LEFT JOIN " . g2_users_table() . " u ON u.id=a.uploaded_by
     WHERE a.post_id=? AND a.deleted_at IS NULL ORDER BY a.version DESC"
);
$artworks->execute([$id]);
$artworks = $artworks->fetchAll();
$latest_artwork = $artworks[0] ?? null;

$comments = sm_db()->prepare("SELECT * FROM comments WHERE post_id=? ORDER BY created_at ASC");
$comments->execute([$id]);
$comments = $comments->fetchAll();

$publish_history = sm_db()->prepare(
    "SELECT j.*, u.name AS by_name FROM publish_jobs j
     LEFT JOIN " . g2_users_table() . " u ON u.id=j.created_by
     WHERE j.post_id=? ORDER BY j.created_at DESC"
);
$publish_history->execute([$id]);
$publish_history = $publish_history->fetchAll();

$owners = sm_db()->query("SELECT id,name FROM " . g2_users_table() . " WHERE status='active' ORDER BY name")->fetchAll();
$owner_name = null;
foreach ($owners as $o) if ((int)$o['id'] === (int)$post['owner_id']) $owner_name = $o['name'];

$platforms = ['Facebook','Instagram','TikTok','LinkedIn','Twitter/X','YouTube'];
$formats   = ['Image','Video','Carousel','Reel','Story'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($post['title']) ?> — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/sm-calendar/sm.css">
<style>
  .pd-wrap { display:grid; grid-template-columns: 1fr 360px; gap:20px; align-items:start; }
  .panel { background:#fff; border:1px solid #e8eaee; border-radius:14px; padding:22px; margin-bottom:18px; }
  .panel h3 { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#aaa; margin:0 0 16px; }
  .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

  .guidance { background:#fffbeb; border:1.5px solid #fde68a; border-left:4px solid #f59e0b; border-radius:8px;
              padding:12px 16px; font-size:12.5px; color:#92400e; margin-bottom:18px; }
  .guidance.ok { background:#f0fdf4; border-color:#bbf7d0; border-left-color:#16a34a; color:#166534; }
  .guidance.review { background:#f5f3ff; border-color:#ddd6fe; border-left-color:#8b5cf6; color:#5b21b6; }
  .guidance.rej { background:#fef2f2; border-color:#fca5a5; border-left-color:#dc2626; color:#991b1b; }

  .action-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
  .a-btn { padding:9px 16px; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer; border:none; }
  .a-btn-red { background:#FF3D33; color:#fff; }
  .a-btn-dark { background:#1a1a1a; color:#fff; }
  .a-btn-outline { background:#fff; color:#555; border:1.5px solid #e8eaee; }
  .a-btn-outline:hover { border-color:#aaa; }

  .artwork-item { display:flex; align-items:center; gap:12px; padding:10px; border:1px solid #f0f1f3; border-radius:10px; margin-bottom:8px; }
  .artwork-thumb { width:48px; height:48px; border-radius:8px; background:#f6f7f9; flex-shrink:0; overflow:hidden; display:flex; align-items:center; justify-content:center; }
  .artwork-thumb img { width:100%; height:100%; object-fit:cover; }
  .artwork-meta { flex:1; min-width:0; }
  .artwork-name { font-size:12.5px; font-weight:600; color:#1a1a1a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .artwork-sub { font-size:11px; color:#aaa; }
  .ver-badge { font-size:10px; font-weight:700; background:#f5f6f8; padding:2px 7px; border-radius:6px; color:#777; }
  .ver-badge.current { background:#fff3f2; color:#FF3D33; }

  .upload-drop { border:2px dashed #e8eaee; border-radius:10px; padding:18px; text-align:center; cursor:pointer; margin-bottom:14px; }
  .upload-drop:hover { border-color:#FF3D33; }
  .upload-drop input { display:none; }

  .comment-item { padding:10px 0; border-bottom:1px solid #f5f6f8; }
  .comment-item:last-child { border-bottom:none; }
  .comment-author { font-size:12px; font-weight:700; color:#1a1a1a; }
  .comment-date { font-size:10.5px; color:#bbb; margin-left:6px; }
  .comment-text { font-size:12.5px; color:#555; margin-top:3px; line-height:1.5; }

  .tool-btn { display:block; width:100%; text-align:left; padding:9px 12px; border:1.5px solid #e8eaee;
              border-radius:8px; font-size:12.5px; font-weight:600; color:#555; background:#fff; cursor:pointer; margin-bottom:8px; text-decoration:none; }
  .tool-btn:hover { border-color:#FF3D33; color:#FF3D33; }

  .ph-item { font-size:12px; padding:8px 0; border-bottom:1px solid #f5f6f8; }
  .ph-item:last-child { border-bottom:none; }
  .ph-success { color:#16a34a; font-weight:700; }
  .ph-failed { color:#dc2626; font-weight:700; }
</style>
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <div>
        <a href="<?= SM_BASE_URL ?>/posts/" style="font-size:12px;color:#999;text-decoration:none">← Posts</a>
        <h1 style="margin-top:4px"><?= htmlspecialchars($post['title']) ?></h1>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <?= sm_status_badge($post['status']) ?>
        <span style="font-size:12px;color:#999"><?= htmlspecialchars($post['client_name']) ?> · <?= htmlspecialchars($post['calendar_name']) ?></span>
      </div>
    </div>

    <?php if ($mm): ?>
    <div class="sm-msg <?= $mt==='ok'?'sm-msg-ok':'sm-msg-err' ?>"><?= htmlspecialchars($mm) ?></div>
    <?php endif; ?>

    <div class="guidance <?= $post['status']==='Approved'?'ok':($post['status']==='In Review'?'review':($post['status']==='Rejected'?'rej':'')) ?>">
      <?= htmlspecialchars(sm_workflow_guidance($post['status'])) ?>
    </div>

    <?php if ($post['status']==='Rejected' && $post['rejection_feedback']): ?>
    <div class="guidance rej"><strong>Client feedback:</strong> <?= htmlspecialchars($post['rejection_feedback']) ?></div>
    <?php endif; ?>

    <div class="action-row">
      <?php if (in_array($post['status'], ['Draft','Artwork Pending'], true)): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="send_brief"><button class="a-btn a-btn-dark">Send Creative Brief</button></form>
      <?php endif; ?>
      <?php if ($post['status']==='Draft'): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="request_artwork"><button class="a-btn a-btn-outline">Mark Artwork Pending</button></form>
      <?php endif; ?>
      <?php if (in_array($post['status'], ['Draft','Artwork Pending','Rejected'], true)): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="send_review"><button class="a-btn a-btn-red">Send for Client Review</button></form>
      <?php endif; ?>
      <?php if ($post['status']==='Approved'): ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="mark_published"><button class="a-btn a-btn-dark">✓ Mark as Manually Published</button></form>
      <button class="a-btn a-btn-outline" disabled title="Meta connection required (Phase 7)">Direct Publish</button>
      <?php endif; ?>
      <form method="POST" style="display:inline"><input type="hidden" name="action" value="archive"><button class="a-btn a-btn-outline" onclick="return confirm('Archive this post?')"><?= $post['archived']?'Restore':'Archive' ?></button></form>
    </div>

    <div class="pd-wrap">
      <!-- Main column -->
      <div>
        <div class="panel">
          <h3>Post Details</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_meta">
            <div class="sm-field"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required></div>
            <div class="sm-field"><label>Caption</label><textarea name="caption" rows="4"><?= htmlspecialchars($post['caption'] ?? '') ?></textarea></div>
            <div class="grid2">
              <div class="sm-field"><label>Notes (internal)</label><textarea name="notes" rows="2"><?= htmlspecialchars($post['notes'] ?? '') ?></textarea></div>
              <div class="sm-field"><label>Hashtags</label><textarea name="hashtags" rows="2"><?= htmlspecialchars($post['hashtags'] ?? '') ?></textarea></div>
            </div>
            <div class="grid2">
              <div class="sm-field">
                <label>Platform</label>
                <select name="platform">
                  <option value="">—</option>
                  <?php foreach ($platforms as $p): ?><option value="<?= $p ?>" <?= $post['platform']===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="sm-field">
                <label>Format</label>
                <select name="format">
                  <option value="">—</option>
                  <?php foreach ($formats as $f): ?><option value="<?= $f ?>" <?= $post['format']===$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="grid2">
              <div class="sm-field"><label>Technical Specs</label><input type="text" name="technical_specs" value="<?= htmlspecialchars($post['technical_specs'] ?? '') ?>" placeholder="e.g. 1080x1080, max 30s"></div>
              <div class="sm-field"><label>Scheduled Date/Time</label><input type="datetime-local" name="scheduled_at" value="<?= $post['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($post['scheduled_at'])) : '' ?>"></div>
            </div>
            <div class="sm-field">
              <label>Owner</label>
              <select name="owner_id">
                <option value="">— Unassigned —</option>
                <?php foreach ($owners as $o): ?>
                <option value="<?= $o['id'] ?>" <?= (int)$post['owner_id']===(int)$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="sm-btn-primary" style="width:auto;padding:10px 22px">Save Changes</button>
          </form>
        </div>

        <div class="panel">
          <h3>Artwork &amp; Versions</h3>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_artwork">
            <label class="upload-drop">
              <input type="file" name="artwork" accept=".jpg,.jpeg,.png,.gif,.mp4,.mov,.pdf" onchange="this.form.submit()">
              <span style="font-size:13px;color:#999">📎 Click to upload new artwork version</span>
            </label>
          </form>
          <?php if (empty($artworks)): ?>
          <p style="font-size:12.5px;color:#bbb">No artwork uploaded yet.</p>
          <?php else: ?>
          <?php foreach ($artworks as $i => $a):
            $is_img = preg_match('/\.(jpg|jpeg|png|gif)$/i', $a['filename']);
          ?>
          <div class="artwork-item">
            <div class="artwork-thumb">
              <?php if ($is_img): ?>
              <img src="<?= SM_BASE_URL ?>/posts/artwork.php?id=<?= $a['id'] ?>">
              <?php else: ?>
              <span style="font-size:18px">📄</span>
              <?php endif; ?>
            </div>
            <div class="artwork-meta">
              <div class="artwork-name"><?= htmlspecialchars($a['filename']) ?></div>
              <div class="artwork-sub"><?= htmlspecialchars($a['uploader_name'] ?? 'Unknown') ?> · <?= date('d M Y H:i', strtotime($a['uploaded_at'])) ?></div>
            </div>
            <span class="ver-badge <?= $i===0?'current':'' ?>">v<?= $a['version'] ?><?= $i===0?' (latest)':'' ?></span>
            <a class="a-btn a-btn-outline" style="padding:5px 10px;text-decoration:none" href="<?= SM_BASE_URL ?>/posts/artwork.php?id=<?= $a['id'] ?>&download=1">⬇</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this artwork version?')">
              <input type="hidden" name="action" value="delete_artwork">
              <input type="hidden" name="artwork_id" value="<?= $a['id'] ?>">
              <button type="submit" class="a-btn a-btn-outline" style="padding:5px 10px;color:#dc2626">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="panel">
          <h3>Internal Comments &amp; Activity</h3>
          <?php if (empty($comments)): ?>
          <p style="font-size:12.5px;color:#bbb">No comments yet.</p>
          <?php else: ?>
          <?php foreach ($comments as $c): ?>
          <div class="comment-item">
            <span class="comment-author"><?= htmlspecialchars($c['author_name']) ?></span>
            <span class="comment-date"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></span>
            <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
          <form method="POST" style="margin-top:14px">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="content" rows="2" placeholder="Add an internal comment…" required style="width:100%;padding:10px 12px;border:1.5px solid #e8eaee;border-radius:8px;font-size:13px;font-family:inherit;margin-bottom:8px"></textarea>
            <button type="submit" class="a-btn a-btn-outline">Add Comment</button>
          </form>
        </div>
      </div>

      <!-- Sidebar column -->
      <div>
        <div class="panel">
          <h3>Manual Publishing Toolkit</h3>
          <?php if ($latest_artwork): ?>
          <a class="tool-btn" href="<?= SM_BASE_URL ?>/posts/artwork.php?id=<?= $latest_artwork['id'] ?>&download=1">⬇ Download Latest Media</a>
          <?php endif; ?>
          <button class="tool-btn" onclick="copyText(<?= htmlspecialchars(json_encode($post['caption'] ?? ''), ENT_QUOTES) ?>)">📋 Copy Caption</button>
          <button class="tool-btn" onclick="copyText(<?= htmlspecialchars(json_encode($post['hashtags'] ?? ''), ENT_QUOTES) ?>)">📋 Copy Hashtags</button>
          <a class="tool-btn" href="<?= SM_BASE_URL ?>/posts/export_brief.php?id=<?= $id ?>">⬇ Export Posting Brief (.txt)</a>
        </div>

        <div class="panel">
          <h3>Publish History</h3>
          <?php if (empty($publish_history)): ?>
          <p style="font-size:12.5px;color:#bbb">No publish activity yet.</p>
          <?php else: ?>
          <?php foreach ($publish_history as $ph): ?>
          <div class="ph-item">
            <span class="<?= $ph['status']==='success'?'ph-success':'ph-failed' ?>"><?= ucfirst($ph['mode']) ?> — <?= ucfirst($ph['status']) ?></span><br>
            <span style="color:#aaa"><?= htmlspecialchars($ph['by_name'] ?? 'System') ?> · <?= date('d M Y H:i', strtotime($ph['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
function copyText(text) {
  navigator.clipboard.writeText(text || '').then(() => alert('Copied to clipboard'));
}
</script>
</body>
</html>

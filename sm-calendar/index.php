<?php
require_once __DIR__ . '/lib/bootstrap.php';
sm_require_staff();
$staff = sm_current_staff();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — G2 SM Calendar Tool</title>
<link rel="stylesheet" href="/g2forms/sm-calendar/sm.css">
</head>
<body>
<div class="sm-shell">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="sm-main">
    <div class="sm-topbar">
      <h1>Dashboard</h1>
      <div class="sm-user-chip">
        <span class="sm-role-badge"><?= htmlspecialchars($staff['sm_role']) ?></span>
        <strong><?= htmlspecialchars($staff['name']) ?></strong>
      </div>
    </div>
    <p style="color:#999;font-size:13px">Signed in via shared G2 Tools identity (<?= htmlspecialchars($staff['email']) ?>). Phase 2 (staff auth) is complete — Calendars, Posts, Clients, and Publishing screens come next.</p>
  </main>
</div>
</body>
</html>

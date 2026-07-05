<?php
// PUBLIC — no login required
if (session_status() === PHP_SESSION_NONE) session_start();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Submitted — Grey Doha</title>
<link rel="stylesheet" href="/form.css">
<style>
  body { background: #f6f7f9; min-height: 100vh; display: flex; flex-direction: column;
         align-items: center; justify-content: center; padding: 40px 16px; }
  .ty-card { max-width: 480px; width: 100%; background: #fff; border-radius: 18px;
             border: 1.5px solid #e8eaee; padding: 48px 40px; text-align: center; }
  .ty-icon { width: 72px; height: 72px; background: #f0fdf4; border: 2px solid #bbf7d0;
             border-radius: 50%; display: flex; align-items: center; justify-content: center;
             font-size: 32px; margin: 0 auto 24px; }
  .ty-title { font-size: 24px; font-weight: 800; color: #1a1a1a; margin-bottom: 10px; }
  .ty-body  { font-size: 14px; color: #888; line-height: 1.7; margin-bottom: 32px; }
  .ty-body strong { color: #555; }
  .ty-logo  { margin-bottom: 24px; }
  .notice   { background: #fffbeb; border: 1.5px solid #fde68a; border-left: 4px solid #f59e0b;
              border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e;
              text-align: left; line-height: 1.6; }
</style>
</head>
<body>
<div class="ty-card">
  <div class="ty-logo">
    <img src="/grey.jpeg" style="height:36px;object-fit:contain;max-width:140px">
  </div>
  <div class="ty-icon">✓</div>
  <div class="ty-title">Registration Submitted!</div>
  <div class="ty-body">
    Thank you for submitting your vendor registration.<br>
    Our <strong>Finance team</strong> has been notified and will review your application.<br><br>
    You will be contacted if additional information is required.
  </div>
  <div class="notice">
    <strong style="display:block;font-weight:700;color:#b45309;margin-bottom:4px">Qatar Tax Notice</strong>
    As per Qatar tax Law, all FOREIGN VENDORS providing services are entitled to 5% withholding tax.
  </div>
</div>
</body>
</html>

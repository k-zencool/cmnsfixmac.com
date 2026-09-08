<?php
/* =========================================================
   admin/scan/resolve.php

   Turns a scanned QR into the admin page it belongs to. The client
   guesses whether a decode is routable so it does not round-trip on
   every stray QR, but the guess is never trusted here — this file
   re-parses and re-validates before it looks anything up.

   Auth deliberately matches admin/warranty/view.php exactly
   (require_login, no extra perm): scanning must not become a way into
   a page you could not otherwise open, and must not lock out a role
   that can already open it by hand.
   ========================================================= */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_login();

/* Back to the scanner with a reason. The raw value rides along so the
   page can show what it actually read — "ไม่พบ" with nothing on screen
   is impossible to act on when the slip is right there in your hand. */
function scan_back(string $err, string $raw): void {
    header('Location: index.php?err=' . urlencode($err) . '&raw=' . urlencode(mb_substr($raw, 0, 120)));
    exit();
}

$raw = trim((string)($_GET['q'] ?? ''));
if ($raw === '') scan_back('empty', '');

/* The QRs this system prints encode /warranty/?q=<warranty_no> (see
   admin/warranty/print.php), but the same slip read by a generic barcode
   app hands over the bare number instead. Accept both shapes and nothing
   else — this is an exact-id lookup, not a search box. */
$no = preg_match('~[?&]q=([^&\s]+)~', $raw, $m) ? urldecode($m[1]) : $raw;
$no = trim($no);

/* Two live formats: W-2026-0055 (older) and WJ-202606-0292 (current). */
if (!preg_match('/^WJ?-\d{4,6}-\d{1,6}$/i', $no)) scan_back('format', $raw);

$st = $pdo->prepare("SELECT id FROM warranties WHERE warranty_no = ? LIMIT 1");
$st->execute([$no]);
$id = (int)$st->fetchColumn();

if (!$id) scan_back('notfound', $no);

header('Location: ../warranty/view.php?id=' . $id);
exit();

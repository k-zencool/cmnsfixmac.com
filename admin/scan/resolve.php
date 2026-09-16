<?php
/* =========================================================
   admin/scan/resolve.php

   Turns a scanned QR into the admin page it belongs to — a warranty
   slip (?q=<warranty_no>) or a repair-number sticker (?t=<ticket_number>). The client
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

/* Repair-number sticker (admin/tracking/stickers.php) → ?t=<ticket_number>.
   Stickers are printed before the job exists, so the QR carries the ticket
   number, and an unused number is a normal answer, not an error. */
if (isset($_GET['t'])) {
    require_once __DIR__ . '/../../includes/sticker_lib.php';

    $ticket = trim((string)$_GET['t']);
    $back   = 't=' . $ticket;   // the shape scan.js parses as "same sticker"

    /* Stickers are scanned in the app only. Old prints carry a URL, so a
       phone camera can still land here — send it to the scanner instead of
       the job. (scan.js adds src=app; this steers a workflow, it is not a
       security gate — the page needs a login either way.) */
    if (($_GET['src'] ?? '') !== 'app') {
        header('Location: index.php?err=use_app');
        exit();
    }
    if ($ticket === '' || mb_strlen($ticket) > 50) scan_back('format', $back);

    $st = $pdo->prepare("SELECT id, ticket_number FROM tracking WHERE ticket_number = ? LIMIT 1");
    $st->execute([$ticket]);
    $job = $st->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        // A well-formed V-number nobody has opened a job with yet
        $n = stk_parse_no($ticket);
        scan_back($n !== null ? 'ticket_unused' : 'ticket_notfound', $n !== null ? 't=' . stk_fmt($n) : $back);
    }

    /* A scan is a look-up — the machine is in your hand, you want to see
       the job, not start editing it. Every role lands on the job's detail
       sheet in the list; its edit button is there for roles that may. */
    header('Location: ../tracking/index.php?q=' . urlencode($job['ticket_number']) . '&open=' . (int)$job['id']);
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

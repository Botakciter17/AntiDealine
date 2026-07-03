<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/groups.php';

$groupId = (int)($argv[1] ?? 0);
$content = $argv[2] ?? '';
$attachmentPath = $argv[3] ?? 'null';

if ($groupId > 0) {
    triggerGroupAi($groupId, $content, $attachmentPath === 'null' ? null : $attachmentPath);
}

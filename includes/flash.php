<?php
/**
 * Drivew - Flash message helpers.
 * Call flash('success', 'File uploaded.') to queue a message;
 * call getFlashes() at the top of your page, before including header.php,
 * to pop queued messages into $flashes.
 */

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

function flash(string $type, string $text, string $icon = 'circle-check'): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'text' => $text, 'icon' => $icon];
}

/** Pop and clear all queued flashes. */
function getFlashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

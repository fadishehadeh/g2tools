<?php
const SM_POST_STATUSES = ['Draft','Brief Sent','Artwork Pending','In Review','Approved','Rejected','Published'];

const SM_STATUS_COLORS = [
    'Draft'           => '#9ca3af',
    'Brief Sent'      => '#2563eb',
    'Artwork Pending' => '#f59e0b',
    'In Review'       => '#8b5cf6',
    'Approved'        => '#16a34a',
    'Rejected'        => '#dc2626',
    'Published'       => '#0891b2',
];

const SM_STATUS_BG = [
    'Draft'           => '#f3f4f6',
    'Brief Sent'      => '#eff6ff',
    'Artwork Pending' => '#fffbeb',
    'In Review'       => '#f5f3ff',
    'Approved'        => '#f0fdf4',
    'Rejected'        => '#fef2f2',
    'Published'       => '#ecfeff',
];

function sm_status_badge(string $status): string {
    $color = SM_STATUS_COLORS[$status] ?? '#999';
    $bg    = SM_STATUS_BG[$status] ?? '#f5f5f5';
    return '<span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:' . $bg . ';color:' . $color . '">' . htmlspecialchars($status) . '</span>';
}

/** Guidance text shown in the post detail workspace based on current status. */
function sm_workflow_guidance(string $status): string {
    return match ($status) {
        'Draft'           => 'Fill in the post details and upload artwork, then send the creative brief or move straight to review.',
        'Brief Sent'      => 'Creative brief sent. Waiting on artwork to be produced.',
        'Artwork Pending' => 'Artwork has been requested. Upload the asset when ready.',
        'In Review'       => 'Sent to the client for review. Waiting on their approval or feedback.',
        'Approved'        => 'Client approved this post. Publish manually or queue for direct publishing.',
        'Rejected'        => 'Client requested changes — see feedback below. Revise and resend for review.',
        'Published'       => 'This post has been published.',
        default           => '',
    };
}

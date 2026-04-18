<?php
include "config.php";
header('Content-Type: application/json');

// Fixed colour palette per mechanic (cycles if more than 6 mechanics)
$mechanic_colors = [
    '#f59e0b', // amber  (default / first mechanic)
    '#3b82f6', // blue
    '#10b981', // green
    '#a855f7', // purple
    '#ec4899', // pink
    '#06b6d4', // cyan
];

// Build mechanic → colour map
$mech_colors = [];
$mech_result = $conn->query("SELECT id FROM users ORDER BY id ASC");
$i = 0;
if($mech_result) {
    while($u = $mech_result->fetch_assoc()) {
        $mech_colors[$u['id']] = $mechanic_colors[$i % count($mechanic_colors)];
        $i++;
    }
}

// Status colours for unassigned jobs
$status_colors = [
    'not started' => '#ef4444',  // red
    'in progress' => '#f97316',  // orange
    'complete'    => '#6b7280',  // grey (completed)
];

$result = $conn->query("
    SELECT j.id, j.job_date, j.job_type, j.status, j.assigned_to,
           u.username as assigned_name
    FROM jobs j
    LEFT JOIN users u ON j.assigned_to = u.id
    WHERE j.deleted=0 OR j.deleted IS NULL
");

$events = [];
while($row = $result->fetch_assoc()) {
    // If assigned to a mechanic, use mechanic colour; otherwise use status colour
    if($row['assigned_to'] && isset($mech_colors[$row['assigned_to']])) {
        $color = $mech_colors[$row['assigned_to']];
        $title = $row['job_type'] . ' · ' . $row['assigned_name'];
    } else {
        $color = $status_colors[$row['status']] ?? '#6b7280';
        $title = $row['job_type'];
    }

    // Dim completed jobs regardless of assignment
    if($row['status'] === 'complete') {
        $color  = '#4b5563';
        $opacity = '0.6';
    } else {
        $opacity = '1';
    }

    $events[] = [
        'id'                => $row['id'],
        'title'             => $title,
        'start'             => $row['job_date'],
        'backgroundColor'   => $color,
        'borderColor'       => $color,
        'textColor'         => $row['status'] === 'complete' ? '#9ca3af' : '#000',
        'extendedProps'     => [
            'status'       => $row['status'],
            'assigned_to'  => $row['assigned_to'],
            'assigned_name'=> $row['assigned_name'],
        ],
    ];
}

echo json_encode($events);

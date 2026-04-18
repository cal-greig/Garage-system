<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }

// Load mechanics with their assigned colours (must match get_jobs.php order)
$mechanic_palette = ['#f59e0b','#3b82f6','#10b981','#a855f7','#ec4899','#06b6d4'];
$mechanics = [];
$mres = $conn->query("SELECT id, username FROM users ORDER BY id ASC");
$i = 0;
if($mres) {
    while($u = $mres->fetch_assoc()) {
        $mechanics[] = [
            'name'  => $u['username'],
            'color' => $mechanic_palette[$i % count($mechanic_palette)],
        ];
        $i++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calendar — Garage System</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel: #1a1f2e; --steel-mid: #242938; --steel-light: #2e3447;
        --accent: #f59e0b; --text: #e2e8f0; --text-muted: #7c8a9e;
        --border: rgba(255,255,255,0.07);
    }
    body { font-family: 'Barlow', sans-serif; background: var(--steel); color: var(--text); min-height: 100vh; margin: 0; }
    .page-header {
        background: var(--steel-mid); border-bottom: 1px solid var(--border);
        padding: 20px 28px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    }
    .page-header h1 {
        font-family: 'Barlow Condensed', sans-serif; font-size: 1.8rem;
        font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #fff; margin: 0;
    }

    /* Mechanic legend */
    .legend { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    .legend-item { display: flex; align-items: center; gap: 7px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }
    .legend-dot { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
    .legend-sep { width: 1px; height: 18px; background: var(--border); }
    .legend-unassigned { display: flex; align-items: center; gap: 14px; }
    .legend-status { display: flex; align-items: center; gap: 7px; font-size: 0.78rem; color: var(--text-muted); }
    .legend-sq { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

    .cal-wrap { padding: 24px 28px; }

    /* FullCalendar dark theme */
    .fc { --fc-border-color: rgba(255,255,255,0.08); --fc-page-bg-color: var(--steel-mid);
          --fc-neutral-bg-color: var(--steel-light); --fc-list-event-hover-bg-color: var(--steel-light);
          --fc-today-bg-color: rgba(245,158,11,0.08); }
    .fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid { border-color: rgba(255,255,255,0.08); }
    .fc .fc-col-header-cell-cushion, .fc .fc-daygrid-day-number { color: var(--text-muted); text-decoration: none; font-size: 0.82rem; }
    .fc .fc-toolbar-title { color: #fff; font-family: 'Barlow Condensed', sans-serif; font-size: 1.4rem; font-weight: 700; }
    .fc .fc-button { background: var(--steel-light); border-color: var(--border); color: var(--text); font-family: 'Barlow', sans-serif; }
    .fc .fc-button:hover { background: rgba(245,158,11,0.2); border-color: var(--accent); color: var(--accent); }
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active { background: var(--accent); border-color: var(--accent); color: #000; }
    .fc-daygrid-body { background: var(--steel-mid); }
    .fc-scrollgrid-sync-table { background: var(--steel-mid); }
    .fc-col-header { background: var(--steel-light); }
    .fc-event { font-size: 0.78rem; font-weight: 600; border-radius: 4px; padding: 1px 4px; }
</style>
</head>
<body>

<div class="page-header">
    <h1>Calendar</h1>
    <div class="legend">
        <?php if(!empty($mechanics)): ?>
            <?php foreach($mechanics as $m): ?>
                <div class="legend-item">
                    <div class="legend-dot" style="background:<?php echo $m['color']; ?>;box-shadow:0 0 6px <?php echo $m['color']; ?>55;"></div>
                    <?php echo htmlspecialchars($m['name']); ?>
                </div>
            <?php endforeach; ?>
            <div class="legend-sep"></div>
        <?php endif; ?>
        <div class="legend-unassigned">
            <div class="legend-status"><div class="legend-sq" style="background:#ef4444;"></div>Not Started</div>
            <div class="legend-status"><div class="legend-sq" style="background:#f97316;"></div>In Progress</div>
            <div class="legend-status"><div class="legend-sq" style="background:#4b5563;"></div>Complete</div>
        </div>
    </div>
</div>

<div class="cal-wrap">
    <div id="calendar"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        events: 'get_jobs.php',
        eventClick: function(info){ window.location = "view_job.php?id=" + info.event.id; },
        eventDidMount: function(info) {
            // Add tooltip showing assigned mechanic
            var assigned = info.event.extendedProps.assigned_name;
            if(assigned) {
                info.el.title = info.event.title + '\nAssigned: ' + assigned;
            }
        }
    });
    calendar.render();
    setInterval(function(){ calendar.refetchEvents(); }, 30000);
});
</script>
</body>
</html>

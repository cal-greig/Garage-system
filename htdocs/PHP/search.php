<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }

$q        = trim($_GET["q"] ?? "");
$filter   = $_GET["filter"] ?? "all";  // all, jobs, quotes
$results  = [];
$searched = $q !== "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--steel:#1a1f2e;--steel-mid:#242938;--steel-light:#2e3447;--accent:#f59e0b;--green:#10b981;--red:#ef4444;--text:#e2e8f0;--text-muted:#7c8a9e;--border:rgba(255,255,255,0.07);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .search-hero{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:28px;}
    .search-hero h1{font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;margin-bottom:18px;}

    .search-bar{display:flex;gap:10px;max-width:700px;}
    .search-input{flex:1;background:var(--steel-light);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'Barlow',sans-serif;font-size:1rem;padding:12px 16px;transition:border-color 0.2s;}
    .search-input:focus{outline:none;border-color:var(--accent);}
    .search-input::placeholder{color:var(--text-muted);}
    .search-btn{padding:12px 24px;background:var(--accent);color:#000;border:none;border-radius:8px;font-family:'Barlow',sans-serif;font-weight:700;font-size:0.95rem;cursor:pointer;transition:background 0.15s;white-space:nowrap;}
    .search-btn:hover{background:#d97706;}

    .filter-row{display:flex;gap:8px;margin-top:14px;}
    .filter-btn{padding:5px 14px;border-radius:20px;font-size:0.8rem;font-weight:600;border:1px solid var(--border);color:var(--text-muted);background:transparent;cursor:pointer;transition:all 0.15s;text-decoration:none;}
    .filter-btn:hover{color:var(--text);border-color:rgba(255,255,255,0.2);}
    .filter-btn.active{background:var(--accent);color:#000;border-color:var(--accent);}

    .content{max-width:900px;margin:0 auto;padding:28px;}

    .results-header{font-size:0.82rem;color:var(--text-muted);margin-bottom:16px;}
    .results-header strong{color:var(--accent);}

    .section-label{font-family:'Barlow Condensed',sans-serif;font-size:0.8rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin:24px 0 10px;display:flex;align-items:center;gap:8px;}
    .section-label::after{content:'';flex:1;height:1px;background:var(--border);}

    .result-card{background:var(--steel-mid);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin-bottom:8px;display:flex;align-items:center;gap:14px;text-decoration:none;transition:all 0.15s;cursor:pointer;}
    .result-card:hover{border-color:var(--accent);background:var(--steel-light);}

    .result-icon{width:36px;height:36px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
    .icon-job{background:rgba(245,158,11,0.12);}
    .icon-quote{background:rgba(16,185,129,0.12);}

    .result-main{flex:1;min-width:0;}
    .result-title{font-weight:700;color:#fff;font-size:0.95rem;margin-bottom:3px;}
    .result-title mark{background:rgba(245,158,11,0.25);color:var(--accent);border-radius:2px;padding:0 2px;}
    .result-sub{font-size:0.8rem;color:var(--text-muted);}
    .result-sub mark{background:rgba(245,158,11,0.2);color:#f5c85a;border-radius:2px;padding:0 2px;}

    .result-meta{text-align:right;flex-shrink:0;}
    .result-ref{font-family:'Barlow Condensed',sans-serif;font-size:0.85rem;font-weight:700;color:var(--accent);}
    .result-date{font-size:0.75rem;color:var(--text-muted);margin-top:2px;}

    .status-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;}
    .dot-pending{background:#f59e0b;} .dot-accepted{background:#10b981;} .dot-declined{background:#ef4444;}
    .dot-not-started{background:#7c8a9e;} .dot-in-progress{background:#3b82f6;} .dot-completed{background:#10b981;}

    .empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
    .empty-icon{font-size:3rem;margin-bottom:12px;}
    .empty-title{font-size:1rem;font-weight:600;color:var(--text);margin-bottom:6px;}
    .empty-sub{font-size:0.85rem;}

    .hint-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:24px;}
    .hint-card{background:var(--steel-mid);border:1px solid var(--border);border-radius:8px;padding:14px 16px;}
    .hint-label{font-size:0.7rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;}
    .hint-example{font-size:0.85rem;color:var(--text);}
</style>
</head>
<body>

<div class="search-hero">
    <h1>Search</h1>
    <form method="GET" action="search.php">
        <div class="search-bar">
            <input
                class="search-input"
                type="text"
                name="q"
                value="<?php echo htmlspecialchars($q); ?>"
                placeholder="Search by customer name, registration, vehicle, phone..."
                autofocus
            >
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <button class="search-btn" type="submit">Search</button>
        </div>
        <div class="filter-row">
            <?php foreach(["all"=>"All","jobs"=>"Jobs only","quotes"=>"Quotes only"] as $val=>$label): ?>
                <a class="filter-btn <?php echo $filter===$val?'active':''; ?>"
                   href="search.php?q=<?php echo urlencode($q); ?>&filter=<?php echo $val; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<div class="content">

<?php if(!$searched): ?>
    <!-- Landing hints -->
    <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <div class="empty-title">Search jobs and quotes</div>
        <div class="empty-sub">Find anything by customer name, vehicle, registration, phone number or job type</div>
    </div>
    <div class="hint-grid">
        <div class="hint-card"><div class="hint-label">Customer name</div><div class="hint-example">John Smith</div></div>
        <div class="hint-card"><div class="hint-label">Registration</div><div class="hint-example">AB12 CDE</div></div>
        <div class="hint-card"><div class="hint-label">Vehicle</div><div class="hint-example">Ford Focus</div></div>
        <div class="hint-card"><div class="hint-label">Phone</div><div class="hint-example">07700 000000</div></div>
        <div class="hint-card"><div class="hint-label">Job type</div><div class="hint-example">Full Service</div></div>
        <div class="hint-card"><div class="hint-label">Partial match</div><div class="hint-example">focu or AB12</div></div>
    </div>

<?php else:
    $safe = $conn->real_escape_string($q);
    $like = "%$safe%";

    $job_results   = [];
    $quote_results = [];

    // Search jobs
    if($filter === "all" || $filter === "jobs") {
        $stmt = $conn->prepare("
            SELECT id, job_date, customer_name, vehicle, registration, job_type, status
            FROM jobs
            WHERE (deleted = 0 OR deleted IS NULL)
            AND (
                customer_name  LIKE ? OR
                registration   LIKE ? OR
                vehicle        LIKE ? OR
                job_type       LIKE ? OR
                description    LIKE ?
            )
            ORDER BY job_date DESC
            LIMIT 50
        ");
        $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while($r = $res->fetch_assoc()) $job_results[] = $r;
    }

    // Search quotes
    if($filter === "all" || $filter === "quotes") {
        // Check quotes table exists first
        $tbl = $conn->query("SHOW TABLES LIKE 'quotes'");
        if($tbl && $tbl->num_rows > 0) {
            $stmt2 = $conn->prepare("
                SELECT id, created_at, customer_name, customer_phone, vehicle, registration, job_type, status, total_amount
                FROM quotes
                WHERE (
                    customer_name   LIKE ? OR
                    registration    LIKE ? OR
                    vehicle         LIKE ? OR
                    job_type        LIKE ? OR
                    customer_phone  LIKE ? OR
                    contact_source  LIKE ? OR
                    description     LIKE ?
                )
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt2->bind_param("sssssss", $like, $like, $like, $like, $like, $like, $like);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while($r = $res2->fetch_assoc()) $quote_results[] = $r;
        }
    }

    $total = count($job_results) + count($quote_results);

    // Helper: highlight matched term in a string
    function highlight($text, $query) {
        if(!$query || !$text) return htmlspecialchars($text);
        $escaped = preg_quote(htmlspecialchars($query), '/');
        return preg_replace('/(' . $escaped . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
    }

    // Status dot helper
    function status_dot($status) {
        $map = [
            'not started'=>'dot-not-started','in progress'=>'dot-in-progress','completed'=>'dot-completed',
            'pending'=>'dot-pending','accepted'=>'dot-accepted','declined'=>'dot-declined'
        ];
        $cls = $map[strtolower($status)] ?? 'dot-pending';
        return "<span class='status-dot $cls'></span>" . ucfirst($status);
    }

    if($total === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">😕</div>
            <div class="empty-title">No results for "<?php echo htmlspecialchars($q); ?>"</div>
            <div class="empty-sub">Try a different name, partial registration, or vehicle make</div>
        </div>
    <?php else: ?>
        <div class="results-header">
            Found <strong><?php echo $total; ?></strong> result<?php echo $total!==1?'s':''; ?> for "<strong><?php echo htmlspecialchars($q); ?></strong>"
        </div>

        <?php if(!empty($job_results)): ?>
        <div class="section-label">Jobs (<?php echo count($job_results); ?>)</div>
        <?php foreach($job_results as $j):
            $ref  = "JOB-" . str_pad($j["id"], 4, "0", STR_PAD_LEFT);
            $date = date("d M Y", strtotime($j["job_date"]));
            $sub  = array_filter([
                $j["vehicle"]      ? highlight($j["vehicle"], $q)      : null,
                $j["registration"] ? highlight($j["registration"], $q) : null,
                $j["job_type"]     ? highlight($j["job_type"], $q)     : null,
            ]);
        ?>
            <a class="result-card" href="view_job.php?id=<?php echo $j["id"]; ?>">
                <div class="result-icon icon-job">🔧</div>
                <div class="result-main">
                    <div class="result-title">
                        <a href="customer.php?name=<?php echo urlencode($j['customer_name']); ?>"
                           style="color:var(--accent);text-decoration:none;"
                           onclick="event.stopPropagation();"
                           title="View customer history">
                            <?php echo highlight($j["customer_name"], $q); ?>
                        </a>
                    </div>
                    <div class="result-sub">
                        <?php echo implode(" · ", $sub); ?>
                        &nbsp;·&nbsp; <?php echo status_dot($j["status"]); ?>
                    </div>
                </div>
                <div class="result-meta">
                    <div class="result-ref"><?php echo $ref; ?></div>
                    <div class="result-date"><?php echo $date; ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if(!empty($quote_results)): ?>
        <div class="section-label">Quotes (<?php echo count($quote_results); ?>)</div>
        <?php foreach($quote_results as $qt):
            $ref  = "QUO-" . str_pad($qt["id"], 4, "0", STR_PAD_LEFT);
            $date = date("d M Y", strtotime($qt["created_at"]));
            $sub  = array_filter([
                $qt["vehicle"]       ? highlight($qt["vehicle"], $qt) : null,
                $qt["registration"]  ? highlight($qt["registration"], $q) : null,
                $qt["customer_phone"]? highlight($qt["customer_phone"], $q) : null,
                $qt["job_type"]      ? highlight($qt["job_type"], $q) : null,
            ]);
        ?>
            <a class="result-card" href="view_quote.php?id=<?php echo $qt["id"]; ?>">
                <div class="result-icon icon-quote">📋</div>
                <div class="result-main">
                    <div class="result-title">
                        <a href="customer.php?name=<?php echo urlencode($qt['customer_name']); ?>"
                           style="color:var(--accent);text-decoration:none;"
                           onclick="event.stopPropagation();"
                           title="View customer history">
                            <?php echo highlight($qt["customer_name"], $q); ?>
                        </a>
                    </div>
                    <div class="result-sub">
                        <?php echo implode(" · ", $sub); ?>
                        &nbsp;·&nbsp; <?php echo status_dot($qt["status"]); ?>
                    </div>
                </div>
                <div class="result-meta">
                    <div class="result-ref"><?php echo $ref; ?></div>
                    <div class="result-date"><?php echo $date; ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>

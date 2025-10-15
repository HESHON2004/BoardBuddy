<?php
session_start();
include '../db.php';
require '../vendor/autoload.php';
use Mpdf\Mpdf;

// ✅ Admin authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../signin.php");
    exit;
}

// ✅ Fetch feedback count grouped by date (daily)
$query = "
    SELECT DATE(created_at) AS feedback_date, COUNT(*) AS count
    FROM feedback
    GROUP BY feedback_date
    ORDER BY feedback_date ASC
";
$result = $conn->query($query);

$dates = [];
$counts = [];
$total = 0;
$tableRows = '';

// ✅ Prepare data for table + chart
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['feedback_date'];
        $count = $row['count'];
        $dates[] = $date;
        $counts[] = $count;
        $total += $count;
    }
}

// ✅ Build table rows
foreach ($dates as $i => $date) {
    $count = $counts[$i];
    $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
    $tableRows .= "
        <tr>
            <td style='border:1px solid #ddd; padding:8px;'>$date</td>
            <td style='border:1px solid #ddd; padding:8px;'>$count</td>
            <td style='border:1px solid #ddd; padding:8px;'>$percentage%</td>
        </tr>
    ";
}

// ✅ Total row
$tableRows .= "
    <tr style='font-weight:bold;'>
        <td style='border:1px solid #ddd; padding:8px;'>Total</td>
        <td style='border:1px solid #ddd; padding:8px;'>$total</td>
        <td style='border:1px solid #ddd; padding:8px;'>100%</td>
    </tr>
";

// ✅ Chart configuration
$chartConfig = [
    'type' => 'bar',
    'data' => [
        'labels' => $dates,
        'datasets' => [[
            'label' => 'Feedback Count',
            'data' => $counts,
            'backgroundColor' => '#2196F3'
        ]]
    ],
    'options' => [
        'plugins' => ['legend' => ['display' => false]],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'ticks' => ['precision' => 0]
            ]
        ]
    ]
];

// ✅ Fetch chart as base64
$chartUrl = "https://quickchart.io/chart?width=800&height=500&c=" . urlencode(json_encode($chartConfig));
$chartImage = @file_get_contents($chartUrl);
$chartBase64 = $chartImage ? 'data:image/png;base64,' . base64_encode($chartImage) : null;

// ✅ Generate PDF
$mpdf = new Mpdf();
$mpdf->SetTitle('Feedback Volume Over Time Report');

$html = '
    <h1 style="text-align:center; color:#333;">Feedback Volume Over Time</h1>
    <p style="text-align:center; font-size:14px; color:#666;">Generated on: '.date("Y-m-d H:i:s").'</p>
    <hr style="margin:15px 0;">

    <h3 style="color:#333;">Daily Summary</h3>
    <table style="width:100%; border-collapse:collapse; margin-bottom:15px; font-size:13px;">
        <tr>
            <th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Date</th>
            <th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Feedback Count</th>
            <th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Percentage</th>
        </tr>'
        .$tableRows.
    '</table>
';

if ($chartBase64) {
    $html .= '
        <div style="text-align:center; margin-top:25px;">
            <h3 style="color:#333;">Feedback Volume Over Time</h3>
            <img src="'.$chartBase64.'" style="width:90%; max-width:700px;" />
        </div>
    ';
} else {
    $html .= '<p style="text-align:center; color:red;">⚠️ Could not load chart. Check your internet connection.</p>';
}

$mpdf->WriteHTML($html);
$filename = 'Feedback_Volume_Over_Time_'.date("Ymd_His").'.pdf';
$mpdf->Output($filename, 'D');
exit;

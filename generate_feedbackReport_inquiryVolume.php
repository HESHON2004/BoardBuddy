<?php
session_start();
include '../db.php';
require '../vendor/autoload.php';
use Mpdf\Mpdf;

// Check admin authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
  header("Location: ../signin.php");
  exit;
}

// Fetch inquiry volume grouped by month
$query = "
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count 
  FROM inquiries 
  GROUP BY month 
  ORDER BY month ASC
";
$result = $conn->query($query);

$months = [];
$counts = [];
$total = 0;
$tableRows = '';

// Prepare data for table + chart
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $month = $row['month'];
    $count = $row['count'];
    $months[] = $month;
    $counts[] = $count;
    $total += $count;
  }
}

// Build table rows
foreach ($months as $i => $month) {
  $count = $counts[$i];
  $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
  $tableRows .= "
    <tr>
      <td style='border:1px solid #ddd; padding:8px;'>$month</td>
      <td style='border:1px solid #ddd; padding:8px;'>$count</td>
      <td style='border:1px solid #ddd; padding:8px;'>$percentage%</td>
    </tr>
  ";
}

// Total row
$tableRows .= "
  <tr style='font-weight:bold;'>
    <td style='border:1px solid #ddd; padding:8px;'>Total</td>
    <td style='border:1px solid #ddd; padding:8px;'>$total</td>
    <td style='border:1px solid #ddd; padding:8px;'>100%</td>
  </tr>
";

$chartConfig = [
  'type' => 'bar',
  'data' => [
    'labels' => $months,
    'datasets' => [[
      'label' => 'Inquiries',
      'data' => $counts,
      'backgroundColor' => '#4CAF50'
    ]]
  ],
  'options' => [
    'plugins' => [
      'legend' => ['display' => false]
    ],
    'scales' => [
      'y' => [
        'beginAtZero' => true,
        'min' => 0,
        'ticks' => [
          'precision' => 0
        ]
      ],
      'x' => [
        'ticks' => [
          'precision' => 0
        ]
      ]
    ]
  ]
];

$chartUrl = "https://quickchart.io/chart?width=800&height=500&c=" . urlencode(json_encode($chartConfig));
$chartImage = file_get_contents($chartUrl);
$chartBase64 = 'data:image/png;base64,' . base64_encode($chartImage);

// Generate PDF
$mpdf = new Mpdf();
$mpdf->SetTitle('Inquiry Volume by Month Report');

$html = '
  <h1 style="text-align:center; color:#333;">Inquiry Volume by Month Report</h1>
  <p style="text-align:center; font-size:14px; color:#666;">Generated on: '.date("Y-m-d H:i:s").'</p>
  <hr style="margin:15px 0;">

  <h3 style="color:#333;">Monthly Summary</h3>
  <table style="width:100%; border-collapse:collapse; margin-bottom:15px;">
    <tr>
      <th style="border:1px solid #ddd; padding:8px; text-align:left;">Month</th>
      <th style="border:1px solid #ddd; padding:8px; text-align:left;">Inquiries</th>
      <th style="border:1px solid #ddd; padding:8px; text-align:left;">Percentage</th>
    </tr>'
    .$tableRows.
  '</table>

  <div style="text-align:center;">
    <h3 style="color:#333;">Inquiry Distribution</h3>
    <img src="'.$chartBase64.'" style="width:90%; max-width:700px;" />
  </div>
';

$mpdf->WriteHTML($html);
$filename = 'Inquiry_Volume_By_Month_Report_'.date("Ymd_His").'.pdf';
$mpdf->Output($filename, 'D');
exit;

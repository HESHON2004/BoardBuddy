<?php
include 'db.php'; // database connection

// Capture filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$price_min = isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? $_GET['price_max'] : '';
$billing_cycle = isset($_GET['billing_cycle']) ? trim($_GET['billing_cycle']) : '';

// Build dynamic WHERE clauses
$conditions = [];

if ($search !== '') {
    $search_esc = $conn->real_escape_string($search);
    $conditions[] = "(p.title LIKE '%$search_esc%' OR p.description LIKE '%$search_esc%')";
}

if ($location !== '') {
    $location_esc = $conn->real_escape_string($location);
    $conditions[] = "p.location LIKE '%$location_esc%'";
}

if ($price_min !== '') {
    $conditions[] = "p.price >= $price_min";
}

if ($price_max !== '') {
    $conditions[] = "p.price <= $price_max";
}

if ($billing_cycle !== '') {
    $billing_cycle_esc = $conn->real_escape_string($billing_cycle);
    $conditions[] = "p.billing_cycle = '$billing_cycle_esc'";
}

// Combine conditions
$where = '';
if (count($conditions) > 0) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

// Fetch properties with their first photo
$sql = "SELECT p.*, ph.photo_path
        FROM properties p
        LEFT JOIN property_photos ph ON p.id = ph.property_id
        $where
        GROUP BY p.id
        ORDER BY p.created_at DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browse Listings - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="styles/navigation.css">
<style>
    body {
        background-color: #f9f9f9;
    }

    .container {
        max-width: 1300px;
        margin-top: 40px;
    }

    /* Filter Sidebar */
    .filter-sidebar .input-group input {
        border-radius: 8px 0 0 8px;
    }

    .filter-sidebar .input-group button {
        border-radius: 0 8px 8px 0;
        border-color: #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .filter-sidebar {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        border: 1px solid #ddd;
    }
    .filter-sidebar h5 {
        font-weight: bold;
        margin-bottom: 15px;
    }
    .filter-sidebar label {
        font-size: 14px;
        margin-top: 8px;
    }
    .filter-sidebar .form-control,
    .filter-sidebar .form-select {
        border-radius: 8px;
        font-size: 14px;
    }
    .filter-sidebar .btn-apply {
        background-color: #e67e22;
        color: white;
        border-radius: 8px;
        width: 100%;
        margin-top: 15px;
        transition: background 0.3s ease;
    }
    .filter-sidebar .btn-apply:hover {
        background-color: #cf711f;
    }

    /* Property Card */
    .property-card {
        background: #fff;
        border-radius: 15px;
        overflow: hidden;
        border: 1px solid #ddd;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .property-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .property-img {
        height: 220px;
        object-fit: cover;
        width: 100%;
        transition: transform 0.3s;
    }
    .property-card:hover .property-img {
        transform: scale(1.05);
    }
    .card-body {
        padding: 18px;
    }
    .card-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .card-text {
        font-size: 14px;
        color: #555;
        margin-bottom: 8px;
    }
    .price {
        font-weight: bold;
        color: #e67e22;
        font-size: 16px;
        margin-bottom: 10px;
    }
    .btn-view {
        background-color: #e67e22;
        color: #fff;
        border-radius: 8px;
        width: 100%;
        text-align: center;
        padding: 10px;
        font-weight: 500;
        transition: background 0.3s;
    }
    .btn-view:hover {
        background-color: #cf711f;
        color: #fff;
        text-decoration: none;
    }

    /* Section Title */
    h2.section-title {
        font-weight: bold;
        color: #333;
        margin-bottom: 25px;
    }
    
</style>
</head>
<body>

<!-- Navbar -->
<?php include 'navigation/userNav.php'; ?>

<div class="container">
    <div class="row" style="gap: 30px;">
        <!-- Filter Sidebar -->
        <div class="col-md-3" style="margin-right: 10px;">
            <div class="filter-sidebar">
                <h5>Filter Listings</h5>
                <form method="GET" action="">
                    <!-- Search Bar -->
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="search" placeholder="Search by title or description">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <label for="location">Location</label>
                    <input type="text" class="form-control" id="location" name="location" placeholder="Enter location">

                    <label for="price_min">Min Price</label>
                    <input type="number" class="form-control" id="price_min" name="price_min" placeholder="0">

                    <label for="price_max">Max Price</label>
                    <input type="number" class="form-control" id="price_max" name="price_max" placeholder="50000">

                    <label for="billing_cycle">Billing Cycle</label>
                    <select class="form-select" id="billing_cycle" name="billing_cycle">
                        <option value="">Any</option>
                        <option value="Per Month">Per Month</option>
                        <option value="Per Week">Per Week</option>
                        <option value="Per Day">Per Day</option>
                    </select>

                    <button type="submit" class="btn btn-apply">Apply Filters</button>
                </form>
            </div>
        </div>


        <!-- Listings Section -->
        <div class="col-md-8">
            <h2 class="section-title">Available Properties</h2>
            <div class="row g-4">
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $photo = !empty($row['photo_path']) ? 'uploads/'.$row['photo_path'] : 'uploads/default.jpg';
                        echo '
                        <div class="col-md-6 col-lg-4">
                            <div class="card property-card">
                                <img src="'.$photo.'" class="card-img-top property-img" alt="'.htmlspecialchars($row['title']).'">
                                <div class="card-body">
                                    <h5 class="card-title">'.htmlspecialchars($row['title']).'</h5>
                                    <p class="card-text">'.htmlspecialchars(substr($row['description'],0,120)).'...</p>
                                    <p><small class="text-muted">'.htmlspecialchars($row['location']).'</small></p>
                                    <p class="price">Rs. '.number_format($row['price']).' / '.strtolower(str_replace("Per ","",$row['billing_cycle'])).'</p>
                                    <a href="property.php?id='.$row['id'].'" class="btn btn-view">View Property</a>
                                </div>
                            </div>
                        </div>
                        ';
                    }
                } else {
                    echo "<p class='text-center'>No properties found.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include 'navigation/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

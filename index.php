<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neo Asteroid Stats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script> <!-- For adding percentage labels -->
</head>
<body class="container">

    <h1 class="mt-5">Near Earth Objects Statistics</h1>
    <form method="POST" action="index.php">
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
        </div>
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>

    <script>
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() -1);
        const maxDate = yesterday.toISOString().split('T')[0];

        document.getElementById('start_date').setAttribute('max', maxDate);


        document.getElementById('end_date').setAttribute('max', maxDate);
    </script>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
        $start_date = $_POST['start_date'];

        $end_date = $_POST['end_date'];

        $api_key = 'KPCwqtBo6wo6qB4zrnB9eJWeDHYYRbjvOzVUGGP8'; 

        $nasa_url = "https://api.nasa.gov/neo/rest/v1/feed?start_date=$start_date&end_date=$end_date&api_key=$api_key";

        $response = file_get_contents($nasa_url);

        $data = json_decode($response, true);

        if ($data && isset($data['near_earth_objects'])) {

            $neoObject = $data['near_earth_objects'];
            $stats = [
                'fastest' => null,
                'closest' => null,
                'average_size' => 0,
                'daily_counts' => [],
                'size_distribution' => [
                    '0-0.10 km' => 0,
                    '0.10-0.30 km' => 0,
                    '0.30-0.50 km' => 0,
                    '0.50-1.00 km' => 0,
                    '1.00-2.00 km' => 0,
                    '>2.00 km' => 0,
                ],
                'asteroids_info' => []
            ];

            $totalSize = 0;
            $total_objects = 0;

            foreach ($neoObject as $date => $asteroids) {


                $daily_count = count($asteroids);

                $stats['daily_counts'][$date] = $daily_count;


                foreach ($asteroids as $asteroid) {


                    $speed = $asteroid['close_approach_data'][0]['relative_velocity']['kilometers_per_hour'];
                    $distance = $asteroid['close_approach_data'][0]['miss_distance']['kilometers'];

                    $size = ($asteroid['estimated_diameter']['kilometers']['estimated_diameter_min'] + $asteroid['estimated_diameter']
                    ['kilometers']['estimated_diameter_max']) / 2;

                    
                    $stats['asteroids_info'][] = [

                        'name' => $asteroid['name'],
                        'speed' => number_format($speed),
                        'distance' => number_format($distance, 2),
                        'size' => number_format($size, 2),
                    ];

                    if ($size <= 0.10) {

                        $stats['size_distribution']['0-0.10 km']++;

                    } elseif ($size <= 0.30) {
                        $stats['size_distribution']['0.10-0.30 km']++;

                    } elseif ($size <= 0.50) {
                        $stats['size_distribution']['0.30-0.50 km']++;
                    } elseif ($size <= 1.00) {
                        $stats['size_distribution']['0.50-1.00 km']++;

                    } elseif ($size <= 2.00) {
                        $stats['size_distribution']['1.00-2.00 km']++;
                    } else {
                        $stats['size_distribution']['>2.00 km']++;

                    }

                    if (!$stats['fastest'] || $speed > $stats['fastest']['speed']) {
                        $stats['fastest'] = ['name' => $asteroid['name'], 'speed' => $speed];

                    }

                    if (!$stats['closest'] || $distance < $stats['closest']['distance']) {

                        $stats['closest'] = ['name' => $asteroid['name'], 'distance' => $distance];
                    }

                    $totalSize += $size;
                    $total_objects++;

                }
            }

            $stats['average_size'] = $totalSize / $total_objects;

            echo "<h2 class='mt-5'>Statistics for {$start_date} to {$end_date}</h2>";
            echo "<p><strong>Fastest Asteroid :-</strong> {$stats['fastest']['name']} at " . number_format($stats['fastest']['speed']) . " km/h</p>";
            echo "<p><strong>Closest Asteroid :-</strong> {$stats['closest']['name']} at " . number_format($stats['closest']['distance'], 2) . " km</p>";
            echo "<p><strong>Average Size of Asteroids :-</strong> " . number_format($stats['average_size'], 2) . " km</p>";
            ?>
            <canvas id="neoLineChart" width="400" height="200"></canvas>
            <script>
                var ctx = document.getElementById('neoLineChart').getContext('2d');
                var neoLineChart = new Chart(ctx, {
                    type: 'line',
                    data: {

                        labels: <?php echo json_encode(array_keys($stats['daily_counts'])); ?>,
                        datasets: [{
                            label: 'Number of Asteroids per Day',
                            data: <?php echo json_encode(array_values($stats['daily_counts'])); ?>,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            fill: false
                        }]
                    },
                    options: {

                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            </script>

            <!-- Pie Chart for Size Distribution with Percentage -->
            <canvas id="neoPieChart" width="400" height="200"></canvas>
            <script>
                var ctxPie = document.getElementById('neoPieChart').getContext('2d');
                var totalAsteroids = <?php echo $total_objects; ?>;
                var neoPieChart = new Chart(ctxPie, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_keys($stats['size_distribution'])); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($stats['size_distribution'])); ?>,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)',
                                'rgba(255, 159, 64, 0.6)'
                            ]
                        }]
                    },
                    options: {
                        plugins: {
                            datalabels: {
                                formatter: (value, ctx) => {
                                    let percentage = (value / totalAsteroids * 100).toFixed(2) + "%";
                                    return percentage;
                                },
                                color: '#fff',
                                font: {
                                    weight: 'bold',
                                    size: 14
                                }
                            }
                        }
                    }
                });
            </script>
      <h3 class="mt-5">Asteroid Details</h3>
            <table class="table table-bordered">
            <thead>
                    <tr>
                        <th>Name</th>
                        <th>Speed (km/h)    </th>
                        <th>Distance from Earth (km)</th>
                        <th>Size (km)</th>
                    </tr>
              </thead>
                <tbody>
                    <?php foreach ($stats['asteroids_info'] as $asteroid): ?>
                        <tr>
                            <td><?= $asteroid['name']; ?></td>
                            <td><?= $asteroid['speed']; ?></td>
                            <td><?= $asteroid['distance']; ?></td>
                            <td><?= $asteroid['size']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
        } else {
            echo "<p class='text-danger'>Errorin fetching data from Astroid API.</p>";
        }
    }
    ?>
</body>
</html>

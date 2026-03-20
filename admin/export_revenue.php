<?php
ob_start();
session_start();



require_once 'vendor/autoload.php'; // For Dompdf
use Dompdf\Dompdf;

try {
    // Include database connection
    $db_path = realpath(__DIR__ . '/../db.php');
    if (!$db_path || !file_exists($db_path)) {
        throw new Exception('Database configuration file not found');
    }
    require_once $db_path;

    // Validate format
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
    if (!in_array($format, ['pdf', 'csv'])) {
        throw new Exception('Invalid export format');
    }

    // Get date range
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }

    // Fetch data (same queries as revenue.php)
    $revenue_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
        FROM orders 
        WHERE payment_status = 'paid' 
        AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $revenue_stmt->execute([$start_date, $end_date]);
    $total_revenue = $revenue_stmt->fetchColumn();

    $revenue_data_stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date, 
            COALESCE(SUM(total_amount), 0) as daily_revenue,
            COUNT(*) as order_count
        FROM orders 
        WHERE payment_status = 'paid' 
        AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $revenue_data_stmt->execute([$start_date, $end_date]);
    $revenue_data = $revenue_data_stmt->fetchAll(PDO::FETCH_ASSOC);

    $category_revenue_stmt = $pdo->prepare("
        SELECT 
            COALESCE(p.type, 'Uncategorized') as category,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COUNT(oi.id) as items_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.payment_status = 'paid'
        AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY p.type
        ORDER BY revenue DESC
    ");
    $category_revenue_stmt->execute([$start_date, $end_date]);
    $category_revenue = $category_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    $top_products_stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            COALESCE(p.type, 'Uncategorized') as category,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COUNT(oi.id) as quantity_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.payment_status = 'paid'
        AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY oi.product_id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $top_products_stmt->execute([$start_date, $end_date]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    $referral_earnings_stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_earnings,
            COUNT(*) as referral_count
        FROM referral_earnings 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $referral_earnings_stmt->execute([$start_date, $end_date]);
    $referral_earnings = $referral_earnings_stmt->fetch(PDO::FETCH_ASSOC);

    $rewards_stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_rewards,
            COUNT(*) as rewards_count
        FROM rewards 
        WHERE status IN ('paid', 'manually_paid')
        AND (paid_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) 
             OR created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    ");
    $rewards_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $rewards_data = $rewards_stmt->fetch(PDO::FETCH_ASSOC);

    $net_revenue = $total_revenue - ($referral_earnings['total_earnings'] ?? 0) - ($rewards_data['total_rewards'] ?? 0);

    if ($format === 'pdf') {
        // Generate PDF
        $dompdf = new Dompdf();
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #e74c3c; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
            </style>
        </head>
        <body>
            <h1>Revenue Report</h1>
            <p>Period: ' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)) . '</p>
            <h2>Summary</h2>
            <p>Total Revenue: Ksh ' . number_format($total_revenue, 2) . '</p>
            <p>Net Revenue: Ksh ' . number_format($net_revenue, 2) . '</p>
            <p>Total Orders: ' . array_sum(array_column($revenue_data, 'order_count')) . '</p>
            <p>Referral Earnings: Ksh ' . number_format($referral_earnings['total_earnings'] ?? 0, 2) . ' (' . ($referral_earnings['referral_count'] ?? 0) . ' referrals)</p>
            <p>Rewards Paid: Ksh ' . number_format($rewards_data['total_rewards'] ?? 0, 2) . ' (' . ($rewards_data['rewards_count'] ?? 0) . ' rewards)</p>
            
            <h2>Revenue by Category</h2>
            <table>
                <tr><th>Category</th><th>Revenue</th><th>Items Sold</th><th>Percentage</th></tr>';
        foreach ($category_revenue as $category) {
            $html .= '<tr>
                <td>' . htmlspecialchars($category['category']) . '</td>
                <td>Ksh ' . number_format($category['revenue'], 2) . '</td>
                <td>' . $category['items_sold'] . '</td>
                <td>' . ($total_revenue > 0 ? number_format(($category['revenue'] / $total_revenue) * 100, 2) : 0) . '%</td>
            </tr>';
        }
        $html .= '</table>
            <h2>Top Products by Revenue</h2>
            <table>
                <tr><th>Product</th><th>Category</th><th>Revenue</th><th>Quantity Sold</th></tr>';
        foreach ($top_products as $product) {
            $html .= '<tr>
                <td>' . htmlspecialchars($product['product_name']) . '</td>
                <td>' . htmlspecialchars($product['category']) . '</td>
                <td>Ksh ' . number_format($product['revenue'], 2) . '</td>
                <td>' . $product['quantity_sold'] . '</td>
            </tr>';
        }
        $html .= '</table>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        ob_end_clean();
        $dompdf->stream('revenue_report_' . $start_date . '_to_' . $end_date . '.pdf', ['Attachment' => true]);
        exit;
    } else {
        // Generate CSV
        ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="revenue_report_' . $start_date . '_to_' . $end_date . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Revenue Report']);
        fputcsv($output, ['Period', date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date))]);
        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Revenue', 'Ksh ' . number_format($total_revenue, 2)]);
        fputcsv($output, ['Net Revenue', 'Ksh ' . number_format($net_revenue, 2)]);
        fputcsv($output, ['Total Orders', array_sum(array_column($revenue_data, 'order_count'))]);
        fputcsv($output, ['Referral Earnings', 'Ksh ' . number_format($referral_earnings['total_earnings'] ?? 0, 2), $referral_earnings['referral_count'] ?? 0 . ' referrals']);
        fputcsv($output, ['Rewards Paid', 'Ksh ' . number_format($rewards_data['total_rewards'] ?? 0, 2), $rewards_data['rewards_count'] ?? 0 . ' rewards']);
        fputcsv($output, []);
        fputcsv($output, ['Revenue by Category']);
        fputcsv($output, ['Category', 'Revenue', 'Items Sold', 'Percentage']);
        foreach ($category_revenue as $category) {
            fputcsv($output, [
                $category['category'],
                'Ksh ' . number_format($category['revenue'], 2),
                $category['items_sold'],
                $total_revenue > 0 ? number_format(($category['revenue'] / $total_revenue) * 100, 2) . '%' : '0%'
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['Top Products by Revenue']);
        fputcsv($output, ['Product', 'Category', 'Revenue', 'Quantity Sold']);
        foreach ($top_products as $product) {
            fputcsv($output, [
                $product['product_name'],
                $product['category'],
                'Ksh ' . number_format($product['revenue'], 2),
                $product['quantity_sold']
            ]);
        }
        fclose($output);
        exit;
    }

} catch (Exception $e) {
    error_log('Error in export_revenue.php: ' . $e->getMessage());
    ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'An error occurred during export. Please try again later.']);
    exit;
}
?>
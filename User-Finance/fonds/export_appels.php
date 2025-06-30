<?php
// API pour exporter les appels de fonds
header('Content-Type: application/json');
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$format = $_GET['format'] ?? 'excel';
$filter = $_GET['filter'] ?? 'all';

try {
    // Construire la requête selon le filtre
    $where_clause = "";
    switch ($filter) {
        case 'en_attente':
            $where_clause = "WHERE af.statut = 'en_attente'";
            break;
        case 'valide':
            $where_clause = "WHERE af.statut = 'valide'";
            break;
        case 'partiellement_valide':
            $where_clause = "WHERE af.statut = 'partiellement_valide'";
            break;
        case 'rejete':
            $where_clause = "WHERE af.statut = 'rejete'";
            break;
    }

    $query = "
        SELECT 
            af.code_appel,
            af.date_creation,
            u.name as demandeur_nom,
            af.designation,
            af.entite,
            af.montant_total,
            af.statut,
            af.date_validation,
            vf.name as validateur_nom
        FROM appels_fonds af
        LEFT JOIN users_exp u ON af.user_id = u.id
        LEFT JOIN users_exp vf ON af.validé_par = vf.id
        $where_clause
        ORDER BY af.date_creation DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $appels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'excel') {
        // Export Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="appels_fonds_' . $filter . '_' . date('Y-m-d') . '.xls"');
        
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"><style>table { border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }</style></head>';
        echo '<body>';
        echo '<table>';
        echo '<tr>';
        echo '<th>Code Appel</th>';
        echo '<th>Date Création</th>';
        echo '<th>Demandeur</th>';
        echo '<th>Désignation</th>';
        echo '<th>Entité</th>';
        echo '<th>Montant Total</th>';
        echo '<th>Statut</th>';
        echo '<th>Date Validation</th>';
        echo '<th>Validateur</th>';
        echo '</tr>';

        foreach ($appels as $appel) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($appel['code_appel']) . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($appel['date_creation'])) . '</td>';
            echo '<td>' . htmlspecialchars($appel['demandeur_nom']) . '</td>';
            echo '<td>' . htmlspecialchars($appel['designation']) . '</td>';
            echo '<td>' . htmlspecialchars($appel['entite']) . '</td>';
            echo '<td>' . number_format($appel['montant_total'], 0, ',', ' ') . ' FCFA</td>';
            echo '<td>' . ucfirst(str_replace('_', ' ', $appel['statut'])) . '</td>';
            echo '<td>' . ($appel['date_validation'] ? date('d/m/Y H:i', strtotime($appel['date_validation'])) : '') . '</td>';
            echo '<td>' . htmlspecialchars($appel['validateur_nom']) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</body></html>';
        
    } else if ($format === 'pdf') {
        // Export PDF simple
        require_once '../fpdf/fpdf.php';
        
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('Rapport Appels de Fonds - ' . ucfirst($filter)), 0, 1, 'C');
        $pdf->Ln(10);
        
        // En-têtes
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 10, 'Code', 1);
        $pdf->Cell(30, 10, 'Date', 1);
        $pdf->Cell(40, 10, 'Demandeur', 1);
        $pdf->Cell(60, 10, utf8_decode('Désignation'), 1);
        $pdf->Cell(30, 10, 'Montant', 1);
        $pdf->Cell(25, 10, 'Statut', 1);
        $pdf->Ln();
        
        // Données
        $pdf->SetFont('Arial', '', 7);
        foreach ($appels as $appel) {
            $pdf->Cell(30, 8, $appel['code_appel'], 1);
            $pdf->Cell(30, 8, date('d/m/Y', strtotime($appel['date_creation'])), 1);
            $pdf->Cell(40, 8, utf8_decode($appel['demandeur_nom']), 1);
            $pdf->Cell(60, 8, utf8_decode(substr($appel['designation'], 0, 40) . '...'), 1);
            $pdf->Cell(30, 8, number_format($appel['montant_total'], 0, ',', ' '), 1);
            $pdf->Cell(25, 8, utf8_decode(ucfirst(str_replace('_', ' ', $appel['statut']))), 1);
            $pdf->Ln();
        }
        
        $pdf->Output('D', 'appels_fonds_' . $filter . '_' . date('Y-m-d') . '.pdf');
    }
    
} catch (Exception $e) {
    error_log("Erreur export: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'export'
    ]);
}
?>
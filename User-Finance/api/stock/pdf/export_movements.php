<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

// Connexion à la base de données
include_once '../../../../database/connection.php';

try {
    // Paramètres
    $format = isset($_GET['format']) ? $_GET['format'] : 'excel';

    // Paramètres de filtrage
    $movementType = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
    $projectCode = isset($_GET['project_code']) ? trim($_GET['project_code']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $productFilter = isset($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
    $searchValue = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Construire la requête complète avec calculs financiers
    $params = [];
    $conditions = [];

    // Vérifier si la table dispatch_details existe
    $checkDispatchTable = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    $dispatchTableExists = $checkDispatchTable->rowCount() > 0;

    // === REQUÊTE PRINCIPALE AVEC CALCULS FINANCIERS ===
    $mainQuery = "
        SELECT 
            sm.id,
            sm.product_id,
            CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
            sm.quantity,
            CONVERT(sm.movement_type USING utf8mb4) COLLATE utf8mb4_general_ci as movement_type,
            CONVERT(COALESCE(sm.provenance, '') USING utf8mb4) COLLATE utf8mb4_general_ci as provenance,
            CONVERT(COALESCE(sm.nom_projet, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
            CONVERT(COALESCE(sm.destination, '') USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
            CONVERT(COALESCE(sm.demandeur, '') USING utf8mb4) COLLATE utf8mb4_general_ci as demandeur,
            sm.date,
            sm.invoice_id,
            CONVERT(COALESCE(c.libelle, '') USING utf8mb4) COLLATE utf8mb4_general_ci as category_name,
            CONVERT(COALESCE(p.unit, '') USING utf8mb4) COLLATE utf8mb4_general_ci as unit,
            CONVERT(COALESCE(sm.notes, '') USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
            -- Calcul de la valeur unitaire
            CASE 
                WHEN sm.movement_type = 'entry' THEN
                    COALESCE(
                        (SELECT CAST(am.prix_unitaire AS DECIMAL(15,2)) 
                         FROM achats_materiaux am 
                         WHERE CONVERT(am.designation USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND CONVERT(am.expression_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND am.status = 'reçu' 
                         AND am.prix_unitaire IS NOT NULL
                         ORDER BY am.date_reception DESC LIMIT 1),
                        COALESCE(p.prix_moyen, 0),
                        COALESCE(p.unit_price, 0),
                        0
                    )
                WHEN sm.movement_type = 'return' THEN
                    COALESCE(p.prix_moyen, p.unit_price, 0)
                ELSE 
                    COALESCE(p.prix_moyen, p.unit_price, 0)
            END as prix_unitaire,
            -- Calcul de la valeur totale
            (CASE 
                WHEN sm.movement_type = 'entry' THEN
                    COALESCE(
                        (SELECT CAST(am.prix_unitaire AS DECIMAL(15,2)) 
                         FROM achats_materiaux am 
                         WHERE CONVERT(am.designation USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND CONVERT(am.expression_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci
                         AND am.status = 'reçu' 
                         AND am.prix_unitaire IS NOT NULL
                         ORDER BY am.date_reception DESC LIMIT 1),
                        COALESCE(p.prix_moyen, 0),
                        COALESCE(p.unit_price, 0),
                        0
                    )
                WHEN sm.movement_type = 'return' THEN
                    COALESCE(p.prix_moyen, p.unit_price, 0)
                ELSE 
                    COALESCE(p.prix_moyen, p.unit_price, 0)
            END) * sm.quantity as valeur_totale
        FROM stock_movement sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category = c.id
        WHERE 1=1
    ";

    // Appliquer les filtres
    if (!empty($movementType) && $movementType !== 'all') {
        if ($movementType === 'entry') {
            $conditions[] = "sm.movement_type = 'entry'";
        } elseif ($movementType === 'dispatch') {
            $conditions[] = "1=0"; // Exclure de la requête principale
        } elseif ($movementType === 'supplier-return') {
            $conditions[] = "sm.movement_type = 'output' AND sm.destination LIKE 'Retour fournisseur:%'";
        } else {
            $conditions[] = "sm.movement_type = :movement_type";
            if ($movementType === 'output') {
                $conditions[] = "(sm.destination NOT LIKE 'Retour fournisseur:%' OR sm.destination IS NULL)";
            }
            $params[':movement_type'] = $movementType;
        }
    }

    if (!empty($projectCode)) {
        $conditions[] = "CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci = :project_code";
        $params[':project_code'] = $projectCode;
    }

    if (!empty($dateFrom)) {
        $conditions[] = "DATE(sm.date) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $conditions[] = "DATE(sm.date) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    if (!empty($productFilter)) {
        $conditions[] = "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :product_filter";
        $params[':product_filter'] = "%{$productFilter}%";
    }

    if (!empty($searchValue)) {
        $searchConditions = [
            "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.provenance USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.nom_projet USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.destination USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search",
            "CONVERT(sm.demandeur USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search"
        ];
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $params[':search'] = "%{$searchValue}%";
    }

    if (!empty($conditions)) {
        $mainQuery .= " AND " . implode(" AND ", $conditions);
    }

    // === REQUÊTE DISPATCH ===
    $dispatchQuery = "";
    if ($dispatchTableExists && (empty($movementType) || $movementType === 'entry' || $movementType === 'dispatch')) {
        $dispatchQuery = "
            UNION ALL
            SELECT 
                dd.movement_id as id,
                dd.product_id,
                CONVERT(COALESCE(p.product_name, '') USING utf8mb4) COLLATE utf8mb4_general_ci as product_name,
                dd.allocated as quantity,
                'dispatch' as movement_type,
                CONVERT(COALESCE(sm_orig.provenance, '') USING utf8mb4) COLLATE utf8mb4_general_ci as provenance,
                CONVERT(COALESCE(dd.project, '') USING utf8mb4) COLLATE utf8mb4_general_ci as nom_projet,
                CONVERT(COALESCE(dd.client, '') USING utf8mb4) COLLATE utf8mb4_general_ci as destination,
                CONVERT(COALESCE(sm_orig.demandeur, '') USING utf8mb4) COLLATE utf8mb4_general_ci as demandeur,
                dd.dispatch_date as date,
                sm_orig.invoice_id,
                CONVERT(COALESCE(c.libelle, '') USING utf8mb4) COLLATE utf8mb4_general_ci as category_name,
                CONVERT(COALESCE(p.unit, '') USING utf8mb4) COLLATE utf8mb4_general_ci as unit,
                CONVERT(COALESCE(dd.notes, '') USING utf8mb4) COLLATE utf8mb4_general_ci as notes,
                COALESCE(p.prix_moyen, p.unit_price, 0) as prix_unitaire,
                COALESCE(p.prix_moyen, p.unit_price, 0) * dd.allocated as valeur_totale
            FROM dispatch_details dd
            LEFT JOIN products p ON dd.product_id = p.id
            LEFT JOIN categories c ON p.category = c.id
            LEFT JOIN stock_movement sm_orig ON dd.movement_id = sm_orig.id
            WHERE 1=1
        ";

        // Appliquer les mêmes filtres pour dispatch
        if (!empty($projectCode)) {
            $dispatchQuery .= " AND CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci = :project_code_dispatch";
            $params[':project_code_dispatch'] = $projectCode;
        }

        if (!empty($dateFrom)) {
            $dispatchQuery .= " AND DATE(dd.dispatch_date) >= :date_from_dispatch";
            $params[':date_from_dispatch'] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $dispatchQuery .= " AND DATE(dd.dispatch_date) <= :date_to_dispatch";
            $params[':date_to_dispatch'] = $dateTo;
        }

        if (!empty($productFilter)) {
            $dispatchQuery .= " AND CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :product_filter_dispatch";
            $params[':product_filter_dispatch'] = "%{$productFilter}%";
        }

        if (!empty($searchValue)) {
            $dispatchSearchConditions = [
                "CONVERT(p.product_name USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch",
                "CONVERT(dd.project USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch",
                "CONVERT(dd.client USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :search_dispatch"
            ];
            $dispatchQuery .= " AND (" . implode(" OR ", $dispatchSearchConditions) . ")";
            $params[':search_dispatch'] = "%{$searchValue}%";
        }
    }

    // Requête finale
    $fullQuery = "SELECT * FROM (" . $mainQuery . $dispatchQuery . ") as combined_results ORDER BY date DESC";

    $stmt = $pdo->prepare($fullQuery);
    $stmt->execute($params);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === CALCUL DES STATISTIQUES FINANCIÈRES ===
    $stats = [
        'total_movements' => count($movements),
        'valeur_entries' => 0,
        'valeur_outputs' => 0,
        'valeur_nette' => 0,
        'count_entries' => 0,
        'count_outputs' => 0
    ];

    foreach ($movements as $movement) {
        $valeur = floatval($movement['valeur_totale']);
        if (in_array($movement['movement_type'], ['entry', 'dispatch', 'return'])) {
            $stats['valeur_entries'] += $valeur;
            $stats['count_entries']++;
        } else {
            $stats['valeur_outputs'] += $valeur;
            $stats['count_outputs']++;
        }
    }

    $stats['valeur_nette'] = $stats['valeur_entries'] - $stats['valeur_outputs'];

    // Traitement selon le format
    if ($format === 'excel') {
        exportToExcelCSV($movements, $stats);
    } elseif ($format === 'pdf') {
        exportToPDF($movements, $stats);
    } elseif ($format === 'print') {
        exportToPrint($movements, $stats);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur : " . $e->getMessage();
    error_log("Erreur export: " . $e->getMessage());
}

/**
 * Export Excel en format CSV avec statistiques financières
 */
function exportToExcelCSV($data, $stats)
{
    $filename = "mouvements_stock_financier_" . date('Y-m-d_H-i-s') . ".csv";

    // Headers pour forcer le téléchargement
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Ajouter le BOM UTF-8 pour la compatibilité Excel
    echo "\xEF\xBB\xBF";

    // Ouvrir le flux de sortie
    $output = fopen('php://output', 'w');

    // === EN-TÊTE STATISTIQUES ===
    fputcsv($output, ['RAPPORT FINANCIER - MOUVEMENTS DE STOCK'], ';');
    fputcsv($output, ['Généré le', date('d/m/Y H:i')], ';');
    fputcsv($output, [], ';'); // Ligne vide

    fputcsv($output, ['STATISTIQUES FINANCIÈRES'], ';');
    fputcsv($output, ['Total mouvements', $stats['total_movements']], ';');
    fputcsv($output, ['Valeur des entrées', number_format($stats['valeur_entries'], 0, ',', ' ') . ' FCFA'], ';');
    fputcsv($output, ['Valeur des sorties', number_format($stats['valeur_outputs'], 0, ',', ' ') . ' FCFA'], ';');
    fputcsv($output, ['Bilan net', number_format($stats['valeur_nette'], 0, ',', ' ') . ' FCFA'], ';');
    fputcsv($output, [], ';'); // Ligne vide

    // === EN-TÊTES DES COLONNES ===
    $headers = [
        'ID',
        'Produit',
        'Catégorie',
        'Quantité',
        'Unité',
        'Type de Mouvement',
        'Provenance',
        'Projet',
        'Destination',
        'Demandeur',
        'Date',
        'Prix Unitaire (FCFA)',
        'Valeur Totale (FCFA)',
        'Notes'
    ];

    fputcsv($output, $headers, ';');

    // === DONNÉES ===
    foreach ($data as $row) {
        $type = getMovementTypeDisplay($row['movement_type']);

        $csvRow = [
            $row['id'],
            cleanCSVData($row['product_name']),
            cleanCSVData($row['category_name']),
            $row['quantity'],
            cleanCSVData($row['unit']),
            $type,
            cleanCSVData($row['provenance']),
            cleanCSVData($row['nom_projet']),
            cleanCSVData($row['destination']),
            cleanCSVData($row['demandeur']),
            date('d/m/Y H:i', strtotime($row['date'])),
            number_format(floatval($row['prix_unitaire']), 2, ',', ' '),
            number_format(floatval($row['valeur_totale']), 2, ',', ' '),
            cleanCSVData($row['notes'])
        ];

        fputcsv($output, $csvRow, ';');
    }

    fclose($output);
    exit;
}

/**
 * Export PDF avec statistiques financières
 */
function exportToPDF($data, $stats)
{
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Rapport Financier - Mouvements de Stock - ' . date('d/m/Y H:i') . '</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 10px; 
                margin: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .header h1 {
                margin: 0;
                color: #333;
                font-size: 18px;
            }
            .header .date {
                color: #666;
                font-size: 12px;
                margin-top: 5px;
            }
            .stats-section {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 20px;
            }
            .stats-title {
                font-size: 14px;
                font-weight: bold;
                color: #495057;
                margin-bottom: 10px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .stat-item {
                padding: 8px;
                background-color: white;
                border-radius: 3px;
                border-left: 3px solid #007bff;
            }
            .stat-label {
                font-weight: bold;
                color: #495057;
            }
            .stat-value {
                color: #28a745;
                font-weight: bold;
            }
            .stat-value.negative {
                color: #dc3545;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
                font-size: 9px;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 4px; 
                text-align: left; 
            }
            th { 
                background-color: #f8f9fa; 
                font-weight: bold;
                color: #495057;
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .badge-entry { 
                background-color: #d4edda; 
                color: #155724; 
                padding: 2px 6px; 
                border-radius: 3px; 
                font-size: 8px;
                font-weight: bold;
            }
            .badge-output { 
                background-color: #f8d7da; 
                color: #721c24; 
                padding: 2px 6px; 
                border-radius: 3px; 
                font-size: 8px;
                font-weight: bold;
            }
            .badge-dispatch { 
                background-color: #d1ecf1; 
                color: #0c5460; 
                padding: 2px 6px; 
                border-radius: 3px; 
                font-size: 8px;
                font-weight: bold;
            }
            .badge-return { 
                background-color: #e2e3e5; 
                color: #383d41; 
                padding: 2px 6px; 
                border-radius: 3px; 
                font-size: 8px;
                font-weight: bold;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="header">
            <h1>Rapport Financier - Mouvements de Stock</h1>
            <div class="date">Généré le ' . date('d/m/Y à H:i') . '</div>
        </div>
        
        <div class="stats-section">
            <div class="stats-title">Statistiques Financières</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Total des mouvements:</div>
                    <div class="stat-value">' . number_format($stats['total_movements'], 0, ',', ' ') . '</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Valeur des entrées:</div>
                    <div class="stat-value">' . number_format($stats['valeur_entries'], 0, ',', ' ') . ' FCFA</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Valeur des sorties:</div>
                    <div class="stat-value">' . number_format($stats['valeur_outputs'], 0, ',', ' ') . ' FCFA</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Bilan net:</div>
                    <div class="stat-value ' . ($stats['valeur_nette'] < 0 ? 'negative' : '') . '">' . number_format($stats['valeur_nette'], 0, ',', ' ') . ' FCFA</div>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produit</th>
                    <th>Qté</th>
                    <th>Type</th>
                    <th>Projet</th>
                    <th>Date</th>
                    <th>Prix Unit.</th>
                    <th>Valeur Tot.</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($data as $row) {
        $type = getMovementTypeDisplay($row['movement_type']);
        $typeClass = getMovementTypeBadgeClass($row['movement_type']);

        echo '<tr>
            <td>' . htmlspecialchars($row['id']) . '</td>
            <td>' . htmlspecialchars($row['product_name']) . '</td>
            <td>' . htmlspecialchars($row['quantity'] . ' ' . $row['unit']) . '</td>
            <td><span class="' . $typeClass . '">' . $type . '</span></td>
            <td>' . htmlspecialchars($row['nom_projet']) . '</td>
            <td>' . date('d/m/Y', strtotime($row['date'])) . '</td>
            <td>' . number_format(floatval($row['prix_unitaire']), 0, ',', ' ') . '</td>
            <td><strong>' . number_format(floatval($row['valeur_totale']), 0, ',', ' ') . '</strong></td>
        </tr>';
    }

    echo '</tbody></table>
        
        <div class="footer">
            <p>DYM MANUFACTURE - Système de Gestion de Stock</p>
            <p>Rapport financier confidentiel - Total: ' . number_format($stats['valeur_nette'], 0, ',', ' ') . ' FCFA</p>
        </div>
    </body></html>';

    exit;
}

/**
 * Export pour impression
 */
function exportToPrint($data, $stats)
{
    exportToPDF($data, $stats);
}

/**
 * Nettoyer les données pour CSV
 */
function cleanCSVData($data)
{
    if ($data === null || $data === '') {
        return '';
    }

    // Remplacer les retours à la ligne par des espaces
    $data = str_replace(["\r\n", "\r", "\n"], ' ', $data);

    // Supprimer les caractères de contrôle
    $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);

    return trim($data);
}

/**
 * Obtenir le libellé d'affichage du type de mouvement
 */
function getMovementTypeDisplay($movementType)
{
    switch (strtolower($movementType)) {
        case 'entry':
            return 'Entrée';
        case 'output':
            return 'Sortie';
        case 'dispatch':
            return 'Dispatching';
        case 'return':
            return 'Retour';
        case 'transfer':
            return 'Transfert';
        case 'adjustment':
            return 'Ajustement';
        default:
            return ucfirst($movementType);
    }
}

/**
 * Obtenir la classe CSS pour le badge du type de mouvement
 */
function getMovementTypeBadgeClass($movementType)
{
    switch (strtolower($movementType)) {
        case 'entry':
        case 'dispatch':
            return 'badge-entry';
        case 'output':
            return 'badge-output';
        case 'return':
            return 'badge-return';
        default:
            return 'badge-dispatch';
    }
}
?>
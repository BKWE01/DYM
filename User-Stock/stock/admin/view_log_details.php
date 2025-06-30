<?php
// Vérifier si l'ID du log est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: logs.php');
    exit;
}

$logId = (int)$_GET['id'];

// Connexion à la base de données
include_once dirname(__DIR__) . '/../../database/connection.php';

// Récupérer les détails du log
try {
    $stmt = $pdo->prepare("SELECT * FROM system_logs WHERE id = :id");
    $stmt->execute([':id' => $logId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        // Log non trouvé, rediriger vers la page des logs
        header('Location: logs.php');
        exit;
    }
    
    // Formater les détails JSON si présents
    $details = null;
    if (!empty($log['details'])) {
        $details = json_decode($log['details'], true);
    }
    
    // Si c'est un log de produit, récupérer les détails complets du produit
    $productData = null;
    if ($log['type'] === 'product' && !empty($log['entity_id'])) {
        $productId = $log['entity_id'];
        
        // Récupérer les détails complets du produit
        $productStmt = $pdo->prepare("SELECT p.*, c.libelle as category_name 
                                      FROM products p 
                                      LEFT JOIN categories c ON p.category = c.id 
                                      WHERE p.id = :id");
        $productStmt->execute([':id' => $productId]);
        $productData = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        // Si le produit a été supprimé mais que nous avons ses détails dans le log
        if (!$productData && $details && isset($details['id'])) {
            $productData = $details;
            
            // Essayer de récupérer le nom de la catégorie
            if (isset($details['category'])) {
                $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
                $catStmt->execute([':id' => $details['category']]);
                $categoryData = $catStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($categoryData) {
                    $productData['category_name'] = $categoryData['libelle'];
                }
            }
        }
    }
    
    // Pour les logs d'entrée/sortie de stock, récupérer des informations supplémentaires
    $stockMovementData = null;
    if (($log['action'] === 'stock_entry' || $log['action'] === 'stock_output') && !empty($log['entity_id'])) {
        // Récupérer les mouvements de stock associés
        $stockStmt = $pdo->prepare("SELECT sm.*, p.product_name, p.barcode, p.unit, p.unit_price, p.prix_moyen, p.quantity_reserved
                                    FROM stock_movement sm
                                    JOIN products p ON sm.product_id = p.id
                                    WHERE sm.product_id = :product_id
                                    ORDER BY sm.date DESC
                                    LIMIT 5");
        $stockStmt->execute([':product_id' => $log['entity_id']]);
        $stockMovementData = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Pour les logs de facture, récupérer des détails supplémentaires
    $invoiceData = null;
    if ($log['action'] === 'invoice_upload' && !empty($log['entity_id'])) {
        $invoiceStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id");
        $invoiceStmt->execute([':id' => $log['entity_id']]);
        $invoiceData = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = 'Erreur de base de données: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Log #<?php echo $logId; ?> - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .info-group {
            margin-bottom: 1.5rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #4B5563;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            padding: 0.5rem;
            background-color: #F9FAFB;
            border-radius: 0.375rem;
            border: 1px solid #E5E7EB;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-stock-entry {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-stock-output {
            background-color: #fce4ec;
            color: #e91e63;
        }

        .badge-product {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-user {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-invoice {
            background-color: #f3e5f5;
            color: #8e24aa;
        }

        .badge-error {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .badge-other {
            background-color: #f5f5f5;
            color: #616161;
        }
        
        .detail-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid #3B82F6;
        }
        
        .detail-card h3 {
            margin-top: 0;
            color: #1F2937;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .detail-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .detail-table th {
            text-align: left;
            font-weight: 500;
            color: #6B7280;
            padding: 0.5rem;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .detail-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .detail-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-change-table {
            width: 100%;
        }
        
        .data-change-table td {
            padding: 0.375rem;
        }
        
        .data-change-table .old-value {
            text-decoration: line-through;
            color: #EF4444;
        }
        
        .data-change-table .new-value {
            color: #10B981;
            font-weight: 500;
        }
        
        .history-table {
            width: 100%;
            font-size: 0.875rem;
        }
        
        .history-table th {
            text-align: left;
            padding: 0.375rem;
            color: #6B7280;
            font-weight: 500;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .history-table td {
            padding: 0.375rem;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .history-card {
            border-left-color: #9333EA;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once dirname(__DIR__) . '/sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once dirname(__DIR__) . '/header.php'; ?>

            <main class="p-4 flex-1 overflow-auto">
                <div class="bg-white p-6 rounded-lg shadow mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">
                            Détails du Log #<?php echo $log['id']; ?>
                        </h1>
                        <a href="logs.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded flex items-center">
                            <span class="material-icons mr-1">arrow_back</span>
                            Retour à la liste
                        </a>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p><?php echo $error; ?></p>
                        </div>
                    <?php else: ?>

                    <!-- Informations principales -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <div class="info-group">
                                <div class="info-label">Date et heure</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Utilisateur</div>
                                <div class="info-value">
                                    <?php echo !empty($log['username']) ? htmlspecialchars($log['username']) : 'N/A'; ?>
                                    <?php if (!empty($log['user_id'])): ?>
                                        (ID: <?php echo htmlspecialchars($log['user_id']); ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Action</div>
                                <div class="info-value">
                                    <?php
                                    // Déterminer le type de badge et le libellé de l'action
                                    $badgeClass = 'badge-other';
                                    $actionDisplay = $log['action'];
                                    
                                    if (strpos($log['action'], 'stock_entry') !== false) {
                                        $badgeClass = 'badge-stock-entry';
                                        $actionDisplay = 'Entrée de stock';
                                    } elseif (strpos($log['action'], 'stock_output') !== false) {
                                        $badgeClass = 'badge-stock-output';
                                        $actionDisplay = 'Sortie de stock';
                                    } elseif (strpos($log['action'], 'product_') !== false) {
                                        $badgeClass = 'badge-product';
                                        
                                        if ($log['action'] === 'product_add') {
                                            $actionDisplay = 'Ajout de produit';
                                        } elseif ($log['action'] === 'product_edit') {
                                            $actionDisplay = 'Modification de produit';
                                        } elseif ($log['action'] === 'product_delete') {
                                            $actionDisplay = 'Suppression de produit';
                                        }
                                    } elseif (strpos($log['action'], 'user_') !== false) {
                                        $badgeClass = 'badge-user';
                                        
                                        if ($log['action'] === 'user_login') {
                                            $actionDisplay = 'Connexion';
                                        } elseif ($log['action'] === 'user_logout') {
                                            $actionDisplay = 'Déconnexion';
                                        }
                                    } elseif (strpos($log['action'], 'invoice_') !== false) {
                                        $badgeClass = 'badge-invoice';
                                        $actionDisplay = 'Upload de facture';
                                    } elseif (strpos($log['action'], 'error') !== false) {
                                        $badgeClass = 'badge-error';
                                        $actionDisplay = 'Erreur';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($actionDisplay); ?></span>
                                    <span class="text-gray-500 text-sm">(<?php echo htmlspecialchars($log['action']); ?>)</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="info-group">
                                <div class="info-label">Adresse IP</div>
                                <div class="info-value"><?php echo !empty($log['ip_address']) ? htmlspecialchars($log['ip_address']) : 'N/A'; ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($log['type']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Élément concerné</div>
                                <div class="info-value">
                                    <?php if (!empty($log['entity_name'])): ?>
                                        <?php echo htmlspecialchars($log['entity_name']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($log['entity_id'])): ?>
                                        <span class="text-gray-500 text-sm">(ID: <?php echo htmlspecialchars($log['entity_id']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Détails formatés -->
                    <?php if ($details || $productData || $stockMovementData || $invoiceData): ?>
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold mb-4">Détails de l'opération</h2>
                            
                            <?php if ($log['action'] === 'stock_entry'): ?>
                                <!-- Entrée de stock -->
                                <div class="detail-card">
                                    <h3>Informations sur l'entrée en stock</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Quantité ajoutée</th>
                                            <td><?php echo htmlspecialchars($details['quantity']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Provenance</th>
                                            <td><?php echo htmlspecialchars($details['provenance']); ?></td>
                                        </tr>
                                        <?php if (!empty($details['fournisseur'])): ?>
                                        <tr>
                                            <th>Fournisseur</th>
                                            <td><?php echo htmlspecialchars($details['fournisseur']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                                <?php if ($productData || $stockMovementData): ?>
                                <div class="detail-card">
                                    <h3>État actuel du produit</h3>
                                    <table class="detail-table">
                                        <?php if ($productData): ?>
                                        <tr>
                                            <th>Nom du produit</th>
                                            <td><?php echo htmlspecialchars($productData['product_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Code-barres</th>
                                            <td><?php echo htmlspecialchars($productData['barcode']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité en stock</th>
                                            <td><?php echo htmlspecialchars($productData['quantity']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité réservée</th>
                                            <td><?php echo isset($productData['quantity_reserved']) ? htmlspecialchars($productData['quantity_reserved']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unité</th>
                                            <td><?php echo htmlspecialchars($productData['unit']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Prix unitaire</th>
                                            <td><?php echo htmlspecialchars($productData['unit_price']); ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Prix moyen</th>
                                            <td><?php echo isset($productData['prix_moyen']) ? htmlspecialchars($productData['prix_moyen']) : 'N/A'; ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Catégorie</th>
                                            <td><?php echo isset($productData['category_name']) ? htmlspecialchars($productData['category_name']) : 'N/A'; ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                            <?php elseif ($log['action'] === 'stock_output'): ?>
                                <!-- Sortie de stock -->
                                <div class="detail-card">
                                    <h3>Informations sur la sortie de stock</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Quantité sortie</th>
                                            <td><?php echo htmlspecialchars($details['quantity']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Destination</th>
                                            <td><?php echo htmlspecialchars($details['destination']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Demandeur</th>
                                            <td><?php echo htmlspecialchars($details['demandeur']); ?></td>
                                        </tr>
                                        <?php if (!empty($details['project'])): ?>
                                        <tr>
                                            <th>Projet</th>
                                            <td><?php echo htmlspecialchars($details['project']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                                <?php if ($productData || $stockMovementData): ?>
                                <div class="detail-card">
                                    <h3>État actuel du produit</h3>
                                    <table class="detail-table">
                                        <?php if ($productData): ?>
                                        <tr>
                                            <th>Nom du produit</th>
                                            <td><?php echo htmlspecialchars($productData['product_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Code-barres</th>
                                            <td><?php echo htmlspecialchars($productData['barcode']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité en stock</th>
                                            <td><?php echo htmlspecialchars($productData['quantity']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité réservée</th>
                                            <td><?php echo isset($productData['quantity_reserved']) ? htmlspecialchars($productData['quantity_reserved']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unité</th>
                                            <td><?php echo htmlspecialchars($productData['unit']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Prix unitaire</th>
                                            <td><?php echo htmlspecialchars($productData['unit_price']); ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Prix moyen</th>
                                            <td><?php echo isset($productData['prix_moyen']) ? htmlspecialchars($productData['prix_moyen']) : 'N/A'; ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Catégorie</th>
                                            <td><?php echo isset($productData['category_name']) ? htmlspecialchars($productData['category_name']) : 'N/A'; ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                            <?php elseif ($log['action'] === 'product_add'): ?>
                                <!-- Ajout de produit -->
                                <div class="detail-card">
                                    <h3>Informations sur l'ajout de produit</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Code-barres</th>
                                            <td><?php echo isset($details['barcode']) ? htmlspecialchars($details['barcode']) : (isset($productData['barcode']) ? htmlspecialchars($productData['barcode']) : 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Nom du produit</th>
                                            <td><?php echo isset($details['product_name']) ? htmlspecialchars($details['product_name']) : (isset($productData['product_name']) ? htmlspecialchars($productData['product_name']) : 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité initiale</th>
                                            <td><?php echo isset($details['quantity']) ? htmlspecialchars($details['quantity']) : (isset($productData['quantity']) ? htmlspecialchars($productData['quantity']) : '0'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité réservée</th>
                                            <td><?php echo isset($details['quantity_reserved']) ? htmlspecialchars($details['quantity_reserved']) : (isset($productData['quantity_reserved']) ? htmlspecialchars($productData['quantity_reserved']) : 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unité</th>
                                            <td><?php echo isset($details['unit']) ? htmlspecialchars($details['unit']) : (isset($productData['unit']) ? htmlspecialchars($productData['unit']) : 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Prix unitaire</th>
                                            <td><?php echo isset($details['unit_price']) ? htmlspecialchars($details['unit_price']) : (isset($productData['unit_price']) ? htmlspecialchars($productData['unit_price']) : '0.00'); ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Prix moyen</th>
                                            <td><?php echo isset($details['prix_moyen']) ? htmlspecialchars($details['prix_moyen']) : (isset($productData['prix_moyen']) ? htmlspecialchars($productData['prix_moyen']) : 'N/A'); ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Catégorie</th>
                                            <td>
                                                <?php 
                                                if (isset($details['category_name'])) {
                                                    echo htmlspecialchars($details['category_name']);
                                                } elseif (isset($productData['category_name'])) {
                                                    echo htmlspecialchars($productData['category_name']);
                                                } elseif (isset($details['category'])) {
                                                    // Si on a l'ID de la catégorie, essayer de récupérer son nom
                                                    $catId = $details['category'];
                                                    try {
                                                        $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
                                                        $catStmt->execute([':id' => $catId]);
                                                        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($cat) {
                                                            echo htmlspecialchars($cat['libelle']);
                                                        } else {
                                                            echo 'ID: ' . htmlspecialchars($catId);
                                                        }
                                                    } catch (PDOException $e) {
                                                        echo 'ID: ' . htmlspecialchars($catId);
                                                    }
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                            <?php elseif ($log['action'] === 'product_edit'): ?>
                                <!-- Modification de produit -->
                                <div class="detail-card">
                                    <h3>Modifications apportées au produit</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Champ</th>
                                            <th>Ancienne valeur</th>
                                            <th>Nouvelle valeur</th>
                                        </tr>
                                        <?php 
                                        if (isset($details['old']) && isset($details['new'])) {
                                            $fieldsToCheck = [
                                                'product_name' => 'Nom du produit',
                                                'barcode' => 'Code-barres',
                                                'quantity' => 'Quantité',
                                                'quantity_reserved' => 'Quantité réservée',
                                                'unit' => 'Unité',
                                                'unit_price' => 'Prix unitaire',
                                                'prix_moyen' => 'Prix moyen',
                                                'category' => 'Catégorie'
                                            ];
                                            
                                            foreach ($fieldsToCheck as $field => $label) {
                                                $oldValue = isset($details['old'][$field]) ? $details['old'][$field] : null;
                                                $newValue = isset($details['new'][$field]) ? $details['new'][$field] : null;
                                                
                                                // Traitement spécial pour la catégorie (convertir l'ID en nom)
                                                if ($field === 'category' && $oldValue !== $newValue) {
                                                    // Récupérer les noms des catégories
                                                    if ($oldValue) {
                                                        try {
                                                            $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
                                                            $catStmt->execute([':id' => $oldValue]);
                                                            $oldCat = $catStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($oldCat) {
                                                                $oldValue = $oldCat['libelle'] . ' (ID: ' . $oldValue . ')';
                                                            }
                                                        } catch (PDOException $e) {
                                                            // Laisser la valeur telle quelle
                                                        }
                                                    }
                                                    
                                                    if ($newValue) {
                                                        try {
                                                            $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
                                                            $catStmt->execute([':id' => $newValue]);
                                                            $newCat = $catStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($newCat) {
                                                                $newValue = $newCat['libelle'] . ' (ID: ' . $newValue . ')';
                                                            }
                                                        } catch (PDOException $e) {
                                                            // Laisser la valeur telle quelle
                                                        }
                                                    }
                                                }
                                                
                                                // Afficher seulement les champs qui ont changé
                                                if ($oldValue != $newValue && ($oldValue !== null || $newValue !== null)) {
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($label) . '</td>';
                                                    echo '<td class="old-value">' . htmlspecialchars($oldValue ?? 'N/A') . '</td>';
                                                    echo '<td class="new-value">' . htmlspecialchars($newValue ?? 'N/A') . '</td>';
                                                    echo '</tr>';
                                                }
                                            }
                                        }
                                        ?>
                                    </table>
                                </div>
                                
                                <!-- Afficher l'état actuel du produit s'il existe encore -->
                                <?php if ($productData): ?>
                                <div class="detail-card">
                                    <h3>État actuel du produit</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Nom du produit</th>
                                            <td><?php echo htmlspecialchars($productData['product_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Code-barres</th>
                                            <td><?php echo htmlspecialchars($productData['barcode']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité en stock</th>
                                            <td><?php echo htmlspecialchars($productData['quantity']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité réservée</th>
                                            <td><?php echo isset($productData['quantity_reserved']) ? htmlspecialchars($productData['quantity_reserved']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unité</th>
                                            <td><?php echo htmlspecialchars($productData['unit']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Prix unitaire</th>
                                            <td><?php echo htmlspecialchars($productData['unit_price']); ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Prix moyen</th>
                                            <td><?php echo isset($productData['prix_moyen']) ? htmlspecialchars($productData['prix_moyen']) : 'N/A'; ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Catégorie</th>
                                            <td><?php echo isset($productData['category_name']) ? htmlspecialchars($productData['category_name']) : 'N/A'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                            <?php elseif ($log['action'] === 'product_delete'): ?>
                                <!-- Suppression de produit -->
                                <div class="detail-card">
                                    <h3>Informations sur le produit supprimé</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Code-barres</th>
                                            <td><?php echo isset($details['barcode']) ? htmlspecialchars($details['barcode']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Nom du produit</th>
                                            <td><?php echo isset($details['product_name']) ? htmlspecialchars($details['product_name']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité en stock</th>
                                            <td><?php echo isset($details['quantity']) ? htmlspecialchars($details['quantity']) : '0'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Quantité réservée</th>
                                            <td><?php echo isset($details['quantity_reserved']) ? htmlspecialchars($details['quantity_reserved']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Unité</th>
                                            <td><?php echo isset($details['unit']) ? htmlspecialchars($details['unit']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Prix unitaire</th>
                                            <td><?php echo isset($details['unit_price']) ? htmlspecialchars($details['unit_price']) : '0.00'; ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Prix moyen</th>
                                            <td><?php echo isset($details['prix_moyen']) ? htmlspecialchars($details['prix_moyen']) : 'N/A'; ?> FCFA</td>
                                        </tr>
                                        <tr>
                                            <th>Catégorie</th>
                                            <td>
                                                <?php 
                                                if (isset($details['category_name'])) {
                                                    echo htmlspecialchars($details['category_name']);
                                                } elseif (isset($details['category'])) {
                                                    // Si on a l'ID de la catégorie, essayer de récupérer son nom
                                                    $catId = $details['category'];
                                                    try {
                                                        $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
                                                        $catStmt->execute([':id' => $catId]);
                                                        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($cat) {
                                                            echo htmlspecialchars($cat['libelle']);
                                                        } else {
                                                            echo 'ID: ' . htmlspecialchars($catId);
                                                        }
                                                    } catch (PDOException $e) {
                                                        echo 'ID: ' . htmlspecialchars($catId);
                                                    }
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                            <?php elseif ($log['action'] === 'invoice_upload'): ?>
                                <!-- Upload de facture -->
                                <div class="detail-card">
                                    <h3>Informations sur la facture</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Nom du fichier</th>
                                            <td><?php echo isset($details['original_filename']) ? htmlspecialchars($details['original_filename']) : (isset($invoiceData['original_filename']) ? htmlspecialchars($invoiceData['original_filename']) : 'N/A'); ?></td>
                                        </tr>
                                        <?php if (isset($details['file_path']) || isset($invoiceData['file_path'])): ?>
                                        <tr>
                                            <th>Chemin du fichier</th>
                                            <td><?php echo isset($details['file_path']) ? htmlspecialchars($details['file_path']) : htmlspecialchars($invoiceData['file_path']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($details['file_type']) || isset($invoiceData['file_type'])): ?>
                                        <tr>
                                            <th>Type de fichier</th>
                                            <td><?php echo isset($details['file_type']) ? htmlspecialchars($details['file_type']) : htmlspecialchars($invoiceData['file_type']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($details['file_size']) || isset($invoiceData['file_size'])): ?>
                                        <tr>
                                            <th>Taille</th>
                                            <td>
                                                <?php 
                                                $size = isset($details['file_size']) ? $details['file_size'] : $invoiceData['file_size'];
                                                echo htmlspecialchars(number_format($size / 1024, 2)); ?> KB
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($details['supplier']) && !empty($details['supplier']) || isset($invoiceData['supplier']) && !empty($invoiceData['supplier'])): ?>
                                        <tr>
                                            <th>Fournisseur</th>
                                            <td><?php echo isset($details['supplier']) ? htmlspecialchars($details['supplier']) : htmlspecialchars($invoiceData['supplier']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($invoiceData['invoice_number'])): ?>
                                        <tr>
                                            <th>Numéro de facture</th>
                                            <td><?php echo htmlspecialchars($invoiceData['invoice_number']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($invoiceData['entry_date'])): ?>
                                        <tr>
                                            <th>Date d'entrée</th>
                                            <td><?php echo date('d/m/Y', strtotime($invoiceData['entry_date'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                                <?php if (isset($invoiceData['file_path'])): ?>
                                <div class="detail-card">
                                    <h3>Aperçu du document</h3>
                                    <?php
                                    // Déterminer l'extension du fichier
                                    $filePath = $invoiceData['file_path'];
                                    $fileExt = pathinfo($filePath, PATHINFO_EXTENSION);
                                    
                                    if (in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif'])) {
                                        // Afficher l'image
                                        echo '<div class="mt-4 flex justify-center">';
                                        echo '<img src="' . htmlspecialchars($filePath) . '" alt="Facture" class="max-w-lg h-auto">';
                                        echo '</div>';
                                    } elseif (strtolower($fileExt) === 'pdf') {
                                        // Lien pour ouvrir le PDF
                                        echo '<div class="mt-4 flex justify-center">';
                                        echo '<a href="' . htmlspecialchars($filePath) . '" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded flex items-center">';
                                        echo '<span class="material-icons mr-2">description</span>';
                                        echo 'Ouvrir le document PDF';
                                        echo '</a>';
                                        echo '</div>';
                                    } else {
                                        // Autre type de document
                                        echo '<div class="mt-4 flex justify-center">';
                                        echo '<a href="' . htmlspecialchars($filePath) . '" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded flex items-center">';
                                        echo '<span class="material-icons mr-2">file_download</span>';
                                        echo 'Télécharger le document';
                                        echo '</a>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                            <?php elseif ($log['action'] === 'user_login' || $log['action'] === 'user_logout'): ?>
                                <!-- Connexion/Déconnexion -->
                                <div class="detail-card">
                                    <h3>Informations sur la session</h3>
                                    <table class="detail-table">
                                        <tr>
                                            <th>Adresse IP</th>
                                            <td><?php echo isset($details['ip_address']) ? htmlspecialchars($details['ip_address']) : $log['ip_address']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Date et heure</th>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        </tr>
                                        <?php if ($log['action'] === 'user_login'): ?>
                                        <tr>
                                            <th>Type de connexion</th>
                                            <td>Connexion au système</td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <th>Type de déconnexion</th>
                                            <td>Déconnexion du système</td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                            <?php else: ?>
                                <!-- Autres types d'actions - affichage générique -->
                                <div class="detail-card">
                                    <h3>Détails de l'opération</h3>
                                    <?php if (is_array($details)): ?>
                                        <table class="detail-table">
                                            <?php foreach ($details as $key => $value): ?>
                                                <tr>
                                                    <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                                    <td>
                                                        <?php
                                                        if (is_array($value)) {
                                                            echo '<pre class="text-sm bg-gray-50 p-2 rounded">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                                                        } else {
                                                            echo htmlspecialchars($value);
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php else: ?>
                                        <pre class="text-sm bg-gray-50 p-4 rounded-md overflow-x-auto">
                                            <?php echo htmlspecialchars(is_string($details) ? $details : json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                                        </pre>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Historique des mouvements de stock récents -->
                            <?php if ($stockMovementData && count($stockMovementData) > 0): ?>
                            <div class="detail-card history-card mt-6">
                                <h3>Historique des mouvements récents</h3>
                                <table class="history-table">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Quantité</th>
                                        <th>Provenance / Destination</th>
                                    </tr>
                                    <?php foreach ($stockMovementData as $movement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['date'])); ?></td>
                                        <td>
                                            <?php if ($movement['movement_type'] === 'entry'): ?>
                                                <span class="badge badge-stock-entry">Entrée</span>
                                            <?php elseif ($movement['movement_type'] === 'output'): ?>
                                                <span class="badge badge-stock-output">Sortie</span>
                                            <?php else: ?>
                                                <span class="badge badge-other"><?php echo htmlspecialchars($movement['movement_type']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                        <td>
                                            <?php
                                            if ($movement['movement_type'] === 'entry') {
                                                echo htmlspecialchars($movement['provenance'] ?: 'N/A');
                                            } else {
                                                echo htmlspecialchars($movement['destination'] ?: 'N/A');
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="mt-8 flex justify-end">
                        <a href="logs.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded mr-2">
                            Retour à la liste des logs
                        </a>
                    </div>
                    
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
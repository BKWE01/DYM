<?php
// diagnostic_stock_general.php
header('Content-Type: text/html');
include_once '../../../database/connection.php';

echo "<h1>Diagnostic des entrées dans le stock général</h1>";

try {
    // 1. Vérifier dans les logs s'il y a des erreurs concernant le stock général
    echo "<h2>Vérification des logs d'erreurs</h2>";
    if (file_exists('dispatching_log.txt')) {
        $logs = file_get_contents('dispatching_log.txt');
        $stockGeneralErrors = [];
        $lines = explode("\n", $logs);
        
        foreach ($lines as $line) {
            if (strpos($line, "Reste de quantité après dispatching") !== false || 
                strpos($line, "ERREUR lors de la création du mouvement pour le reste") !== false) {
                $stockGeneralErrors[] = $line;
            }
        }
        
        if (count($stockGeneralErrors) > 0) {
            echo "<p>Erreurs trouvées dans les logs (" . count($stockGeneralErrors) . ") :</p>";
            echo "<ul>";
            foreach ($stockGeneralErrors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Aucune erreur trouvée dans les logs concernant le stock général.</p>";
        }
    } else {
        echo "<p>Fichier de log non trouvé.</p>";
    }
    
    // 2. Vérifier dans la base de données si des entrées de stock général existent
    echo "<h2>Vérification des entrées de stock général dans la base de données</h2>";
    $query = "SELECT COUNT(*) as count FROM stock_movement WHERE nom_projet = 'Stock général' OR destination = 'Stock général'";
    $stmt = $pdo->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Nombre d'entrées de stock général trouvées : " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        $detailQuery = "SELECT sm.id, p.product_name, sm.quantity, sm.provenance, sm.date 
                        FROM stock_movement sm
                        JOIN products p ON sm.product_id = p.id
                        WHERE sm.nom_projet = 'Stock général' OR sm.destination = 'Stock général'
                        ORDER BY sm.date DESC LIMIT 10";
        $detailStmt = $pdo->query($detailQuery);
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>10 dernières entrées :</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Produit</th><th>Quantité</th><th>Provenance</th><th>Date</th></tr>";
        foreach ($details as $detail) {
            echo "<tr>";
            echo "<td>" . $detail['id'] . "</td>";
            echo "<td>" . htmlspecialchars($detail['product_name']) . "</td>";
            echo "<td>" . $detail['quantity'] . "</td>";
            echo "<td>" . htmlspecialchars($detail['provenance']) . "</td>";
            echo "<td>" . $detail['date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Vérifier s'il y a des entrées où la quantité pourrait avoir un reste
    echo "<h2>Analyse des entrées récentes avec potential de reste</h2>";
    
    $recentQuery = "SELECT sm.id, sm.product_id, p.product_name, sm.quantity as total_quantity, 
                           sm.provenance, sm.date, sm.fournisseur, UNIX_TIMESTAMP(sm.date) as timestamp
                    FROM stock_movement sm
                    JOIN products p ON sm.product_id = p.id
                    WHERE sm.movement_type = 'entry' 
                    AND sm.nom_projet != 'Stock général'
                    AND sm.destination != 'Stock général'
                    ORDER BY sm.date DESC LIMIT 20";
    
    $recentStmt = $pdo->query($recentQuery);
    $recentEntries = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Analyse des 20 dernières entrées principales :</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Produit</th><th>Quantité totale</th><th>Dispatché</th><th>Reste</th><th>Stock général créé?</th><th>Action</th></tr>";
    
    foreach ($recentEntries as $entry) {
        // Vérifier combien a été dispatché depuis cette entrée
        $dispatchQuery = "SELECT SUM(allocated) as total_dispatched 
                          FROM dispatch_details 
                          WHERE movement_id = :movement_id";
        $dispatchStmt = $pdo->prepare($dispatchQuery);
        $dispatchStmt->execute([':movement_id' => $entry['id']]);
        $dispatchResult = $dispatchStmt->fetch(PDO::FETCH_ASSOC);
        
        $dispatchedQuantity = $dispatchResult['total_dispatched'] ?: 0;
        $remainingQuantity = $entry['total_quantity'] - $dispatchedQuantity;
        
        // Vérifier si une entrée correspondante existe dans le stock général
        $stockGeneralQuery = "SELECT COUNT(*) as count, id 
                             FROM stock_movement 
                             WHERE product_id = :product_id 
                             AND quantity = :quantity
                             AND (nom_projet = 'Stock général' OR destination = 'Stock général')
                             AND provenance = :provenance
                             AND ABS(TIMESTAMPDIFF(SECOND, date, :entry_date)) < 300"; // Dans les 5 minutes
        
        $stockGeneralStmt = $pdo->prepare($stockGeneralQuery);
        $stockGeneralStmt->execute([
            ':product_id' => $entry['product_id'],
            ':quantity' => $remainingQuantity,
            ':provenance' => $entry['provenance'],
            ':entry_date' => $entry['date']
        ]);
        
        $stockGeneralResult = $stockGeneralStmt->fetch(PDO::FETCH_ASSOC);
        $stockGeneralExists = $stockGeneralResult['count'] > 0;
        $stockGeneralId = $stockGeneralResult['id'] ?? null;
        
        echo "<tr>";
        echo "<td>" . $entry['id'] . "</td>";
        echo "<td>" . htmlspecialchars($entry['product_name']) . "</td>";
        echo "<td>" . $entry['total_quantity'] . "</td>";
        echo "<td>" . $dispatchedQuantity . "</td>";
        echo "<td>" . $remainingQuantity . "</td>";
        
        if ($stockGeneralExists) {
            echo "<td>Oui (ID: " . $stockGeneralId . ")</td>";
            echo "<td>-</td>";
        } elseif ($remainingQuantity > 0) {
            echo "<td><span style='color:red'>Non</span></td>";
            echo "<td><button onclick=\"fixMissingStockGeneral(" . $entry['id'] . "," . $entry['product_id'] . "," . $remainingQuantity . ",'" . addslashes($entry['provenance']) . "','" . addslashes($entry['fournisseur']) . "','" . $entry['timestamp'] . "')\" style='background-color: #4CAF50; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Créer entrée manquante</button></td>";
        } else {
            echo "<td>N/A</td>";
            echo "<td>-</td>";
        }
        
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<script>
        function fixMissingStockGeneral(movementId, productId, quantity, provenance, fournisseur, timestamp) {
            if (confirm('Voulez-vous créer une entrée de stock général manquante pour l\\'ID de mouvement ' + movementId + ' ?')) {
                fetch('fix_missing_stock_general.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        source_movement_id: movementId,
                        product_id: productId,
                        quantity: quantity,
                        provenance: provenance,
                        fournisseur: fournisseur,
                        timestamp: timestamp
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Entrée créée avec succès! ID: ' + data.id);
                        window.location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur: ' + error);
                });
            }
        }
    </script>";
    
    echo "<h2>Conclusion</h2>";
    echo "<p>Ce diagnostic vous permet de voir si les entrées de stock général sont correctement créées. Si vous voyez des lignes avec un reste positif mais sans entrée dans le stock général, cela indique un problème avec le code qui crée ces entrées.</p>";
    
} catch (Exception $e) {
    echo "<div style='color:red'>";
    echo "<h2>Erreur</h2>";
    echo "<p>Une erreur est survenue lors du diagnostic : " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
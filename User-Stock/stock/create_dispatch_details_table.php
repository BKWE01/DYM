<?php
// Connexion à la base de données
include_once '../../database/connection.php'; 

try {
    // Vérifier si la table existe déjà
    $checkTable = $pdo->query("SHOW TABLES LIKE 'dispatch_details'");
    if ($checkTable->rowCount() > 0) {
        echo "La table dispatch_details existe déjà.<br>";
    } else {
        // Créer la table
        $sql = "CREATE TABLE dispatch_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            movement_id INT NOT NULL,
            order_id INT,
            product_id INT NOT NULL,
            allocated FLOAT NOT NULL,
            remaining FLOAT,
            status VARCHAR(50) NOT NULL,
            project VARCHAR(100),
            client VARCHAR(100),
            dispatch_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            
            INDEX (movement_id),
            INDEX (order_id),
            INDEX (product_id),
            INDEX (status)
        )";

        $pdo->exec($sql);
        echo "Table dispatch_details créée avec succès.<br>";
    }

    // Vérifier si nous avons des données dans les logs à importer
    $logFile = 'dispatching_log.txt';
    if (file_exists($logFile)) {
        echo "Fichier de log trouvé, début de l'importation des données existantes...<br>";

        // Lire le contenu du fichier de log
        $logContent = file_get_contents($logFile);
        $lines = explode(PHP_EOL, $logContent);

        $currentMovement = null;
        $currentProduct = null;
        $dispatchingData = [];

        foreach ($lines as $line) {
            // Extraire l'horodatage et le message
            if (preg_match('/\[(.*?)\] (.*)/', $line, $matches)) {
                $timestamp = $matches[1];
                $message = $matches[2];

                // Détecter un début de mouvement
                if (preg_match('/Entrée de produit: (.*?) \(ID: (\d+), Code-barres: (.*?)\), Quantité: (\d+)/', $message, $matches)) {
                    $productName = $matches[1];
                    $productId = $matches[2];
                    $barcode = $matches[3];
                    $quantity = $matches[4];

                    // Récupérer l'ID du mouvement de stock correspondant
                    $stmt = $pdo->prepare("
                        SELECT id FROM stock_movement 
                        WHERE product_id = :product_id 
                        AND quantity = :quantity 
                        AND movement_type = 'entry'
                        ORDER BY date DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':product_id' => $productId,
                        ':quantity' => $quantity
                    ]);
                    $movementData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($movementData) {
                        $currentMovement = $movementData['id'];
                        $currentProduct = [
                            'id' => $productId,
                            'name' => $productName,
                            'quantity' => $quantity
                        ];
                    }
                }

                // Détecter une commande complétée
                else if ($currentMovement && preg_match('/Commande #(\d+) COMPLETÉE et marquée comme \'reçu\'/', $message, $matches)) {
                    $orderId = $matches[1];

                    // Récupérer les infos supplémentaires sur la commande
                    $orderStmt = $pdo->prepare("
                        SELECT am.*, ip.code_projet, ip.nom_client
                        FROM achats_materiaux am
                        JOIN identification_projet ip ON am.expression_id = ip.idExpression
                        WHERE am.id = :order_id
                    ");
                    $orderStmt->execute([':order_id' => $orderId]);
                    $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                    if ($orderInfo) {
                        // Estimer la quantité allouée (généralement la quantité totale)
                        $allocated = $orderInfo['quantity'];

                        $dispatchingData[] = [
                            'movement_id' => $currentMovement,
                            'order_id' => $orderId,
                            'product_id' => $currentProduct['id'],
                            'allocated' => $allocated,
                            'remaining' => 0,
                            'status' => 'completed',
                            'project' => $orderInfo['code_projet'],
                            'client' => $orderInfo['nom_client'],
                            'dispatch_date' => $timestamp,
                            'notes' => "Importé depuis les logs"
                        ];
                    }
                }

                // Détecter une commande partiellement satisfaite
                else if ($currentMovement && preg_match('/Commande #(\d+) PARTIELLEMENT satisfaite, nouvelle quantité restante: (\d+(\.\d+)?)/', $message, $matches)) {
                    $orderId = $matches[1];
                    $remaining = $matches[2];

                    // Récupérer les infos supplémentaires sur la commande
                    $orderStmt = $pdo->prepare("
                        SELECT am.*, ip.code_projet, ip.nom_client
                        FROM achats_materiaux am
                        JOIN identification_projet ip ON am.expression_id = ip.idExpression
                        WHERE am.id = :order_id
                    ");
                    $orderStmt->execute([':order_id' => $orderId]);
                    $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);

                    if ($orderInfo) {
                        // Estimer la quantité allouée
                        $originalQuantity = $orderInfo['quantity'] + $remaining; // Estimation
                        $allocated = $originalQuantity - $remaining;

                        $dispatchingData[] = [
                            'movement_id' => $currentMovement,
                            'order_id' => $orderId,
                            'product_id' => $currentProduct['id'],
                            'allocated' => $allocated,
                            'remaining' => $remaining,
                            'status' => 'partial',
                            'project' => $orderInfo['code_projet'],
                            'client' => $orderInfo['nom_client'],
                            'dispatch_date' => $timestamp,
                            'notes' => "Importé depuis les logs"
                        ];
                    }
                }

                // Détecter la fin d'un mouvement
                else if (strpos($message, "Transaction VALIDÉE avec succès") !== false) {
                    $currentMovement = null;
                    $currentProduct = null;
                }
            }
        }

        // Insérer les données dans la table
        if (!empty($dispatchingData)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO dispatch_details 
                (movement_id, order_id, product_id, allocated, remaining, status, project, client, dispatch_date, notes)
                VALUES 
                (:movement_id, :order_id, :product_id, :allocated, :remaining, :status, :project, :client, :dispatch_date, :notes)
            ");

            $insertedCount = 0;
            foreach ($dispatchingData as $data) {
                try {
                    $insertStmt->execute($data);
                    $insertedCount++;
                } catch (PDOException $e) {
                    echo "Erreur lors de l'insertion: " . $e->getMessage() . "<br>";
                }
            }

            echo "$insertedCount enregistrements de dispatching importés avec succès.<br>";
        } else {
            echo "Aucune donnée de dispatching trouvée dans les logs.<br>";
        }
    } else {
        echo "Fichier de log non trouvé. Aucune donnée importée.<br>";
    }

    echo "Opération terminée.";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
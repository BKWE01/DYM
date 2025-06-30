<?php
// Script pour vérifier la relation entre besoins et demandeur
// À exécuter pour diagnostiquer pourquoi les données ne s'affichent pas

header('Content-Type: text/html; charset=utf-8');

// Connexion à la base de données
include_once '../../database/connection.php';

echo "<h1>Diagnostic des relations entre tables</h1>";

try {
    // 1. Vérifier les besoins
    echo "<h2>Table besoins</h2>";
    $stmt_besoins = $pdo->query("SELECT COUNT(*) as total FROM besoins");
    $total_besoins = $stmt_besoins->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Nombre total d'enregistrements dans 'besoins': <strong>$total_besoins</strong></p>";

    // 2. Identifier les idBesoin uniques
    $stmt_ids = $pdo->query("SELECT DISTINCT idBesoin FROM besoins");
    $ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Nombre d'identifiants idBesoin uniques: <strong>" . count($ids) . "</strong></p>";

    echo "<h3>Liste des idBesoin:</h3>";
    echo "<ul>";
    foreach ($ids as $id) {
        echo "<li>$id</li>";
    }
    echo "</ul>";

    // 3. Vérifier la table demandeur
    echo "<h2>Table demandeur</h2>";
    $stmt_demandeur = $pdo->query("SELECT COUNT(*) as total FROM demandeur");
    $total_demandeur = $stmt_demandeur->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Nombre total d'enregistrements dans 'demandeur': <strong>$total_demandeur</strong></p>";

    // 4. Vérifier la correspondance entre les tables
    echo "<h2>Correspondance entre tables</h2>";
    $matching_count = 0;
    $missing_list = [];
    
    foreach ($ids as $id) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as found FROM demandeur WHERE idBesoin = :id");
        $stmt_check->execute([':id' => $id]);
        $found = $stmt_check->fetch(PDO::FETCH_ASSOC)['found'];
        
        if ($found > 0) {
            $matching_count++;
        } else {
            $missing_list[] = $id;
        }
    }
    
    echo "<p>Identifiants avec correspondance dans 'demandeur': <strong>$matching_count</strong></p>";
    
    if (count($missing_list) > 0) {
        echo "<h3>Identifiants sans correspondance dans 'demandeur':</h3>";
        echo "<ul>";
        foreach ($missing_list as $id) {
            echo "<li>$id</li>";
        }
        echo "</ul>";
    }

    // 5. Tester la requête JOIN utilisée dans l'API
    echo "<h2>Test de la requête JOIN</h2>";
    
    $stmt_join = $pdo->query("
        SELECT DISTINCT b.idBesoin, b.created_at, d.service_demandeur, d.nom_prenoms
        FROM besoins b
        JOIN demandeur d ON b.idBesoin = d.idBesoin
        GROUP BY b.idBesoin
        ORDER BY b.created_at DESC
    ");
    
    $join_results = $stmt_join->fetchAll(PDO::FETCH_ASSOC);
    $join_count = count($join_results);
    
    echo "<p>Nombre de résultats avec JOIN: <strong>$join_count</strong></p>";
    
    if ($join_count > 0) {
        echo "<h3>Premier résultat de la requête JOIN:</h3>";
        echo "<pre>";
        print_r($join_results[0]);
        echo "</pre>";
        
        echo "<h3>Liste des résultats:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>idBesoin</th><th>created_at</th><th>service_demandeur</th><th>nom_prenoms</th></tr>";
        
        foreach ($join_results as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['idBesoin']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($row['service_demandeur'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['nom_prenoms'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p><strong>Aucun résultat trouvé avec la requête JOIN!</strong></p>";
    }

    // 6. Vérifier la structure de la table demandeur
    echo "<h2>Structure de la table demandeur</h2>";
    
    $stmt_columns = $pdo->query("DESCRIBE demandeur");
    $columns = $stmt_columns->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";

} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>Erreur PDO: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>Erreur: " . $e->getMessage() . "</div>";
}
?>
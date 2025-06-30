<?php
// API pour exporter un appel de fonds en Excel avec style moderne
session_start();

require_once '../../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    header("Location: ../../index.php");
    exit();
}

$code_appel = $_GET['code'] ?? null;

if (!$code_appel) {
    die('Code appel manquant');
}

try {
    // Récupérer les informations de l'appel
    $appel_query = "
        SELECT 
            af.*,
            u.name as demandeur_nom,
            u.email as demandeur_email,
            vf.name as validateur_nom
        FROM appels_fonds af
        LEFT JOIN users_exp u ON af.user_id = u.id
        LEFT JOIN users_exp vf ON af.validé_par = vf.id
        WHERE af.code_appel = ?
    ";
    $appel_stmt = $pdo->prepare($appel_query);
    $appel_stmt->execute([$code_appel]);
    $appel = $appel_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appel) {
        die('Appel de fonds non trouvé');
    }
    
    // Récupérer les éléments
    $elements_query = "
        SELECT *, 
            CASE 
                WHEN statut = 'valide' THEN 'Validé'
                WHEN statut = 'rejete' THEN 'Rejeté'
                ELSE 'En attente'
            END as statut_libelle
        FROM appels_fonds_elements 
        WHERE appel_fonds_id = ?
        ORDER BY id
    ";
    $elements_stmt = $pdo->prepare($elements_query);
    $elements_stmt->execute([$appel['id']]);
    $elements = $elements_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les justificatifs
    $justificatifs_query = "
        SELECT * FROM appels_fonds_justificatifs 
        WHERE appel_fonds_id = ?
        ORDER BY uploaded_at
    ";
    $justificatifs_stmt = $pdo->prepare($justificatifs_query);
    $justificatifs_stmt->execute([$appel['id']]);
    $justificatifs = $justificatifs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les totaux
    $montant_valide = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'valide' ? $el['montant_total'] : 0;
    }, $elements)));
    
    $montant_rejete = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'rejete' ? $el['montant_total'] : 0;
    }, $elements)));
    
    $montant_attente = array_sum(array_filter(array_map(function($el) {
        return $el['statut'] === 'en_attente' ? $el['montant_total'] : 0;
    }, $elements)));
    
    // Headers pour forcer le téléchargement Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="appel_fonds_' . $code_appel . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Styles CSS pour Excel
    $styles = '
    <style type="text/css">
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .header h2 { margin: 5px 0 0 0; font-size: 18px; font-weight: normal; }
        .info-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .info-title { color: #374151; font-size: 16px; font-weight: bold; margin-bottom: 15px; border-bottom: 2px solid #3b82f6; padding-bottom: 5px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; color: #4b5563; width: 200px; padding: 8px 15px 8px 0; }
        .info-value { display: table-cell; color: #1f2937; padding: 8px 0; }
        .totals-section { background: #eff6ff; border: 2px solid #3b82f6; border-radius: 10px; padding: 20px; margin: 20px 0; }
        .totals-grid { display: table; width: 100%; }
        .total-item { display: table-cell; text-align: center; padding: 10px; }
        .total-value { font-size: 20px; font-weight: bold; display: block; }
        .total-label { font-size: 12px; color: #6b7280; margin-top: 5px; }
        .total-general { color: #1f2937; }
        .total-valid { color: #10b981; }
        .total-rejected { color: #ef4444; }
        .total-pending { color: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th { background: #374151; color: white; padding: 15px 10px; text-align: left; font-weight: bold; font-size: 14px; }
        td { padding: 12px 10px; border-bottom: 1px solid #f3f4f6; }
        tr:nth-child(even) { background: #f9fafb; }
        tr:hover { background: #f3f4f6; }
        .status-en_attente { background: #fef3c7; color: #92400e; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-valide { background: #d1fae5; color: #065f46; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-rejete { background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .text-right { text-align: right; }
        .designation-cell { max-width: 300px; word-wrap: break-word; }
        .file-list { background: #f3f4f6; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .file-item { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .file-item:last-child { border-bottom: none; }
        .footer { text-align: center; margin-top: 40px; padding: 20px; background: #f9fafb; border-radius: 8px; color: #6b7280; font-size: 12px; }
        .progression-bar { background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progression-fill { background: linear-gradient(90deg, #3b82f6, #1d4ed8); height: 100%; transition: width 0.3s ease; }
    </style>';
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Appel de Fonds - ' . htmlspecialchars($code_appel) . '</title>';
    echo $styles;
    echo '</head>';
    echo '<body>';
    
    // En-tête principal
    echo '<div class="header">';
    echo '<h1>Appel de Fonds</h1>';
    echo '<h2>' . htmlspecialchars($code_appel) . '</h2>';
    echo '</div>';
    
    // Informations générales
    echo '<div class="info-section">';
    echo '<div class="info-title">📋 Informations générales</div>';
    echo '<div class="info-grid">';
    echo '<div class="info-row"><div class="info-label">Demandeur:</div><div class="info-value">' . htmlspecialchars($appel['demandeur_nom']) . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Email:</div><div class="info-value">' . htmlspecialchars($appel['demandeur_email']) . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Entité:</div><div class="info-value">' . htmlspecialchars($appel['entite'] ?: 'Non spécifié') . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Département:</div><div class="info-value">' . htmlspecialchars($appel['departement'] ?: 'Non spécifié') . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Mode de paiement:</div><div class="info-value">' . htmlspecialchars($appel['mode_paiement'] ?: 'Non spécifié') . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Date de création:</div><div class="info-value">' . date('d/m/Y à H:i', strtotime($appel['date_creation'])) . '</div></div>';
    echo '<div class="info-row"><div class="info-label">Statut:</div><div class="info-value"><span class="status-' . $appel['statut'] . '">' . ucfirst(str_replace('_', ' ', $appel['statut'])) . '</span></div></div>';
    
    if ($appel['date_validation']) {
        echo '<div class="info-row"><div class="info-label">Date de validation:</div><div class="info-value">' . date('d/m/Y à H:i', strtotime($appel['date_validation'])) . '</div></div>';
    }
    if ($appel['validateur_nom']) {
        echo '<div class="info-row"><div class="info-label">Validé par:</div><div class="info-value">' . htmlspecialchars($appel['validateur_nom']) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Désignation et justification
    echo '<div class="info-section">';
    echo '<div class="info-title">📝 Description</div>';
    echo '<div class="info-grid">';
    echo '<div class="info-row"><div class="info-label">Désignation:</div><div class="info-value">' . htmlspecialchars($appel['designation']) . '</div></div>';
    if ($appel['justification']) {
        echo '<div class="info-row"><div class="info-label">Justification:</div><div class="info-value">' . htmlspecialchars($appel['justification']) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Résumé financier
    $progression = $appel['montant_total'] > 0 ? ($montant_valide / $appel['montant_total']) * 100 : 0;
    echo '<div class="totals-section">';
    echo '<div class="info-title">💰 Résumé financier</div>';
    echo '<div class="totals-grid">';
    echo '<div class="total-item"><span class="total-value total-general">' . number_format($appel['montant_total'], 0, ',', ' ') . ' FCFA</span><div class="total-label">Montant total</div></div>';
    echo '<div class="total-item"><span class="total-value total-valid">' . number_format($montant_valide, 0, ',', ' ') . ' FCFA</span><div class="total-label">Validé</div></div>';
    echo '<div class="total-item"><span class="total-value total-rejected">' . number_format($montant_rejete, 0, ',', ' ') . ' FCFA</span><div class="total-label">Rejeté</div></div>';
    echo '<div class="total-item"><span class="total-value total-pending">' . number_format($montant_attente, 0, ',', ' ') . ' FCFA</span><div class="total-label">En attente</div></div>';
    echo '</div>';
    echo '<div style="margin-top: 15px;"><strong>Progression: ' . round($progression) . '%</strong></div>';
    echo '<div class="progression-bar"><div class="progression-fill" style="width: ' . $progression . '%"></div></div>';
    echo '</div>';
    
    // Tableau des éléments
    echo '<h3 style="color: #374151; margin-top: 30px;">🔍 Détail des éléments</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Désignation</th>';
    echo '<th>Quantité</th>';
    echo '<th>Unité</th>';
    echo '<th class="text-right">Prix unitaire</th>';
    echo '<th class="text-right">Montant total</th>';
    echo '<th>Statut</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($elements as $element) {
        echo '<tr>';
        echo '<td class="designation-cell">' . htmlspecialchars($element['designation']) . '</td>';
        echo '<td>' . number_format($element['quantite'], 0, ',', ' ') . '</td>';
        echo '<td>' . htmlspecialchars($element['unite'] ?: '-') . '</td>';
        echo '<td class="text-right">' . number_format($element['prix_unitaire'], 0, ',', ' ') . ' FCFA</td>';
        echo '<td class="text-right"><strong>' . number_format($element['montant_total'], 0, ',', ' ') . ' FCFA</strong></td>';
        echo '<td><span class="status-' . $element['statut'] . '">' . $element['statut_libelle'] . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Liste des justificatifs
    if (!empty($justificatifs)) {
        echo '<h3 style="color: #374151; margin-top: 30px;">📎 Justificatifs</h3>';
        echo '<div class="file-list">';
        foreach ($justificatifs as $file) {
            echo '<div class="file-item">';
            echo '<strong>' . htmlspecialchars($file['nom_fichier']) . '</strong>';
            echo ' (' . round($file['taille_fichier'] / 1024) . ' KB)';
            echo ' - Uploadé le ' . date('d/m/Y à H:i', strtotime($file['uploaded_at']));
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Footer
    echo '<div class="footer">';
    echo '📄 Document généré le ' . date('d/m/Y à H:i') . '<br>';
    echo 'Système de gestion des appels de fonds - DYM Fonds';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    
} catch (Exception $e) {
    error_log("Erreur export Excel: " . $e->getMessage());
    die('Erreur lors de la génération du fichier Excel: ' . $e->getMessage());
}
?>
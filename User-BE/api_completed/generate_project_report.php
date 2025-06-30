<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Inclure la bibliothèque TCPDF
require_once('../../tcpdf/tcpdf.php');

// Si la bibliothèque TCPDF n'est pas trouvée, essayer un autre chemin
if (!class_exists('TCPDF')) {
    require_once('../vendor/tcpdf/tcpdf.php');
}

// Si la bibliothèque TCPDF n'est toujours pas trouvée, utiliser une version CDN
if (!class_exists('TCPDF')) {
    die('Erreur: La bibliothèque TCPDF est requise pour générer le rapport PDF.');
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Vérifier si l'ID du projet est fourni
if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
    die('Erreur: ID de projet manquant');
}

$projectId = $_GET['project_id'];

try {
    // Récupérer les détails du projet
    $stmt = $pdo->prepare("
        SELECT p.*, ps.status, ps.completed_at, ps.completed_by, u.name as completed_by_name
        FROM identification_projet p
        JOIN project_status ps ON p.idExpression = ps.idExpression
        LEFT JOIN users_exp u ON ps.completed_by = u.id
        WHERE p.idExpression = :project_id
        AND ps.status = 'completed'
    ");
    $stmt->bindParam(':project_id', $projectId);
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        die('Erreur: Projet non trouvé ou non terminé');
    }

    // Récupérer les produits du projet
    $stmt = $pdo->prepare("
        SELECT e.*, 
            (SELECT COALESCE(SUM(sm.quantity), 0) 
             FROM stock_movement sm 
             WHERE sm.movement_type = 'output' 
             AND sm.nom_projet = :nom_client
             AND sm.product_id = p.id) as quantity_used,
            (SELECT COALESCE(SUM(sm.quantity), 0) 
             FROM stock_movement sm 
             WHERE (sm.movement_type = 'input' OR sm.movement_type = 'adjustment')
             AND sm.provenance LIKE '%Retour%' 
             AND sm.nom_projet = :nom_client
             AND sm.product_id = p.id) as quantity_returned
        FROM expression_dym e
        LEFT JOIN products p ON LOWER(e.designation) = LOWER(p.product_name)
        WHERE e.idExpression = :project_id
        ORDER BY e.designation
    ");
    $stmt->bindParam(':project_id', $projectId);
    $stmt->bindParam(':nom_client', $project['nom_client']);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Créer une instance de TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('DYM BE');
    $pdf->SetAuthor('DYM Manufacture');
    $pdf->SetTitle('Rapport de Projet Terminé - ' . $project['nom_client']);
    $pdf->SetSubject('Rapport de Projet Terminé');
    $pdf->SetKeywords('DYM, Projet, Rapport, Terminé');

    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Définir la police par défaut
    $pdf->SetFont('helvetica', '', 10);

    // Ajouter une page
    $pdf->AddPage();

    // Logo et titre
    $pdf->Image('../../assets/images/logo.png', 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(45, 10);
    $pdf->Cell(150, 10, 'RAPPORT DE PROJET TERMINÉ', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    $pdf->SetXY(45, 20);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(150, 10, 'DYM MANUFACTURE', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    
    // Date de génération
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(160, 10);
    $pdf->Cell(40, 10, 'Date: ' . date('d/m/Y'), 0, false, 'R', 0, '', 0, false, 'T', 'M');

    $pdf->Ln(25);

    // Informations du projet
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(190, 10, 'Informations du projet', 0, 1, 'L');
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);

    // Détails du projet
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'ID Projet:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['idExpression'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Code Projet:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['code_projet'] ?? 'Non spécifié', 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Nom Client:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['nom_client'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Chef de Projet:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['chefprojet'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Situation Géographique:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['sitgeo'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Date de Création:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, date('d/m/Y', strtotime($project['created_at'])), 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Date de Fin:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, date('d/m/Y', strtotime($project['completed_at'])), 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Terminé par:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, $project['completed_by_name'] ?? 'Utilisateur ID: ' . $project['completed_by'], 0, 1);

    $pdf->Ln(5);

    // Description du projet
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 6, 'Description:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(190, 6, $project['description_projet'] ?? 'Aucune description disponible.', 0, 'L', 0, 1);

    $pdf->Ln(5);

    // Produits du projet
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(190, 10, 'Produits utilisés', 0, 1, 'L');
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);

    // Vérifier si une nouvelle page est nécessaire
    if ($pdf->GetY() > 220) {
        $pdf->AddPage();
    }

    // En-têtes du tableau
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(65, 7, 'Désignation', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Unité', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Type', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Qté Initiale', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Qté Utilisée', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Qté Retournée', 1, 1, 'C', 1);

    // Données du tableau
    $pdf->SetFont('helvetica', '', 8);
    foreach ($products as $product) {
        // Vérifier si une nouvelle page est nécessaire
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            
            // Répéter les en-têtes du tableau sur la nouvelle page
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(65, 7, 'Désignation', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Unité', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Type', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Qté Initiale', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Qté Utilisée', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Qté Retournée', 1, 1, 'C', 1);
            $pdf->SetFont('helvetica', '', 8);
        }

        $initialQuantity = number_format((float)$product['quantity'], 2, '.', '');
        $usedQuantity = number_format((float)($product['quantity_used'] ?? 0), 2, '.', '');
        $returnedQuantity = number_format((float)($product['quantity_returned'] ?? 0), 2, '.', '');

        $pdf->Cell(65, 6, $product['designation'], 1, 0, 'L');
        $pdf->Cell(20, 6, $product['unit'] ?? 'unité', 1, 0, 'C');
        $pdf->Cell(25, 6, $product['type'] ?? '-', 1, 0, 'C');
        $pdf->Cell(25, 6, $initialQuantity, 1, 0, 'C');
        $pdf->Cell(25, 6, $usedQuantity, 1, 0, 'C');
        $pdf->Cell(30, 6, $returnedQuantity, 1, 1, 'C');
    }

    // Pied de page
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i'), 0, 1, 'C');
    $pdf->Cell(0, 5, 'DYM MANUFACTURE - Tous droits réservés', 0, 1, 'C');

    // Nom du fichier
    $filename = 'Rapport_Projet_' . preg_replace('/[^a-zA-Z0-9]/', '_', $project['nom_client']) . '_' . date('Y-m-d') . '.pdf';

    // Sortie du PDF
    $pdf->Output($filename, 'I');

} catch (PDOException $e) {
    die('Erreur de base de données: ' . $e->getMessage());
} catch (Exception $e) {
    die('Erreur: ' . $e->getMessage());
}
?>
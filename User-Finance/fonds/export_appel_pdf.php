<?php
// API pour exporter un appel de fonds en PDF - Version mise à jour (style simplifié)
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
    
    // Générer le PDF avec style simplifié
    require_once '../fpdf/fpdf.php';
    
    class SimplePDF extends FPDF {
        function Header() {
            // Logo et titre
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 10, 'APPEL DE FONDS', 0, 1, 'R');
            
            // Ligne bleue
            $this->SetDrawColor(61, 130, 193); // #3D82C1
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'DYM Fonds - Document généré le ' . date('d/m/Y à H:i') . ' - Page ' . $this->PageNo(), 0, 0, 'C');
        }
        
        function SectionTitle($title) {
            $this->Ln(3);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(61, 130, 193); // #3D82C1
            $this->Cell(0, 8, utf8_decode($title), 0, 1);
            $this->SetDrawColor(61, 130, 193); // #3D82C1
            $this->SetLineWidth(0.2);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->SetTextColor(0, 0, 0);
            $this->Ln(5);
        }
        
        function InfoRow($label, $value) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(50, 6, utf8_decode($label), 0, 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, utf8_decode($value), 0, 1);
        }
        
        function SummaryTable($montantTotal, $montantValide, $montantRejete, $montantAttente) {
            $this->SetFillColor(245, 245, 245);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(95, 8, 'Montant total', 1, 0, 'L', true);
            $this->Cell(95, 8, number_format($montantTotal, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);
            
            $this->Cell(95, 8, 'Montant validé', 1, 0, 'L');
            $this->Cell(95, 8, number_format($montantValide, 0, ',', ' ') . ' FCFA', 1, 1, 'R');
            
            $this->Cell(95, 8, 'Montant rejeté', 1, 0, 'L', true);
            $this->Cell(95, 8, number_format($montantRejete, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);
            
            $this->Cell(95, 8, 'Montant en attente', 1, 0, 'L');
            $this->Cell(95, 8, number_format($montantAttente, 0, ',', ' ') . ' FCFA', 1, 1, 'R');
            
            // Progression
            $progression = $montantTotal > 0 ? ($montantValide / $montantTotal) * 100 : 0;
            $this->Cell(95, 8, 'Progression', 1, 0, 'L', true);
            $this->Cell(95, 8, round($progression) . '%', 1, 1, 'R', true);
        }
    }
    
    $pdf = new SimplePDF();
    $pdf->AddPage();
    
    // Référence et date
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(130, 5, 'Référence : ' . $code_appel, 0, 0);
    $pdf->Cell(60, 5, 'Date : ' . date('d/m/Y', strtotime($appel['date_creation'])), 0, 1, 'R');
    
    // Statut en haut à droite
    $pdf->SetFont('Arial', 'B', 12);
    $status_label = ucfirst(str_replace('_', ' ', $appel['statut']));
    $pdf->Cell(0, 10, $status_label, 0, 1, 'R');
    $pdf->Ln(5);
    
    // Informations générales
    $pdf->SectionTitle('Informations générales');
    
    $pdf->InfoRow('Demandeur:', $appel['demandeur_nom']);
    $pdf->InfoRow('Email:', $appel['demandeur_email']);
    $pdf->InfoRow('Entité:', $appel['entite'] ?: 'Non spécifié');
    $pdf->InfoRow('Département:', $appel['departement'] ?: 'Non spécifié');
    $pdf->InfoRow('Mode de paiement:', $appel['mode_paiement'] ?: 'Non spécifié');
    $pdf->InfoRow('Date de création:', date('d/m/Y à H:i', strtotime($appel['date_creation'])));
    
    if ($appel['date_validation']) {
        $pdf->InfoRow('Date de validation:', date('d/m/Y à H:i', strtotime($appel['date_validation'])));
    }
    
    if ($appel['validateur_nom']) {
        $pdf->InfoRow('Validé par:', $appel['validateur_nom']);
    }
    
    $pdf->Ln(5);
    
    // Description
    $pdf->SectionTitle('Description');
    $pdf->InfoRow('Désignation:', $appel['designation']);
    
    if ($appel['justification']) {
        $pdf->InfoRow('Justification:', $appel['justification']);
    }
    
    $pdf->Ln(5);
    
    // Résumé financier
    $pdf->SectionTitle('Résumé financier');
    $pdf->SummaryTable($appel['montant_total'], $montant_valide, $montant_rejete, $montant_attente);
    
    $pdf->Ln(10);
    
    // Tableau des éléments
    $pdf->SectionTitle('Détail des éléments');
    
    // En-têtes du tableau
    $pdf->SetFillColor(61, 130, 193); // #3D82C1
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    
    $colWidths = [80, 20, 20, 30, 30, 20];
    $pdf->Cell($colWidths[0], 8, utf8_decode('Désignation'), 1, 0, 'C', true);
    $pdf->Cell($colWidths[1], 8, utf8_decode('Qté'), 1, 0, 'C', true);
    $pdf->Cell($colWidths[2], 8, utf8_decode('Unité'), 1, 0, 'C', true);
    $pdf->Cell($colWidths[3], 8, 'Prix unitaire', 1, 0, 'C', true);
    $pdf->Cell($colWidths[4], 8, 'Montant', 1, 0, 'C', true);
    $pdf->Cell($colWidths[5], 8, 'Statut', 1, 0, 'C', true);
    $pdf->Ln();
    
    // Données du tableau
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $fillRow = false;
    
    foreach ($elements as $element) {
        $pdf->SetFillColor(245, 245, 245);
        
        // Designations can be long, we need to ensure they fit
        $designation = $element['designation'];
        if (strlen($designation) > 35) {
            $designation = substr($designation, 0, 32) . '...';
        }
        
        $pdf->Cell($colWidths[0], 8, utf8_decode($designation), 1, 0, 'L', $fillRow);
        $pdf->Cell($colWidths[1], 8, number_format($element['quantite'], 2), 1, 0, 'C', $fillRow);
        $pdf->Cell($colWidths[2], 8, utf8_decode($element['unite'] ?: '-'), 1, 0, 'C', $fillRow);
        $pdf->Cell($colWidths[3], 8, number_format($element['prix_unitaire'], 0, ',', ' '), 1, 0, 'R', $fillRow);
        $pdf->Cell($colWidths[4], 8, number_format($element['montant_total'], 0, ',', ' '), 1, 0, 'R', $fillRow);
        $pdf->Cell($colWidths[5], 8, $element['statut_libelle'], 1, 0, 'C', $fillRow);
        $pdf->Ln();
        $fillRow = !$fillRow; // Alterne les couleurs des lignes
    }
    
    // Justificatifs
    if (!empty($justificatifs)) {
        $pdf->Ln(10);
        $pdf->SectionTitle('Justificatifs');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(100, 8, 'Nom du fichier', 1, 0, 'L', true);
        $pdf->Cell(50, 8, 'Taille', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Date d\'upload', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($justificatifs as $index => $file) {
            $fillBg = $index % 2 == 0;
            $pdf->Cell(100, 8, utf8_decode($file['nom_fichier']), 1, 0, 'L', $fillBg);
            $pdf->Cell(50, 8, round($file['taille_fichier'] / 1024) . ' KB', 1, 0, 'C', $fillBg);
            $pdf->Cell(40, 8, date('d/m/Y H:i', strtotime($file['uploaded_at'])), 1, 1, 'C', $fillBg);
        }
    }
    
    $pdf->Output('D', 'appel_fonds_' . $code_appel . '.pdf');
    
} catch (Exception $e) {
    error_log("Erreur export PDF: " . $e->getMessage());
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>
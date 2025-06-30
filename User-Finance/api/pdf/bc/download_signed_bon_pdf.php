<?php
// /DYM MANUFACTURE/expressions_besoins/User-Finance/api/pdf/bc/download_signed_bon_pdf.php

// Désactiver l'affichage des avertissements pour éviter les problèmes avec FPDF
error_reporting(E_ERROR | E_PARSE);

// Vérifier si l'utilisateur est connecté et est de type finance
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'finance') {
    die("Accès non autorisé");
}

require_once('../../../fpdf/fpdf.php');

// Connexion à la base de données
include_once '../../../../database/connection.php';

try {
    // Récupération de l'ID depuis l'URL
    $bonId = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($bonId)) {
        throw new Exception('ID invalide.');
    }

    // Récupérer les informations du bon de commande
    $orderInfo = getPurchaseOrderInfo($pdo, $bonId);
    
    if (!$orderInfo) {
        throw new Exception('Bon de commande non trouvé.');
    }

    // Vérifier si le bon est validé
    if (!$orderInfo['signature_finance'] || !$orderInfo['user_finance_id']) {
        throw new Exception("Ce bon de commande n'a pas encore été validé par le service finance");
    }

    $expressionId = $orderInfo['expression_id'];
    
    // Récupérer les détails du projet
    $projetDetails = getProjectDetails($pdo, $expressionId);
    
    if (!$projetDetails) {
        throw new Exception('Détails du projet non trouvés.');
    }

    // Récupérer les détails des expressions
    $expressions = getExpressionDetails($pdo, $expressionId);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée.');
    }

    // Récupérer les informations des signatures
    $userMap = getUserSignatures($pdo, $expressionId, $orderInfo);

    // Calculer le montant total
    $totalGeneral = 0;
    foreach ($expressions as $expression) {
        if (isset($expression['montant']) && is_numeric(str_replace(' ', '', $expression['montant']))) {
            $totalGeneral += floatval(str_replace(' ', '', $expression['montant']));
        }
    }
    
    // Si pas de montant dans les expressions, utiliser celui du bon de commande
    if ($totalGeneral == 0 && isset($orderInfo['montant_total'])) {
        $totalGeneral = $orderInfo['montant_total'];
    }

    $createdAt = date('d/m/Y', strtotime($orderInfo['generated_at']));
    $signedAt = date('d/m/Y', strtotime($orderInfo['signature_finance']));

} catch (Exception $e) {
    // Gérer l'erreur comme dans download_validated_bon_commande.php
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur de téléchargement</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .error-container { border: 1px solid #f5c6cb; padding: 20px; background-color: #f8d7da; border-radius: 5px; }
            h1 { color: #721c24; }
            p { margin-bottom: 15px; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .back-link:hover { background-color: #0056b3; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Erreur lors du téléchargement du bon de commande validé</h1>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <a href="../../../dashboard.php" class="back-link">Retour au tableau de bord</a>
        </div>
    </body>
    </html>';
    exit();
}

class PDF extends FPDF
{
    public $orderInfo;
    public $createdAt;
    public $signedAt;
    public $projetDetails;
    public $userMap;
    public $expressions;
    public $isLastPage = false;
    public $isFirstPage = true;
    public $totalGeneral = 0;

    function __construct($orderInfo, $createdAt, $signedAt, $projetDetails, $userMap, $expressions, $totalGeneral)
    {
        parent::__construct();
        $this->orderInfo = $orderInfo;
        $this->createdAt = $createdAt;
        $this->signedAt = $signedAt;
        $this->projetDetails = $projetDetails;
        $this->userMap = $userMap;
        $this->expressions = $expressions;
        $this->totalGeneral = $totalGeneral;
    }

    // Fonction pour convertir les caractères spéciaux en ISO-8859-1 pour FPDF
    function strConvert($str)
    {
        if ($str === null)
            return '';
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
    }

    function Header()
    {
        if ($this->isFirstPage) {
            // Logo et informations de l'entreprise
            $logoPath = '../../../../public/logo.png';
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 15, 10, 30);
            }

            // Titre et informations du bon de commande
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, $this->strConvert('BON DE COMMANDE'), 0, 1, 'R');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, $this->strConvert('N° ' . $this->orderInfo['order_number'] . ' (VALIDÉ)'), 0, 1, 'R');
            $this->Cell(0, 5, $this->strConvert('Date: ' . $this->createdAt), 0, 1, 'R');
            $this->Cell(0, 5, $this->strConvert('Date de validation: ' . $this->signedAt), 0, 1, 'R');

            // Filigrane "VALIDÉ"
            $this->SetFont('Arial', 'B', 50);
            $this->SetTextColor(230, 230, 230); // Gris très clair
            $currentX = $this->GetX();
            $currentY = $this->GetY();
            $this->SetXY(60, 80);
            $this->Cell(90, 20, 'VALIDE', 0, 0, 'C');
            $this->SetXY($currentX, $currentY);
            $this->SetTextColor(0, 0, 0); // Retour au noir
            $this->SetFont('Arial', '', 10);

            // Espacement après l'en-tête
            $this->Ln(10);

            // Informations du fournisseur
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, $this->strConvert('FOURNISSEUR'), 0, 1, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, $this->strConvert($this->orderInfo['fournisseur']), 0, 1, 'L');
            
            $this->Ln(5);

            // Changer l'état de isFirstPage à false
            $this->isFirstPage = false;
        }
    }

    function ProjectTable()
    {
        // Titre du tableau
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->strConvert('INFORMATIONS DU PROJET'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);

        // Largeur totale de la page
        $pageWidth = $this->GetPageWidth() - 20;

        // Largeur des colonnes
        $colWidth1 = 60;
        $colWidth2 = 130;

        // Bordure et police pour le tableau du projet
        $this->SetLineWidth(0.2);

        // Détails du projet
        $this->Cell($colWidth1, 8, $this->strConvert('Code du projet'), 1);
        $this->Cell($colWidth2, 8, $this->strConvert($this->projetDetails['code_projet']), 1, 1);

        $this->Cell($colWidth1, 8, $this->strConvert('Nom du client'), 1);
        $this->Cell($colWidth2, 8, $this->strConvert($this->projetDetails['nom_client']), 1, 1);

        $this->Cell($colWidth1, 8, $this->strConvert('Description'), 1);
        $this->Cell($colWidth2, 8, $this->strConvert($this->projetDetails['description_projet']), 1, 1);

        if (!empty($this->projetDetails['sitgeo'])) {
            $this->Cell($colWidth1, 8, $this->strConvert('Situation géo.'), 1);
            $this->Cell($colWidth2, 8, $this->strConvert($this->projetDetails['sitgeo']), 1, 1);
        }

        if (!empty($this->projetDetails['chefprojet'])) {
            $this->Cell($colWidth1, 8, $this->strConvert('Chef de Projet'), 1);
            $this->Cell($colWidth2, 8, $this->strConvert($this->projetDetails['chefprojet']), 1, 1);
        }

        $this->Ln(10);
    }

    function ExpressionTable()
    {
        // Titre du tableau des matériaux
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->strConvert('LISTE DES MATÉRIAUX'), 0, 1, 'L');
        $this->SetFont('Arial', '', 10);

        // Configuration des largeurs des colonnes
        $colDesignation = 70;
        $colQuantite = 25;
        $colUnite = 20;
        $colPrix = 35;
        $colMontant = 40;

        // En-têtes du tableau
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($colDesignation, 7, $this->strConvert('Désignation'), 1, 0, 'C', true);
        $this->Cell($colQuantite, 7, $this->strConvert('Quantité'), 1, 0, 'C', true);
        $this->Cell($colUnite, 7, $this->strConvert('Unité'), 1, 0, 'C', true);
        $this->Cell($colPrix, 7, $this->strConvert('Prix Unitaire'), 1, 0, 'C', true);
        $this->Cell($colMontant, 7, $this->strConvert('Montant Total'), 1, 1, 'C', true);

        // Données des expressions
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', '', 8);

        $currentType = '';
        $currentSubtotal = 0;
        $currentTypeCount = 0;
        $totalGeneral = 0;

        foreach ($this->expressions as $expression) {
            // Vérifier l'espace disponible
            if ($this->GetY() + 10 > $this->PageBreakTrigger - 40) {
                $this->AddPage();
                // Réafficher les en-têtes
                $this->SetFont('Arial', 'B', 9);
                $this->Cell($colDesignation, 7, $this->strConvert('Désignation'), 1, 0, 'C', true);
                $this->Cell($colQuantite, 7, $this->strConvert('Quantité'), 1, 0, 'C', true);
                $this->Cell($colUnite, 7, $this->strConvert('Unité'), 1, 0, 'C', true);
                $this->Cell($colPrix, 7, $this->strConvert('Prix Unitaire'), 1, 0, 'C', true);
                $this->Cell($colMontant, 7, $this->strConvert('Montant Total'), 1, 1, 'C', true);
                $this->SetFont('Arial', '', 8);
            }

            // Si le type change, afficher le nouveau type
            if ($expression['type'] !== $currentType) {
                // Afficher le sous-total pour le type précédent
                if ($currentTypeCount > 0) {
                    $this->SetFillColor(200, 220, 255);
                    $this->Cell($colDesignation + $colQuantite + $colUnite + $colPrix, 6, $this->strConvert('Sous-total pour ' . $currentType), 1, 0, 'R', true);
                    $this->Cell($colMontant, 6, number_format($currentSubtotal, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);

                    // Réinitialiser
                    $currentSubtotal = 0;
                    $currentTypeCount = 0;
                }

                // Changer le type courant
                $currentType = $expression['type'];
                $this->SetFillColor(220, 220, 220);
                $this->Cell($colDesignation + $colQuantite + $colUnite + $colPrix + $colMontant, 6, $this->strConvert($currentType), 1, 1, 'L', true);
                $this->SetFillColor(255, 255, 255);
            }

            // Remplir les données
            $montant = is_numeric(str_replace(' ', '', $expression['montant'])) ? floatval(str_replace(' ', '', $expression['montant'])) : 0;
            
            $this->Cell($colDesignation, 6, $this->strConvert($expression['designation']), 1);
            $this->Cell($colQuantite, 6, number_format(floatval($expression['quantity']), 2, ',', ' '), 1, 0, 'C');
            $this->Cell($colUnite, 6, $this->strConvert($expression['unit']), 1, 0, 'C');
            $this->Cell($colPrix, 6, number_format(floatval(str_replace(' ', '', $expression['prix_unitaire'])), 0, ',', ' ') . ' F', 1, 0, 'R');
            $this->Cell($colMontant, 6, number_format($montant, 0, ',', ' ') . ' F', 1, 1, 'R');
            
            // Accumuler les totaux
            $currentSubtotal += $montant;
            $totalGeneral += $montant;
            $currentTypeCount++;
        }

        // Afficher le sous-total pour le dernier type
        if ($currentTypeCount > 0) {
            $this->SetFillColor(200, 220, 255);
            $this->Cell($colDesignation + $colQuantite + $colUnite + $colPrix, 6, $this->strConvert('Sous-total pour ' . $currentType), 1, 0, 'R', true);
            $this->Cell($colMontant, 6, number_format($currentSubtotal, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);
        }

        // TOTAL GÉNÉRAL
        $this->SetFillColor(169, 169, 169);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($colDesignation + $colQuantite + $colUnite + $colPrix, 8, $this->strConvert('TOTAL GÉNÉRAL'), 1, 0, 'R', true);
        $this->Cell($colMontant, 8, number_format($totalGeneral, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);

        // Marquer comme dernière page
        $this->isLastPage = true;
    }

    function Footer()
    {
        if ($this->isLastPage) {
            $footerHeight = 60;
            
            // Vérifier si on a assez de place sur la page actuelle
            if ($this->GetY() + $footerHeight > $this->PageBreakTrigger) {
                $this->AddPage();
            }

            // Titre "SIGNATURES"
            $this->Ln(10);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'SIGNATURES', 0, 1, 'L');
            $this->SetFont('Arial', '', 10);

            // Largeur des colonnes pour 3 signatures
            $colWidth = 63;

            // En-tête avec les noms
            $y_position = $this->GetY();
            
            // Noms des responsables
            $this->SetFont('Arial', '', 9);
            $this->Cell($colWidth, 6, $this->strConvert('Service BE'), 0, 0, 'C');
            $this->Cell($colWidth, 6, $this->strConvert('Service Achat'), 0, 0, 'C');
            $this->Cell($colWidth, 6, $this->strConvert('Service Finance'), 0, 1, 'C');
            
            // Zones de signature
            $signatureHeight = 30;
            $this->Cell($colWidth, $signatureHeight, '', 1, 0, 'C');
            $this->Cell($colWidth, $signatureHeight, '', 1, 0, 'C');
            $this->Cell($colWidth, $signatureHeight, '', 1, 1, 'C');
            
            $signature_y = $y_position + 6;
            $start_x = $this->GetX();
            
            // Ajouter les signatures si disponibles
            if (!empty($this->userMap['emet_signature'])) {
                $signaturePath = '../../../../uploads/' . $this->userMap['emet_signature'];
                if (file_exists($signaturePath)) {
                    $this->Image($signaturePath, $start_x + 5, $signature_y + 5, $colWidth - 10, 20);
                }
            }
            
            if (!empty($this->userMap['achat_signature'])) {
                $signaturePath = '../../../../uploads/' . $this->userMap['achat_signature'];
                if (file_exists($signaturePath)) {
                    $this->Image($signaturePath, $start_x + $colWidth + 5, $signature_y + 5, $colWidth - 10, 20);
                }
            }
            
            if (!empty($this->userMap['finance_signature'])) {
                $signaturePath = '../../../../uploads/' . $this->userMap['finance_signature'];
                if (file_exists($signaturePath)) {
                    $this->Image($signaturePath, $start_x + ($colWidth * 2) + 5, $signature_y + 5, $colWidth - 10, 20);
                }
            }
            
            // Noms des signataires
            $this->SetFont('Arial', '', 8);
            $this->Cell($colWidth, 6, $this->strConvert($this->userMap['emet_name'] ?? ''), 0, 0, 'C');
            $this->Cell($colWidth, 6, $this->strConvert($this->userMap['achat_name'] ?? ''), 0, 0, 'C');
            $this->Cell($colWidth, 6, $this->strConvert($this->userMap['finance_name'] ?? ''), 0, 1, 'C');
            
            // Notes de bas de page
            $this->Ln(5);
            $this->SetFont('Arial', 'I', 8);
            $this->MultiCell(0, 4, $this->strConvert('Ce bon de commande validé est généré automatiquement par le système de gestion des achats.'), 0, 'C');
        }
    }
}

// Création du PDF
$pdf = new PDF($orderInfo, $createdAt, $signedAt, $projetDetails, $userMap, $expressions, $totalGeneral);
$pdf->AddPage();

// Ajout des données
$pdf->ProjectTable();
$pdf->ExpressionTable();

// Vider toute sortie mise en mémoire tampon avant d'envoyer les en-têtes
if (ob_get_length()) {
    ob_clean();
}

// Générer un nom de fichier pour la version validée
$validatedFilename = 'Bon_Commande_Valide_' . $orderInfo['order_number'] . '.pdf';

// Envoyer le PDF au navigateur
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $validatedFilename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$pdf->Output('I', $validatedFilename);
exit();

/**
 * Récupère les informations du bon de commande depuis purchase_orders
 */
function getPurchaseOrderInfo($pdo, $bonId) {
    $sql = "
        SELECT 
            po.id,
            po.order_number,
            po.expression_id,
            po.fournisseur,
            po.montant_total,
            po.generated_at,
            po.signature_finance,
            po.user_finance_id,
            u_finance.name as finance_name,
            u_finance.signature as finance_signature,
            u_achat.name as achat_name,
            u_achat.signature as achat_signature
        FROM purchase_orders po
        LEFT JOIN users_exp u_finance ON po.user_finance_id = u_finance.id
        LEFT JOIN users_exp u_achat ON po.user_id = u_achat.id
        WHERE po.order_number = :bon_id OR po.id = :bon_id2
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':bon_id', $bonId);
    $stmt->bindParam(':bon_id2', $bonId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails du projet depuis les deux tables possibles
 */
function getProjectDetails($pdo, $expressionId) {
    // D'abord, essayer de récupérer depuis identification_projet
    $sqlProjet = "
        SELECT 
            code_projet,
            nom_client,
            description_projet,
            sitgeo,
            chefprojet
        FROM identification_projet
        WHERE idExpression = :id
        LIMIT 1
    ";
    
    $stmtProjet = $pdo->prepare($sqlProjet);
    $stmtProjet->bindParam(':id', $expressionId);
    $stmtProjet->execute();
    
    $projet = $stmtProjet->fetch(PDO::FETCH_ASSOC);
    
    if ($projet) {
        return $projet;
    }
    
    // Si pas trouvé, essayer depuis besoins (expression système)
    $sqlBesoin = "
        SELECT 
            idBesoin as code_projet,
            'Expression Système' as nom_client,
            designation_article as description_projet,
            type as sitgeo,
            user_emet as chefprojet
        FROM besoins
        WHERE idBesoin = :id
        LIMIT 1
    ";
    
    $stmtBesoin = $pdo->prepare($sqlBesoin);
    $stmtBesoin->bindParam(':id', $expressionId);
    $stmtBesoin->execute();
    
    return $stmtBesoin->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails des expressions
 */
function getExpressionDetails($pdo, $expressionId) {
    // D'abord essayer depuis expression_dym
    $sqlExpression = "
        SELECT 
            e.designation,
            e.unit,
            e.quantity,
            e.qt_acheter,
            e.prix_unitaire,
            e.montant,
            IFNULL(e.type, 'MATÉRIEL') as type
        FROM expression_dym e
        WHERE e.idExpression = :id
        ORDER BY e.type, e.id
    ";
    
    $stmtExpression = $pdo->prepare($sqlExpression);
    $stmtExpression->bindParam(':id', $expressionId);
    $stmtExpression->execute();
    
    $expressions = $stmtExpression->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($expressions)) {
        return $expressions;
    }
    
    // Si pas trouvé, essayer depuis achats_materiaux
    $sqlAchats = "
        SELECT 
            am.designation,
            am.unit,
            am.quantity as qt_acheter,
            am.original_quantity as quantity,
            am.prix_unitaire,
            (am.quantity * am.prix_unitaire) as montant,
            'MATÉRIEL' as type
        FROM achats_materiaux am
        WHERE am.expression_id = :id
        ORDER BY am.id
    ";
    
    $stmtAchats = $pdo->prepare($sqlAchats);
    $stmtAchats->bindParam(':id', $expressionId);
    $stmtAchats->execute();
    
    return $stmtAchats->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations des signatures
 */
function getUserSignatures($pdo, $expressionId, $orderInfo) {
    $userMap = [
        'emet_name' => '',
        'emet_signature' => '',
        'achat_name' => $orderInfo['achat_name'] ?? '',
        'achat_signature' => $orderInfo['achat_signature'] ?? '',
        'finance_name' => $orderInfo['finance_name'] ?? '',
        'finance_signature' => $orderInfo['finance_signature'] ?? ''
    ];
    
    // Essayer de récupérer depuis expression_dym
    $sql = "
        SELECT 
            u_emet.name as emet_name,
            u_emet.signature as emet_signature
        FROM expression_dym e
        LEFT JOIN users_exp u_emet ON e.user_emet = u_emet.id
        WHERE e.idExpression = :id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $expressionId);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $userMap['emet_name'] = $result['emet_name'];
        $userMap['emet_signature'] = $result['emet_signature'];
    }
    
    return $userMap;
}
?>
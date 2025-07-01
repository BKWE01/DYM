<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit();
}

$orderId = intval($_GET['id']);

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // 1. Récupérer les informations du bon de commande
    $query = "SELECT po.*, u.name as user_achat_name, u.signature as user_achat_signature, 
                    uf.name as user_finance_name, uf.signature as user_finance_signature
              FROM purchase_orders po
              LEFT JOIN users_exp u ON po.user_id = u.id
              LEFT JOIN users_exp uf ON po.user_finance_id = uf.id 
              WHERE po.id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Bon de commande non trouvé");
    }

    // Vérifier si le bon de commande est validé
    if (!$order['signature_finance'] || !$order['user_finance_id']) {
        throw new Exception("Ce bon de commande n'a pas encore été validé par le service finance");
    }

    // 2. NOUVEAU : Récupérer le mode de paiement depuis achats_materiaux
    $paymentMode = 'Non spécifié'; // Valeur par défaut
    
    // Récupérer le libellé du mode de paiement depuis les achats liés à ce bon de commande
    $paymentQuery = "SELECT DISTINCT pm.label
                     FROM achats_materiaux am
                     JOIN payment_methods pm ON am.mode_paiement_id = pm.id
                     WHERE am.expression_id = ?
                     AND am.fournisseur = ?
                     AND DATE(am.date_achat) = DATE(?)
                     AND am.mode_paiement_id IS NOT NULL
                     LIMIT 1";
    
    $paymentStmt = $pdo->prepare($paymentQuery);
    $paymentStmt->execute([$order['expression_id'], $order['fournisseur'], $order['generated_at']]);
    $paymentResult = $paymentStmt->fetchColumn();
    
    if ($paymentResult) {
        $paymentMode = $paymentResult;
    } else {
        // Si pas trouvé, essayer avec les expressions liées
        $relatedExpressions = json_decode($order['related_expressions'], true);
        if ($relatedExpressions && is_array($relatedExpressions)) {
            $placeholders = implode(',', array_fill(0, count($relatedExpressions), '?'));
            $altPaymentQuery = "SELECT DISTINCT pm.label
                               FROM achats_materiaux am
                               JOIN payment_methods pm ON am.mode_paiement_id = pm.id
                               WHERE am.expression_id IN ($placeholders)
                               AND am.fournisseur = ?
                               AND am.mode_paiement_id IS NOT NULL
                               LIMIT 1";
            
            $params = $relatedExpressions;
            $params[] = $order['fournisseur'];
            
            $altPaymentStmt = $pdo->prepare($altPaymentQuery);
            $altPaymentStmt->execute($params);
            $altPaymentResult = $altPaymentStmt->fetchColumn();
            
            if ($altPaymentResult) {
                $paymentMode = $altPaymentResult;
            }
        }
    }

    // 3. Récupérer les informations des projets associés
    $projects = [];
    $relatedExpressions = json_decode($order['related_expressions'], true);

    if ($relatedExpressions && is_array($relatedExpressions)) {
        // Séparer les IDs selon leur format (besoins ou projet)
        $projetsIds = [];
        $besoinsIds = [];

        foreach ($relatedExpressions as $expressionId) {
            if (strpos($expressionId, 'EXP_B') !== false) {
                $besoinsIds[] = $expressionId;
            } else {
                $projetsIds[] = $expressionId;
            }
        }

        // Récupérer les projets réguliers
        if (!empty($projetsIds)) {
            $placeholders = implode(',', array_fill(0, count($projetsIds), '?'));
            $projectsQuery = "SELECT 
                          idExpression, 
                          code_projet, 
                          nom_client, 
                          description_projet,
                          chefprojet,
                          sitgeo
                        FROM identification_projet 
                        WHERE idExpression IN ($placeholders)";

            $projectsStmt = $pdo->prepare($projectsQuery);

            // Lier les paramètres
            foreach ($projetsIds as $index => $expressionId) {
                $projectsStmt->bindValue($index + 1, $expressionId);
            }

            $projectsStmt->execute();
            $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Récupérer les projets système (besoins)
        if (!empty($besoinsIds)) {
            $placeholders = implode(',', array_fill(0, count($besoinsIds), '?'));
            $besoinsQuery = "SELECT 
                          b.idBesoin as idExpression,
                          CONCAT('SYS-', d.service_demandeur) as code_projet,
                          d.client as nom_client,
                          d.motif_demande as description_projet,
                          'Système' as chefprojet,
                          'N/A' as sitgeo
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE b.idBesoin IN ($placeholders)";

            $besoinsStmt = $pdo->prepare($besoinsQuery);

            // Lier les paramètres
            foreach ($besoinsIds as $index => $expressionId) {
                $besoinsStmt->bindValue($index + 1, $expressionId);
            }

            $besoinsStmt->execute();
            $besoinsProjects = $besoinsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fusionner avec les projets réguliers
            $projects = array_merge($projects, $besoinsProjects);
        }
    } else {
        // Si pas de projets liés, chercher le projet principal
        if (strpos($order['expression_id'], 'EXP_B') !== false) {
            // C'est une expression système
            $besoinsQuery = "SELECT 
                          b.idBesoin as idExpression,
                          CONCAT('SYS-', d.service_demandeur) as code_projet,
                          d.client as nom_client,
                          d.motif_demande as description_projet,
                          'Système' as chefprojet,
                          'N/A' as sitgeo
                        FROM besoins b
                        LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                        WHERE b.idBesoin = ?";

            $besoinsStmt = $pdo->prepare($besoinsQuery);
            $besoinsStmt->execute([$order['expression_id']]);
            $projects = $besoinsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // C'est un projet régulier
            $projectsQuery = "SELECT 
                          idExpression, 
                          code_projet, 
                          nom_client, 
                          description_projet,
                          chefprojet,
                          sitgeo
                        FROM identification_projet 
                        WHERE idExpression = ?";

            $projectsStmt = $pdo->prepare($projectsQuery);
            $projectsStmt->execute([$order['expression_id']]);
            $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (empty($projects)) {
        throw new Exception("Aucune information de projet trouvée pour ce bon de commande.");
    }

    // 4. Récupérer les matériaux exactement comme dans la version originale
    // Vérifier s'il existe une table qui enregistre les matériaux spécifiques à chaque bon de commande
    $checkTableQuery = "SHOW TABLES LIKE 'purchase_order_materials'";
    $checkTableStmt = $pdo->prepare($checkTableQuery);
    $checkTableStmt->execute();
    $tableExists = $checkTableStmt->rowCount() > 0;

    $rawMaterials = [];

    if ($tableExists) {
        // Si la table existe, récupérer directement les matériaux liés à ce bon de commande
        $materialsQuery = "SELECT 
                         material_id,
                         designation, 
                         quantity as qt_acheter,
                         unit,
                         prix_unitaire,
                         fournisseur,
                         expression_id
                       FROM purchase_order_materials
                       WHERE purchase_order_id = ?
                       ORDER BY designation";

        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->execute([$orderId]);
        $rawMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Sinon, récupérer les matériaux liés aux expressions avec la date et le fournisseur exact
        $expressionIds = $relatedExpressions ?: [$order['expression_id']];

        // Préparer les placeholders pour la requête IN
        $placeholders = implode(',', array_fill(0, count($expressionIds), '?'));

        // Récupérer les matériaux commandés pour ces expressions à la date exacte du bon de commande
        $materialsQuery = "SELECT 
                         am.designation, 
                         am.quantity as qt_acheter,
                         am.unit,
                         am.prix_unitaire,
                         am.fournisseur,
                         am.date_achat,
                         ip.code_projet,
                         ip.nom_client
                       FROM achats_materiaux am
                       LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
                       WHERE am.expression_id IN ($placeholders)
                       AND DATE(am.date_achat) = DATE(?)
                       AND am.fournisseur = ?
                       ORDER BY am.designation";

        $materialsStmt = $pdo->prepare($materialsQuery);

        // Créer un tableau pour tous les paramètres
        $params = $expressionIds;
        // Ajouter la date et le fournisseur
        $params[] = $order['generated_at'];
        $params[] = $order['fournisseur'];

        $materialsStmt->execute($params);
        $rawMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucun matériau n'est trouvé avec la date exacte, élargir légèrement la recherche
        if (empty($rawMaterials)) {
            $alternativeMaterialsQuery = "SELECT 
                                       am.designation, 
                                       am.quantity as qt_acheter,
                                       am.unit,
                                       am.prix_unitaire,
                                       am.fournisseur,
                                       am.date_achat,
                                       ip.code_projet,
                                       ip.nom_client
                                     FROM achats_materiaux am
                                     LEFT JOIN identification_projet ip ON am.expression_id = ip.idExpression
                                     WHERE am.expression_id IN ($placeholders)
                                     AND am.fournisseur = ?
                                     AND am.date_achat BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
                                     ORDER BY ABS(TIMESTAMPDIFF(SECOND, am.date_achat, ?))
                                     LIMIT 50";

            $alternativeMaterialsStmt = $pdo->prepare($alternativeMaterialsQuery);

            // Créer un tableau pour tous les paramètres
            $alternativeParams = $expressionIds;
            // Ajouter le fournisseur et les dates
            $alternativeParams[] = $order['fournisseur'];
            $alternativeParams[] = $order['generated_at'];
            $alternativeParams[] = $order['generated_at'];
            $alternativeParams[] = $order['generated_at'];

            $alternativeMaterialsStmt->execute($alternativeParams);
            $rawMaterials = $alternativeMaterialsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Consolidation des matériaux (regrouper les matériaux identiques)
    $consolidatedMaterials = [];
    foreach ($rawMaterials as $material) {
        $designation = trim($material['designation']);
        $unit = trim($material['unit']);

        // Clé unique pour le matériau
        $materialKey = md5($designation . '|' . $unit);

        // Quantité et prix pour ce matériau
        $quantity = floatval($material['qt_acheter']);
        $prix = floatval($material['prix_unitaire']);

        if (!isset($consolidatedMaterials[$materialKey])) {
            // Première occurrence de ce matériau
            $consolidatedMaterials[$materialKey] = [
                'designation' => $designation,
                'qt_acheter' => $quantity,
                'unit' => $unit,
                'prix_unitaire' => $prix,
                'montant_total' => $quantity * $prix
            ];
        } else {
            // Matériau déjà rencontré, ajouter la quantité
            $consolidatedMaterials[$materialKey]['qt_acheter'] += $quantity;
            $consolidatedMaterials[$materialKey]['montant_total'] =
                $consolidatedMaterials[$materialKey]['qt_acheter'] * $consolidatedMaterials[$materialKey]['prix_unitaire'];
        }
    }

    // Convertir en tableau indexé
    $materials = array_values($consolidatedMaterials);

    // Calculer le montant total
    $totalAmount = 0;
    foreach ($materials as $material) {
        $totalAmount += $material['montant_total'];
    }

    // Vérifier les chemins des signatures
    $achatSignaturePath = '../../../uploads/' . $order['user_achat_signature'];
    $financeSignaturePath = '../../../uploads/' . $order['user_finance_signature'];

    // Vérifier l'existence des fichiers de signatures
    $achatSignatureExists = file_exists($achatSignaturePath) && !is_dir($achatSignaturePath) && !empty($order['user_achat_signature']);
    $financeSignatureExists = file_exists($financeSignaturePath) && !is_dir($financeSignaturePath) && !empty($order['user_finance_signature']);

    // 5. Générer le PDF
    require_once('../../tcpdf/tcpdf.php');

    // Classe PDF personnalisée
    class PDF extends TCPDF
    {
        protected $bonCommandeNumber;
        protected $currentDate;
        protected $fournisseurCommande;
        protected $paymentMode;
        protected $validated;

        // Constructeur personnalisé - MODIFIÉ pour inclure le mode de paiement
        function __construct($bonCommandeNumber, $currentDate, $fournisseurCommande, $paymentMode, $validated = true)
        {
            // Orientation P (portrait), unité mm, format A4
            parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

            $this->bonCommandeNumber = $bonCommandeNumber;
            $this->currentDate = $currentDate;
            $this->fournisseurCommande = $fournisseurCommande;
            $this->paymentMode = $paymentMode;
            $this->validated = $validated;

            // Définir les informations du document
            $this->SetCreator(PDF_CREATOR);
            $this->SetAuthor('DYM MANUFACTURE');
            $this->SetTitle('Bon de Commande ' . $bonCommandeNumber . ($validated ? ' (Validé)' : ''));
            $this->SetSubject('Bon de Commande');

            // Définir les marges
            $this->SetMargins(15, 15, 15);
            $this->SetHeaderMargin(10);
            $this->SetFooterMargin(10);

            // Désactiver l'en-tête et le pied de page automatiques
            $this->setPrintHeader(false);
            $this->setPrintFooter(false);

            // Activer le mode de saut de page automatique
            $this->SetAutoPageBreak(TRUE, 10);

            // Définir le facteur d'échelle pour les images
            $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
        }

        // Méthode pour créer une rangée avec des cellules de même hauteur
        public function Row($data, $widths, $aligns = [], $fills = [], $heights = [], $styles = [])
        {
            // Nombre de colonnes
            $num = count($data);

            if ($num == 0)
                return;

            // Vérifier si les tableaux d'alignement et de style sont complets
            while (count($aligns) < $num)
                $aligns[] = 'L';
            while (count($fills) < $num)
                $fills[] = false;
            while (count($styles) < $num)
                $styles[] = '';

            // Sauvegarder la position Y initiale
            $startY = $this->GetY();
            $startX = $this->GetX();
            $maxHeight = 0;

            // Première passe : déterminer la hauteur maximale nécessaire
            for ($i = 0; $i < $num; $i++) {
                // Si une hauteur spécifique est définie pour cette cellule, l'utiliser
                if (isset($heights[$i]) && $heights[$i] > 0) {
                    $cellHeight = $heights[$i];
                } else {
                    // Sinon, calculer la hauteur nécessaire
                    $this->startTransaction();
                    $this->SetX($startX);
                    for ($j = 0; $j < $i; $j++) {
                        $this->SetX($this->GetX() + $widths[$j]);
                    }

                    // Calculer la hauteur sans imprimer
                    $this->MultiCell($widths[$i], 0, $data[$i], 0, $aligns[$i], $fills[$i], 1);
                    $cellHeight = $this->GetY() - $startY;

                    // Annuler pour revenir à la position initiale
                    $this->rollbackTransaction(true);
                }

                // Mettre à jour la hauteur maximale
                $maxHeight = max($maxHeight, $cellHeight);
            }

            // Vérifier si un changement de page est nécessaire
            if ($startY + $maxHeight > $this->getPageHeight() - $this->getBreakMargin()) {
                $this->AddPage();
                $startY = $this->GetY();
                $startX = $this->GetX();
            }

            // Deuxième passe : dessiner toutes les cellules avec la même hauteur
            $this->SetXY($startX, $startY);

            for ($i = 0; $i < $num; $i++) {
                // Appliquer le style si spécifié
                if (!empty($styles[$i])) {
                    $this->SetFont('', $styles[$i]);
                }

                // Dessiner la cellule
                $this->MultiCell($widths[$i], $maxHeight, $data[$i], 1, $aligns[$i], $fills[$i], 0);
            }

            // Aller à la ligne suivante
            $this->Ln($maxHeight);

            // Restaurer le style par défaut
            $this->SetFont('', '');

            return $maxHeight;
        }
    }

    // Créer une nouvelle instance de PDF - MODIFIÉ pour inclure le mode de paiement
    $pdf = new PDF($order['order_number'], date('d/m/Y', strtotime($order['generated_at'])), $order['fournisseur'], $paymentMode, true);

    // Définir la police
    $pdf->SetFont('helvetica', '', 10);

    // Ajouter une page
    $pdf->AddPage();

    // Logo et informations de l'entreprise
    $logoPath = '../../../public/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 10, 30);
    }

    // Titre et informations du bon de commande
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'BON DE COMMANDE', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'N° ' . $order['order_number'] . ' (VALIDÉ)', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Date: ' . date('d/m/Y', strtotime($order['generated_at'])), 0, 1, 'R');

    // Date de validation
    $pdf->Cell(0, 5, 'Date de validation: ' . date('d/m/Y', strtotime($order['signature_finance'])), 0, 1, 'R');

    // FILIGRANE "VALIDÉ" OPTIMISÉ - Discret mais visible
    $pdf->SetFont('helvetica', 'B', 45);
    $pdf->SetTextColor(246, 240, 240); // Gris ultra-léger 246 240 240
    
    // Sauvegarder la position actuelle
    $currentX = $pdf->GetX();
    $currentY = $pdf->GetY();
    
    // Positionner le filigrane en diagonale, décalé vers le bas-droite
    $pdf->StartTransform();
    $pdf->Rotate(30, 105, 180); // Rotation de 30° (moins aggressive)
    $pdf->SetXY(75, 170);
    $pdf->Cell(60, 15, 'VALIDÉ', 0, 0, 'C', false);
    $pdf->StopTransform();
    
    // Restaurer la position et la couleur
    $pdf->SetXY($currentX, $currentY);
    $pdf->SetTextColor(0, 0, 0); // Retour au noir
    $pdf->SetFont('helvetica', '', 10);

    // Espace
    $pdf->Ln(10);

    // MODIFIÉ : Section fournisseur et mode de paiement (comme dans le bon de commande normal)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMATIONS FOURNISSEUR ET PAIEMENT', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Tableau des informations fournisseur et paiement
    $pdf->SetFillColor(245, 245, 245);
    $supplierPaymentData = [
        ['Fournisseur :', $order['fournisseur']],
        ['Mode de paiement :', $paymentMode]
    ];

    foreach ($supplierPaymentData as $row) {
        $pdf->Row($row, [60, 120], ['L', 'L'], [true, false]);
    }

    // Espace
    $pdf->Ln(5);

    // Informations des projets
    $pdf->SetFont('helvetica', 'B', 12);

    if (count($projects) > 1) {
        $pdf->Cell(0, 8, 'COMMANDE MULTI-PROJETS (' . count($projects) . ' projets)', 0, 1, 'L');
    } else {
        $pdf->Cell(0, 8, 'INFORMATIONS DU PROJET', 0, 1, 'L');
    }

    $pdf->SetFont('helvetica', '', 10);

    // Tableau des matériaux
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'LISTE DES MATÉRIAUX', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Configuration des largeurs des colonnes
    $colDesignation = 85;
    $colQuantite = 20;
    $colUnite = 20;
    $colPrix = 25;
    $colMontant = 30;

    // En-têtes du tableau consolidé
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Row(
        ['Désignation', 'Quantité', 'Unité', 'Prix Unitaire', 'Montant Total'],
        [$colDesignation, $colQuantite, $colUnite, $colPrix, $colMontant],
        ['C', 'C', 'C', 'C', 'C'],
        [true, true, true, true, true],
        [],
        ['B', 'B', 'B', 'B', 'B']
    );

    // Contenu du tableau consolidé
    $pdf->SetFillColor(255, 255, 255);

    foreach ($materials as $material) {
        // Formater les données
        $quantityFormatted = number_format($material['qt_acheter'], 2, ',', ' ');
        $prixFormatted = number_format((float) $material['prix_unitaire'], 0, ',', ' ') . ' FCFA';
        $montantFormatted = number_format((float) $material['montant_total'], 0, ',', ' ') . ' FCFA';

        // Utiliser la méthode Row pour garantir une hauteur uniforme
        $pdf->Row(
            [
                $material['designation'],
                $quantityFormatted,
                $material['unit'],
                $prixFormatted,
                $montantFormatted
            ],
            [$colDesignation, $colQuantite, $colUnite, $colPrix, $colMontant],
            ['L', 'C', 'C', 'R', 'R']
        );
    }

    // Total
    $pdf->SetFont('helvetica', 'B', 10);
    $totalFormatted = number_format($totalAmount, 0, ',', ' ') . ' FCFA';
    $pdf->Row(
        ['TOTAL', $totalFormatted],
        [$colDesignation + $colQuantite + $colUnite + $colPrix, $colMontant],
        ['R', 'R'],
        [true, true],
        [],
        ['B', 'B']
    );

    // Calculer la hauteur nécessaire pour la section signature (environ 6cm)
    $signatureSectionHeight = 60; // 60mm = 6cm

    // Calculer la position Y minimum pour commencer les signatures
    $pageHeight = $pdf->getPageHeight();
    $bottomMargin = $pdf->getBreakMargin(); // Marge basse automatique (généralement 10mm)
    $minSignatureY = $pageHeight - $signatureSectionHeight - $bottomMargin;

    // Obtenir la position Y actuelle
    $currentY = $pdf->GetY();

    // Si on est trop haut, ajouter un espace pour pousser les signatures vers le bas
    if ($currentY < $minSignatureY) {
        $neededSpace = $minSignatureY - $currentY;
        $pdf->Ln($neededSpace);
    }
    // Si on est déjà en bas, forcer une nouvelle page
    elseif ($currentY > $minSignatureY) {
        $pdf->AddPage();
    }

    // Titre "SIGNATURES"
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'SIGNATURES', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Calculer la largeur disponible pour les signatures
    $pageWidth = $pdf->getPageWidth();
    $margins = $pdf->getMargins();
    $availableWidth = $pageWidth - $margins['left'] - $margins['right'];
    $signatureColWidth = $availableWidth / 2;

    // En-têtes des signatures
    $pdf->Row(
        [
            'Service achat: ' . $order['user_achat_name'],
            'Service finance: ' . $order['user_finance_name']
        ],
        [$signatureColWidth, $signatureColWidth],
        ['L', 'L']
    );

    // Zone de signature
    $signatureHeight = 35;
    $pdf->SetLineWidth(0.1);
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    // Cellules vides pour signatures
    $pdf->Cell($signatureColWidth, $signatureHeight, '', 1, 0, 'L');
    $pdf->Cell($signatureColWidth, $signatureHeight, '', 1, 1, 'L');

    // SIGNATURE DE L'ACHETEUR
    if ($achatSignatureExists) {
        // Valider que c'est un fichier image valide avant de tenter de l'utiliser
        $validImage = false;
        $signaturePath = $achatSignaturePath;
        
        try {
            // Vérifier si c'est une image valide
            $imageInfo = @getimagesize($signaturePath);
            $validImage = $imageInfo !== false && is_array($imageInfo) && !empty($imageInfo[0]) && !empty($imageInfo[1]);
        } catch (Exception $e) {
            $validImage = false;
        }
        
        if ($validImage) {
            $origWidth = $imageInfo[0];
            $origHeight = $imageInfo[1];
            
            // Éviter la division par zéro
            if ($origWidth > 0 && $origHeight > 0) {
                $maxWidth = $signatureColWidth * 0.6;
                $maxHeight = $signatureHeight * 0.7;
    
                if ($origWidth > $origHeight) {
                    $newWidth = min($origWidth, $maxWidth);
                    $newHeight = $newWidth * ($origHeight / $origWidth);
                } else {
                    $newHeight = min($origHeight, $maxHeight);
                    $newWidth = $newHeight * ($origWidth / $origHeight);
                }
    
                $xPos = $startX + ($signatureColWidth - $newWidth) / 2;
                $yPos = $startY + ($signatureHeight - $newHeight) / 2;
                $pdf->Image($signaturePath, $xPos, $yPos, $newWidth, $newHeight);
            } else {
                // Afficher un texte si les dimensions de l'image sont nulles
                $pdf->SetXY($startX, $startY + $signatureHeight / 2);
                $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
            }
        } else {
            // Afficher un texte si l'image n'est pas valide
            $pdf->SetXY($startX, $startY + $signatureHeight / 2);
            $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
        }
    } else {
        // Afficher un texte "Signature" si l'image n'existe pas
        $pdf->SetXY($startX, $startY + $signatureHeight / 2);
        $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
    }

    // SIGNATURE DU FINANCIER
    if ($financeSignatureExists) {
        // Valider que c'est un fichier image valide avant de tenter de l'utiliser
        $validImage = false;
        $signaturePath = $financeSignaturePath;
        
        try {
            // Vérifier si c'est une image valide
            $imageInfo = @getimagesize($signaturePath);
            $validImage = $imageInfo !== false && is_array($imageInfo) && !empty($imageInfo[0]) && !empty($imageInfo[1]);
        } catch (Exception $e) {
            $validImage = false;
        }
        
        if ($validImage) {
            $origWidth = $imageInfo[0];
            $origHeight = $imageInfo[1];
            
            // Éviter la division par zéro
            if ($origWidth > 0 && $origHeight > 0) {
                $maxWidth = $signatureColWidth * 0.6;
                $maxHeight = $signatureHeight * 0.7;
    
                if ($origWidth > $origHeight) {
                    $newWidth = min($origWidth, $maxWidth);
                    $newHeight = $newWidth * ($origHeight / $origWidth);
                } else {
                    $newHeight = min($origHeight, $maxHeight);
                    $newWidth = $newHeight * ($origWidth / $origHeight);
                }
    
                $xPos = $startX + $signatureColWidth + ($signatureColWidth - $newWidth) / 2;
                $yPos = $startY + ($signatureHeight - $newHeight) / 2;
                $pdf->Image($signaturePath, $xPos, $yPos, $newWidth, $newHeight);
            } else {
                // Afficher un texte si les dimensions de l'image sont nulles
                $pdf->SetXY($startX + $signatureColWidth, $startY + $signatureHeight / 2);
                $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
            }
        } else {
            // Afficher un texte si l'image n'est pas valide
            $pdf->SetXY($startX + $signatureColWidth, $startY + $signatureHeight / 2);
            $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
        }
    } else {
        // Afficher un texte "Signature" si l'image n'existe pas
        $pdf->SetXY($startX + $signatureColWidth, $startY + $signatureHeight / 2);
        $pdf->Cell($signatureColWidth, 10, '(Signature)', 0, 0, 'C');
    }

    // Notes de bas de page
    $pdf->Ln(21);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, 'Ce bon de commande validé est généré automatiquement par le système de gestion des achats. Pour toute question, veuillez contacter le service des achats.');

    // Générer un nom de fichier pour la version validée
    $validatedFilename = 'Bon_Commande_Validé_' . $order['order_number'] . '.pdf';

    // Vider toute sortie mise en mémoire tampon avant d'envoyer les en-têtes
    if (ob_get_length()) {
        ob_clean();
    }

    // Définir les en-têtes pour le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $validatedFilename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Transfer-Encoding: binary');

    // Générer le PDF et le proposer en téléchargement
    $pdf->Output($validatedFilename, 'I');
    exit;

} catch (Exception $e) {
    // En cas d'erreur, afficher un message d'erreur formaté
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
            pre { background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto; font-size: 12px; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .back-link:hover { background-color: #0056b3; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Erreur lors du téléchargement du bon de commande validé</h1>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p>Informations de débogage:</p>
            <pre>' .
        'Mode de paiement trouvé: ' . (isset($paymentMode) ? htmlspecialchars($paymentMode) : 'Non trouvé') . "\n" .
        'Achat signature: ' . (isset($achatSignaturePath) ? htmlspecialchars($achatSignaturePath) : 'Non défini') . "\n" .
        'Finance signature: ' . (isset($financeSignaturePath) ? htmlspecialchars($financeSignaturePath) : 'Non défini')
        . '</pre>
            <a href="../commandes_archive.php" class="back-link">Retour aux archives</a>
        </div>
    </body>
    </html>';
    exit;
}
?>
<?php
session_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// S'assurer qu'aucune sortie n'a été envoyée avant (important pour les en-têtes)
ob_clean();
ob_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Vérifier si l'ID de l'expression est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: achats_materiaux.php");
    exit();
}

// Récupérer le paramètre de téléchargement
$forceDownload = isset($_GET['download']) && $_GET['download'] == 1;

// Supprimer le cookie de suivi de téléchargement
$expressionId = $_GET['id'] ?? '';
if (!empty($expressionId)) {
    $cookieName = 'pdf_downloaded_' . $expressionId;
    setcookie($cookieName, '', time() - 3600, '/'); // Expire dans le passé = supprimé
}

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupérer les IDs d'expression supplémentaires si cet achat concerne plusieurs projets
    $additionalExpressionIds = [];
    $selectedMaterialIds = []; // Tableau pour stocker les IDs des matériaux sélectionnés

    // Vérifier si c'est un achat groupé avec des matériaux spécifiques sélectionnés
    if (isset($_SESSION['bulk_purchase_expressions']) && is_array($_SESSION['bulk_purchase_expressions'])) {
        $additionalExpressionIds = $_SESSION['bulk_purchase_expressions'];

        // Récupérer les IDs des matériaux sélectionnés s'ils existent dans la session
        if (isset($_SESSION['selected_material_ids']) && is_array($_SESSION['selected_material_ids'])) {
            $selectedMaterialIds = $_SESSION['selected_material_ids'];
            // Nettoyage de la session
            unset($_SESSION['selected_material_ids']);
        }

        // Récupérer les sources des matériaux si disponibles dans la session
        $selectedMaterialSources = [];
        if (isset($_SESSION['selected_material_sources']) && is_array($_SESSION['selected_material_sources'])) {
            $selectedMaterialSources = $_SESSION['selected_material_sources'];
            // Nettoyage de la session
            unset($_SESSION['selected_material_sources']);
        }

        // Effacer cette information de session après utilisation
        unset($_SESSION['bulk_purchase_expressions']);
    } else {
        // Si pas d'achat groupé, on n'a que l'ID actuel
        $additionalExpressionIds = [$expressionId];

        // Pour un achat individuel, vérifier si des matériaux spécifiques ont été sélectionnés
        if (isset($_SESSION['selected_material_ids']) && is_array($_SESSION['selected_material_ids'])) {
            $selectedMaterialIds = $_SESSION['selected_material_ids'];
            unset($_SESSION['selected_material_ids']);
        }

        // Récupérer les sources des matériaux si disponibles
        $selectedMaterialSources = [];
        if (isset($_SESSION['selected_material_sources']) && is_array($_SESSION['selected_material_sources'])) {
            $selectedMaterialSources = $_SESSION['selected_material_sources'];
            unset($_SESSION['selected_material_sources']);
        }
    }

    // Dédupliquer les IDs
    $expressionIds = array_unique($additionalExpressionIds);

    // Préparer les placeholders pour la requête SQL
    $placeholders = implode(',', array_fill(0, count($expressionIds), '?'));

    // Utiliser des paramètres positionnels partout
    $projectsQuery = "SELECT DISTINCT ip.code_projet, ip.nom_client, ip.description_projet, ip.chefprojet,
            u.name as acheteur_name,
            u.signature as acheteur_signature,
            chef.signature as chef_projet_signature,
            ip.idExpression
            FROM identification_projet ip
            LEFT JOIN users_exp u ON u.id = ?
            LEFT JOIN users_exp chef ON chef.name = ip.chefprojet
            WHERE ip.idExpression IN ($placeholders)
            GROUP BY ip.code_projet, ip.nom_client";

    $projectsStmt = $pdo->prepare($projectsQuery);

    // Lier l'ID de l'utilisateur en premier (position 1)
    $projectsStmt->bindValue(1, $_SESSION['user_id']);

    // Lier tous les IDs d'expression ensuite (positions 2, 3, etc.)
    foreach ($expressionIds as $index => $id) {
        $projectsStmt->bindValue($index + 2, $id);
    }

    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun projet n'a été trouvé pour une expression de besoin système, il faut créer un "projet virtuel"
    if (empty($projects)) {
        // Vérifions si l'ID correspond à un besoin système
        $checkBesoinQuery = "SELECT b.idBesoin, d.service_demandeur, d.client 
                           FROM besoins b 
                           LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin 
                           WHERE b.idBesoin = ? LIMIT 1";
        $checkBesoinStmt = $pdo->prepare($checkBesoinQuery);
        $checkBesoinStmt->bindValue(1, $expressionIds[0]);
        $checkBesoinStmt->execute();
        $besoinInfo = $checkBesoinStmt->fetch(PDO::FETCH_ASSOC);

        if ($besoinInfo) {
            // Créer un "projet virtuel" pour les besoins système
            $projects = [
                [
                    'code_projet' => 'SYS-' . ($besoinInfo['service_demandeur'] ?? 'SYSTÈME'),
                    'nom_client' => $besoinInfo['client'] ?? 'Demande interne',
                    'description_projet' => 'Demande de matériaux système',
                    'chefprojet' => 'Administration',
                    'acheteur_name' => '',
                    'acheteur_signature' => '',
                    'chef_projet_signature' => '',
                    'idExpression' => $besoinInfo['idBesoin']
                ]
            ];

            // Récupérer l'acheteur
            $acheteurQuery = "SELECT u.name, u.signature FROM users_exp u WHERE u.id = ?";
            $acheteurStmt = $pdo->prepare($acheteurQuery);
            $acheteurStmt->bindValue(1, $_SESSION['user_id']);
            $acheteurStmt->execute();
            $acheteur = $acheteurStmt->fetch(PDO::FETCH_ASSOC);

            if ($acheteur) {
                $projects[0]['acheteur_name'] = $acheteur['name'];
                $projects[0]['acheteur_signature'] = $acheteur['signature'];
            }
        } else {
            throw new Exception("Aucune information de projet ou de besoin trouvée pour les commandes sélectionnées.");
        }
    }

    // ===== RÉCUPÉRATION DU FOURNISSEUR ET PRIX =====
    $fournisseurCommande = '';
    $materialPrices = [];

    if (isset($_SESSION['temp_fournisseur'])) {
        $fournisseurCommande = $_SESSION['temp_fournisseur'];
        unset($_SESSION['temp_fournisseur']);
    }

    if (isset($_SESSION['temp_material_prices']) && is_array($_SESSION['temp_material_prices'])) {
        $materialPrices = $_SESSION['temp_material_prices'];
        unset($_SESSION['temp_material_prices']);
    }

    // ===== RÉCUPÉRATION DU MODE DE PAIEMENT =====
    $paymentMethod = '';
    $paymentMethodLabel = '';

    // Récupérer depuis la session d'abord
    if (isset($_SESSION['temp_payment_method'])) {
        $paymentMethod = $_SESSION['temp_payment_method'];
        unset($_SESSION['temp_payment_method']);
    }

    if (isset($_SESSION['temp_payment_method_label'])) {
        $paymentMethodLabel = $_SESSION['temp_payment_method_label'];
        unset($_SESSION['temp_payment_method_label']);
    }

    // Si pas disponible en session, récupérer depuis la base de données
    if (empty($paymentMethod) && !empty($materialPrices)) {
        try {
            // Récupérer le mode de paiement depuis la première commande trouvée
            $firstMaterialId = array_keys($materialPrices)[0];

            $paymentQuery = "SELECT am.mode_paiement_id, pm.label as payment_label, pm.description, pm.icon_path
                FROM achats_materiaux am
                LEFT JOIN payment_methods pm ON am.mode_paiement_id = pm.id
                WHERE am.id = ? OR am.expression_id = ?
                ORDER BY am.date_achat DESC LIMIT 1";

            $paymentStmt = $pdo->prepare($paymentQuery);
            $paymentStmt->bindValue(1, $firstMaterialId);
            $paymentStmt->bindValue(2, $expressionIds[0]);
            $paymentStmt->execute();

            $paymentInfo = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentInfo) {
                $paymentMethod = $paymentInfo['mode_paiement_id'] ?? '';
                $paymentMethodLabel = $paymentInfo['payment_label'] ?? 'Non spécifié';

                // NOUVEAU : Informations supplémentaires disponibles
                $paymentMethodDescription = $paymentInfo['description'] ?? '';
            }
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération du mode de paiement: " . $e->getMessage());
        }
    }

    // Valeur par défaut si aucun mode de paiement trouvé
    if (empty($paymentMethodLabel)) {
        $paymentMethodLabel = 'Non spécifié';
    }

    // Construire la requête pour récupérer les matériaux en fonction de la sélection
    $rawMaterials = [];

    if (!empty($selectedMaterialIds)) {
        // Traiter séparément les matériaux de expression_dym et de besoins
        $expressionMaterialIds = [];
        $besoinMaterialIds = [];

        foreach ($selectedMaterialIds as $materialId) {
            $source = $selectedMaterialSources[$materialId] ?? 'expression_dym';
            if ($source === 'besoins') {
                $besoinMaterialIds[] = $materialId;
            } else {
                $expressionMaterialIds[] = $materialId;
            }
        }

        // Récupérer les matériaux de expression_dym
        if (!empty($expressionMaterialIds)) {
            $materialPlaceholders = implode(',', array_fill(0, count($expressionMaterialIds), '?'));
            $materialsExpressionQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.prix_unitaire, ed.fournisseur, 
                              ip.idExpression, ip.code_projet, ip.nom_client
                              FROM expression_dym ed
                              INNER JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                              WHERE ed.id IN ($materialPlaceholders)
                              ORDER BY ed.designation";

            $materialsExpressionStmt = $pdo->prepare($materialsExpressionQuery);
            foreach ($expressionMaterialIds as $index => $id) {
                $materialsExpressionStmt->bindValue($index + 1, $id);
            }
            $materialsExpressionStmt->execute();
            $expressionMaterials = $materialsExpressionStmt->fetchAll(PDO::FETCH_ASSOC);
            $rawMaterials = array_merge($rawMaterials, $expressionMaterials);
        }

        // Récupérer les matériaux de besoins
        if (!empty($besoinMaterialIds)) {
            $besoinPlaceholders = implode(',', array_fill(0, count($besoinMaterialIds), '?'));
            $materialsBesoinsQuery = "SELECT b.id, b.designation_article as designation, b.qt_acheter, b.caracteristique as unit, 
                                      '' as prix_unitaire, '' as fournisseur, 
                                      b.idBesoin as idExpression, 
                                      CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet, 
                                      COALESCE(d.client, 'Demande interne') as nom_client
                                      FROM besoins b
                                      LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                                      WHERE b.id IN ($besoinPlaceholders)
                                      ORDER BY b.designation_article";

            $materialsBesoinsStmt = $pdo->prepare($materialsBesoinsQuery);
            foreach ($besoinMaterialIds as $index => $id) {
                $materialsBesoinsStmt->bindValue($index + 1, $id);
            }
            $materialsBesoinsStmt->execute();
            $besoinMaterials = $materialsBesoinsStmt->fetchAll(PDO::FETCH_ASSOC);
            $rawMaterials = array_merge($rawMaterials, $besoinMaterials);
        }
    } else {
        // Si aucun matériau spécifique n'est sélectionné, utiliser les IDs d'expression
        // Combiner les résultats de expression_dym et besoins

        // Requête pour expression_dym
        $materialsExpressionQuery = "SELECT ed.id, ed.designation, ed.qt_acheter, ed.unit, ed.prix_unitaire, ed.fournisseur, 
                          ip.idExpression, ip.code_projet, ip.nom_client
                          FROM expression_dym ed
                          INNER JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                          WHERE ed.idExpression IN ($placeholders)
                          AND ed.qt_acheter > 0
                          ORDER BY ed.designation";

        $materialsExpressionStmt = $pdo->prepare($materialsExpressionQuery);
        foreach ($expressionIds as $index => $id) {
            $materialsExpressionStmt->bindValue($index + 1, $id);
        }
        $materialsExpressionStmt->execute();
        $expressionMaterials = $materialsExpressionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Requête pour besoins
        $materialsBesoinsQuery = "SELECT b.id, b.designation_article as designation, b.qt_acheter, b.caracteristique as unit, 
                               '' as prix_unitaire, '' as fournisseur, 
                               b.idBesoin as idExpression, 
                               CONCAT('SYS-', COALESCE(d.service_demandeur, 'Système')) as code_projet, 
                               COALESCE(d.client, 'Demande interne') as nom_client
                               FROM besoins b
                               LEFT JOIN demandeur d ON b.idBesoin = d.idBesoin
                               WHERE b.idBesoin IN ($placeholders)
                               AND b.qt_acheter > 0
                               ORDER BY b.designation_article";

        $materialsBesoinsStmt = $pdo->prepare($materialsBesoinsQuery);
        foreach ($expressionIds as $index => $id) {
            $materialsBesoinsStmt->bindValue($index + 1, $id);
        }
        $materialsBesoinsStmt->execute();
        $besoinMaterials = $materialsBesoinsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Combiner les résultats
        $rawMaterials = array_merge($expressionMaterials, $besoinMaterials);
    }

    if (empty($rawMaterials)) {
        throw new Exception("Aucun matériau trouvé pour ces projets.");
    }

    // === CONSOLIDATION DES MATÉRIAUX ET APPLICATION DES PRIX TEMPORAIRES ===
    $consolidatedMaterials = [];

    foreach ($rawMaterials as $material) {
        $materialId = $material['id'];
        $designation = trim($material['designation']);
        $unit = trim($material['unit']);

        // Utiliser le prix stocké temporairement ou celui de la BD
        $prix_unitaire = isset($materialPrices[$materialId]) ? $materialPrices[$materialId] : $material['prix_unitaire'];

        // Clé unique pour le matériau (basée uniquement sur désignation et unité)
        $materialKey = md5($designation . '|' . $unit);

        // MODIFICATION: Vérifier si une quantité spécifique a été demandée pour cette commande partielle
        $commandeQuantity = null;
        if (isset($_SESSION['commande_partielle_quantities'][$materialId])) {
            $commandeQuantity = floatval($_SESSION['commande_partielle_quantities'][$materialId]);
        }

        // Si on a une quantité spécifique pour cette commande, l'utiliser au lieu de la quantité totale
        $quantiteAffichee = $commandeQuantity !== null ? $commandeQuantity : floatval($material['qt_acheter']);

        // Si ce matériau n'a pas encore été traité, initialiser son entrée
        if (!isset($consolidatedMaterials[$materialKey])) {
            $consolidatedMaterials[$materialKey] = [
                'designation' => $designation,
                'unit' => $unit,
                'prix_unitaire' => $prix_unitaire,
                'qt_acheter' => $quantiteAffichee,
                'montant_total' => $quantiteAffichee * floatval($prix_unitaire)
            ];
        } else {
            // Sinon, incrémenter la quantité
            $consolidatedMaterials[$materialKey]['qt_acheter'] += $quantiteAffichee;

            // Recalculer le montant total basé sur le prix unitaire et la nouvelle quantité
            $consolidatedMaterials[$materialKey]['montant_total'] =
                $consolidatedMaterials[$materialKey]['qt_acheter'] * floatval($consolidatedMaterials[$materialKey]['prix_unitaire']);
        }
    }

    // Après avoir traité tous les matériaux, nettoyer la session
    if (isset($_SESSION['commande_partielle_quantities'])) {
        unset($_SESSION['commande_partielle_quantities']);
    }

    // Calculer le montant total consolidé
    $totalAmount = 0;
    foreach ($consolidatedMaterials as $material) {
        $totalAmount += $material['montant_total'];
    }

    // Générer un numéro de bon de commande unique avec timestamp pour assurer l'unicité
    $timestamp = date('YmdHis');
    $bonCommandeNumber = 'BC-' . date('Ymd') . '-' . substr($expressionId, -5) . '-' . $timestamp;
    if (count($expressionIds) > 1) {
        // Si plusieurs projets, ajouter un indicateur "MULTI" dans le numéro
        $bonCommandeNumber = 'BC-MULTI-' . date('Ymd') . '-' . substr(md5(implode('-', $expressionIds)), 0, 5) . '-' . $timestamp;
    }

    // Date du jour
    $currentDate = date('d/m/Y');

    // Vérifier si TCPDF est disponible
    if (!file_exists('tcpdf/tcpdf.php')) {
        throw new Exception("La bibliothèque TCPDF n'est pas disponible dans le chemin spécifié.");
    }

    // Utilisation de la bibliothèque TCPDF pour générer le PDF
    require_once('tcpdf/tcpdf.php');

    // Créer une classe personnalisée pour le PDF avec une méthode pour dessiner des cellules à hauteur uniforme
    class PDF extends TCPDF
    {
        protected $bonCommandeNumber;
        protected $currentDate;
        protected $fournisseurCommande;

        // Constructeur personnalisé
        function __construct($bonCommandeNumber, $currentDate, $fournisseurCommande)
        {
            // Orientation P (portrait), unité mm, format A4
            parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

            $this->bonCommandeNumber = $bonCommandeNumber;
            $this->currentDate = $currentDate;
            $this->fournisseurCommande = $fournisseurCommande;

            // Définir les informations du document
            $this->SetCreator(PDF_CREATOR);
            $this->SetAuthor('Service Achats');
            $this->SetTitle('Bon de Commande ' . $bonCommandeNumber);
            $this->SetSubject('Bon de Commande');

            // Définir les marges pour garantir que tout reste dans les limites de la page
            $this->SetMargins(15, 15, 15); // Marges gauche, haut, droite
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

        // En-tête personnalisé
        public function Header()
        {
            // En-tête est désactivé et géré manuellement
        }

        // Pied de page personnalisé
        public function Footer()
        {
            // Se positionner à 10mm du bas de la page
            $this->SetY(-10);
            // Police
            $this->SetFont('helvetica', 'I', 8);
            // Numéro de page
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
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

    // Créer une nouvelle instance de PDF
    $pdf = new PDF($bonCommandeNumber, $currentDate, $fournisseurCommande);

    // Définir la police
    $pdf->SetFont('helvetica', '', 10);

    // Ajouter une page
    $pdf->AddPage();

    // Logo et informations de l'entreprise
    $logoPath = '../public/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 10, 30);
    }

    // Titre et informations du bon de commande
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'BON DE COMMANDE', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'N° ' . $bonCommandeNumber, 0, 1, 'R');
    $pdf->Cell(0, 5, 'Date: ' . $currentDate, 0, 1, 'R');

    // Espace
    $pdf->Ln(10);

    // ===== INFORMATIONS FOURNISSEUR ET MODE DE PAIEMENT =====
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMATIONS FOURNISSEUR ET PAIEMENT', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Tableau avec fournisseur et mode de paiement
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(60, 7, 'Fournisseur :', 1, 0, 'L', true);
    $pdf->Cell(120, 7, $fournisseurCommande, 1, 1, 'L');
    $pdf->Cell(60, 7, 'Mode de paiement :', 1, 0, 'L', true);
    $pdf->Cell(120, 7, $paymentMethodLabel, 1, 1, 'L');

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

    // === SECTION 2: TABLEAU CONSOLIDÉ DES MATÉRIAUX ===
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

    foreach ($consolidatedMaterials as $material) {
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

    // === SECTION 3: SIGNATURES (TOUJOURS EN BAS DE PAGE) ===

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
    $firstProject = $projects[0];
    $pdf->Row(
        [
            'Service achat: ' . $firstProject['acheteur_name'],
            'Service finance: ' //. $firstProject['chefprojet']
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
    if (!empty($firstProject['acheteur_signature'])) {
        $signaturePath = '../uploads/' . $firstProject['acheteur_signature'];
        if (file_exists($signaturePath)) {
            list($origWidth, $origHeight) = getimagesize($signaturePath);
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
        }
    }

    // Notes de bas de page
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 2, 'Ce bon de commande est généré automatiquement par le système de gestion des achats. Pour toute question, veuillez contacter le service des achats.');

    // =====================================================
    // DÉBUT DE LA MODIFICATION POUR SAUVEGARDER LE PDF
    // =====================================================

    // Créer le répertoire de stockage directement dans le dossier désigné
    $storageDir = __DIR__ . '/gestion-bon-commande/purchase_orders';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    // Générer un nom de fichier unique pour le stockage avec un identifiant unique
    $uniqueId = uniqid('', true);
    $saveFilename = 'BC_' . $bonCommandeNumber . '_' . $uniqueId . '.pdf';
    $savePath = $storageDir . '/' . $saveFilename;

    // Chemin relatif pour la base de données - Corriger le chemin pour éviter la duplication
    // On utilise un chemin relatif qui commence directement à "purchase_orders"
    $relativePathForDB = 'purchase_orders/' . $saveFilename;

    // Sauvegarder une copie du PDF dans le système de fichiers
    $pdf->Output($savePath, 'F');

    // Enregistrer l'information du bon de commande dans la base de données
    $isMultiProject = count($expressionIds) > 1 ? 1 : 0;
    $relatedExpressions = json_encode($expressionIds);

    // Créer un identifiant de référence pour ce téléchargement spécifique
    $downloadReference = 'REF-' . date('YmdHis') . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);

    $saveOrderQuery = "INSERT INTO purchase_orders
                      (order_number, expression_id, related_expressions, file_path,
                       fournisseur, mode_paiement_id, montant_total, user_id, is_multi_project, download_reference, generated_at)
                      VALUES
                      (:order_number, :expression_id, :related_expressions, :file_path,
                       :fournisseur, :mode_paiement_id, :montant_total, :user_id, :is_multi_project, :download_reference, NOW())";

    $saveOrderStmt = $pdo->prepare($saveOrderQuery);
    $saveOrderStmt->bindParam(':order_number', $bonCommandeNumber);
    $saveOrderStmt->bindParam(':expression_id', $expressionId);
    $saveOrderStmt->bindParam(':related_expressions', $relatedExpressions);
    $saveOrderStmt->bindParam(':file_path', $relativePathForDB);
    $saveOrderStmt->bindParam(':fournisseur', $fournisseurCommande);
    $saveOrderStmt->bindParam(':mode_paiement_id', $paymentMethod);
    $saveOrderStmt->bindParam(':montant_total', $totalAmount);
    $saveOrderStmt->bindParam(':user_id', $_SESSION['user_id']);
    $saveOrderStmt->bindParam(':is_multi_project', $isMultiProject);
    $saveOrderStmt->bindParam(':download_reference', $downloadReference);

    try {
        $saveOrderStmt->execute();

        // Récupérer l'ID du bon de commande nouvellement créé
        $bonCommandeId = $pdo->lastInsertId();

        // Associer cet ID aux pro-formas éventuels créés lors de la complétion
        if (isset($_SESSION['bulk_purchase_orders']) && is_array($_SESSION['bulk_purchase_orders'])) {
            $orderIds = array_column($_SESSION['bulk_purchase_orders'], 'order_id');
            $orderIds = array_filter($orderIds);
            if (!empty($orderIds)) {
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

                // Mise à jour de la table des pro-formas
                $updateProformaQuery = "UPDATE proformas SET bon_commande_id = ? WHERE achat_materiau_id IN ($placeholders)";
                $updateProformaStmt = $pdo->prepare($updateProformaQuery);
                $updateProformaStmt->execute(array_merge([$bonCommandeId], $orderIds));

                // Vérifier si la colonne bon_commande_id existe dans achats_materiaux
                $columnCheck = $pdo->query("SHOW COLUMNS FROM achats_materiaux LIKE 'bon_commande_id'");
                if ($columnCheck && $columnCheck->rowCount() > 0) {
                    $updateAchatQuery = "UPDATE achats_materiaux SET bon_commande_id = ? WHERE id IN ($placeholders)";
                    $updateAchatStmt = $pdo->prepare($updateAchatQuery);
                    $updateAchatStmt->execute(array_merge([$bonCommandeId], $orderIds));
                }
            }
            unset($_SESSION['bulk_purchase_orders']);
        }

        // Enregistrer un log du système
        if (function_exists('logSystemEvent')) {
            $logDetails = [
                'order_number' => $bonCommandeNumber,
                'expression_id' => $expressionId,
                'file_path' => $savePath,
                'total_amount' => $totalAmount,
                'is_multi_project' => $isMultiProject,
                'payment_method' => $paymentMethod
            ];

            logSystemEvent(
                $pdo,
                $_SESSION['user_id'],
                'generate_bon_commande',
                'purchase_orders',
                $bonCommandeId,
                json_encode($logDetails)
            );
        }
    } catch (PDOException $e) {
        // On capture l'erreur mais on ne l'affiche pas pour ne pas interrompre le téléchargement
        // L'enregistrement dans la base a échoué mais le PDF est toujours généré
        error_log("Erreur lors de l'enregistrement du bon de commande dans la base: " . $e->getMessage());
    }

    // =====================================================
    // FIN DE LA MODIFICATION POUR SAUVEGARDER LE PDF
    // =====================================================

    // Configuration de la sortie du PDF pour l'utilisateur
    $pdf_filename = 'bon_commande_multi_projets_' . date('Ymd') . '.pdf';
    if (count($expressionIds) === 1) {
        $pdf_filename = 'bon_commande_' . $expressionId . '.pdf';
    }

    // Vider toute sortie mise en mémoire tampon avant d'envoyer les en-têtes
    if (ob_get_length()) {
        ob_clean();
    }

    // Ajouter des en-têtes HTTP pour forcer le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdf_filename . '"');
    header('Cache-Control: max-age=0');
    // Prévenir le caching du PDF
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Transfer-Encoding: binary');

    // Forcer le téléchargement ou afficher dans le navigateur selon le paramètre
    if ($forceDownload) {
        $pdf->Output($pdf_filename, 'D'); // 'D' force le téléchargement
    } else {
        $pdf->Output($pdf_filename, 'I'); // 'I' affiche dans le navigateur
    }

    // Terminer le script pour éviter tout contenu supplémentaire
    exit;
} catch (Exception $e) {
    // En cas d'erreur, nettoyer la sortie mise en mémoire tampon
    if (ob_get_length()) {
        ob_clean();
    }

    // Journaliser l'erreur
    error_log("Erreur dans generate_bon_commande.php: " . $e->getMessage());

    // Afficher un message d'erreur formaté en HTML
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
   <html>
   <head>
       <title>Erreur - Génération du Bon de Commande</title>
       <style>
           body { font-family: Arial, sans-serif; margin: 20px; }
           .error-container { border: 1px solid #f5c6cb; padding: 20px; background-color: #f8d7da; border-radius: 5px; }
           h1 { color: #721c24; }
           p { margin-bottom: 15px; }
           .back-link { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
           .back-link:hover { background-color: #0056b3; }
           .error-details { margin-top: 20px; padding: 10px; background-color: #f8f9fa; border-left: 3px solid #721c24; }
       </style>
   </head>
   <body>
       <div class='error-container'>
           <h1>Erreur lors de la génération du bon de commande</h1>
           <p>" . htmlspecialchars($e->getMessage()) . "</p>
           <div class='error-details'>
               <p><strong>Informations de débogage :</strong></p>
               <p>Fichier : " . htmlspecialchars($e->getFile()) . "</p>
               <p>Ligne : " . $e->getLine() . "</p>
           </div>
           <a href='achats_materiaux.php' class='back-link'>Retour à la liste des matériaux</a>
       </div>
   </body>
   </html>";
    exit();
}
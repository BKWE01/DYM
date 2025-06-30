<?php
// Fichier: /DYM MANUFACTURE/expressions_besoins/User-BE/api_expression/update_expression.php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Non autorisé. Veuillez vous connecter.']);
  exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';

// Vérifier si l'utilisateur est un super_admin
try {
  $user_id = $_SESSION['user_id'];
  $stmt = $pdo->prepare("SELECT role FROM users_exp WHERE id = :user_id");
  $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
  $stmt->execute();
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$userData || $userData['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Permission refusée. Seul un super administrateur peut effectuer cette action.']);
    exit();
  }
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
  exit();
}

// Lire les données JSON du corps de la requête
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
  echo json_encode(['success' => false, 'message' => 'Données invalides.']);
  exit();
}

// Vérifier les données requises
if (!isset($data['expressionId']) || !isset($data['projectInfo']) || !isset($data['expressions'])) {
  echo json_encode(['success' => false, 'message' => 'Données incomplètes.']);
  exit();
}

$expressionId = $data['expressionId'];
$projectInfo = $data['projectInfo'];
$expressions = $data['expressions'];
$deletedRows = $data['deletedRows'] ?? [];

// Fonction pour valider et formater les nombres décimaux
function validateDecimal($value)
{
  $number = floatval($value);
  if ($number < 0) {
    return 0;
  }
  // Limiter à 3 décimales pour éviter les problèmes de précision
  return round($number, 3);
}

// Fonction pour formater les décimaux pour la base de données
function formatForDatabase($value)
{
  $validated = validateDecimal($value);
  return number_format($validated, 3, '.', '');
}

// Array pour suivre les produits qui nécessitent des achats supplémentaires
$itemsNeedingPurchase = [];

try {
  // Démarrer une transaction
  $pdo->beginTransaction();

  // 1. Mettre à jour les informations du projet
  $stmt = $pdo->prepare("
        UPDATE identification_projet
        SET code_projet = :code_projet,
            nom_client = :nom_client,
            description_projet = :description_projet,
            sitgeo = :sitgeo,
            chefprojet = :chefprojet
        WHERE idExpression = :idExpression
    ");
  $stmt->bindParam(':code_projet', $projectInfo['code_projet'], PDO::PARAM_STR);
  $stmt->bindParam(':nom_client', $projectInfo['nom_client'], PDO::PARAM_STR);
  $stmt->bindParam(':description_projet', $projectInfo['description_projet'], PDO::PARAM_STR);
  $stmt->bindParam(':sitgeo', $projectInfo['sitgeo'], PDO::PARAM_STR);
  $stmt->bindParam(':chefprojet', $projectInfo['chefprojet'], PDO::PARAM_STR);
  $stmt->bindParam(':idExpression', $expressionId, PDO::PARAM_STR);
  $stmt->execute();

  // 2. Supprimer les expressions supprimées
  foreach ($deletedRows as $rowId) {
    // Récupérer les infos de la ligne à supprimer pour ajuster les quantités réservées
    $stmt = $pdo->prepare("
            SELECT designation, quantity, quantity_reserved 
            FROM expression_dym 
            WHERE id = :id
        ");
    $stmt->bindParam(':id', $rowId, PDO::PARAM_INT);
    $stmt->execute();
    $rowInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rowInfo) {
      // Ajuster la quantité réservée dans la table products (avec support décimal)
      $quantityToRelease = validateDecimal($rowInfo['quantity']);
      $stmt = $pdo->prepare("
                UPDATE products 
                SET quantity_reserved = GREATEST(0, COALESCE(quantity_reserved, 0) - :quantity) 
                WHERE product_name = :designation
            ");
      $stmt->bindParam(':quantity', $quantityToRelease, PDO::PARAM_STR);
      $stmt->bindParam(':designation', $rowInfo['designation'], PDO::PARAM_STR);
      $stmt->execute();

      // Supprimer la ligne
      $stmt = $pdo->prepare("DELETE FROM expression_dym WHERE id = :id");
      $stmt->bindParam(':id', $rowId, PDO::PARAM_INT);
      $stmt->execute();
    }
  }

  // 3. Mettre à jour ou ajouter des expressions
  foreach ($expressions as $expr) {
    // Valider et formater la quantité
    $validatedQuantity = validateDecimal($expr['quantity']);

    if ($validatedQuantity <= 0) {
      throw new Exception("Quantité invalide pour le produit: " . $expr['designation']);
    }

    // Vérifier le stock disponible et calculer qt_acheter et qt_stock
    $stockQuery = $pdo->prepare("
            SELECT p.quantity, p.quantity_reserved, p.unit_price, p.unit, c.libelle as type
            FROM products p 
            LEFT JOIN categories c ON p.category = c.id
            WHERE p.product_name = :designation
        ");
    $stockQuery->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
    $stockQuery->execute();
    $stockInfo = $stockQuery->fetch(PDO::FETCH_ASSOC);

    $qt_acheter = null;  // Par défaut, aucun achat supplémentaire n'est nécessaire
    $qt_stock = null;    // Par défaut, aucune quantité en stock n'est disponible
    $prix_unitaire = null;
    $montant = null;
    $unit = $expr['unit'];
    $type = $expr['type'];

    if ($stockInfo) {
      // Mettre à jour unit et type avec les valeurs de la base de données si disponibles
      $unit = $stockInfo['unit'] ?: $expr['unit'];
      $type = $stockInfo['type'] ?: $expr['type'];

      // Récupérer le prix unitaire
      $prix_unitaire = validateDecimal($stockInfo['unit_price']);

      // Calculer le montant total avec support décimal
      $montant = $prix_unitaire * $validatedQuantity;
      $montant = round($montant, 2); // Arrondir à 2 décimales pour les montants

      $quantityInStock = validateDecimal($stockInfo['quantity']);
      $quantityReserved = validateDecimal($stockInfo['quantity_reserved']);

      // Vérifier si c'est une ligne existante ou nouvelle
      $isNewRow = strpos($expr['id'], 'new-') === 0;

      // Pour les lignes existantes, ajuster le montant réservé en fonction de la quantité originale
      if (!$isNewRow) {
        // Récupérer la quantité actuelle pour cet article dans expression_dym
        $stmtCurrent = $pdo->prepare("
                    SELECT quantity FROM expression_dym WHERE id = :id
                ");
        $stmtCurrent->bindParam(':id', $expr['id'], PDO::PARAM_INT);
        $stmtCurrent->execute();
        $currentInfo = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

        if ($currentInfo) {
          $currentQuantity = validateDecimal($currentInfo['quantity']);
          // Ajuster le quantityReserved en soustrayant la quantité actuelle (car elle sera mise à jour)
          $quantityReserved -= $currentQuantity;
        }
      }

      $availableStock = $quantityInStock - $quantityReserved;

      // Si la quantité demandée dépasse le stock disponible
      if ($validatedQuantity > $availableStock) {
        // La quantité qu'on peut prendre du stock est le maximum entre 0 et le stock disponible
        $qt_stock = max(0, $availableStock);

        // Calculer la quantité à acheter (différence entre la demande et le stock disponible)
        $qt_acheter = $validatedQuantity - $availableStock;

        // Si le stock disponible est négatif, la totalité doit être achetée
        if ($availableStock < 0) {
          $qt_acheter = $validatedQuantity;
          $qt_stock = 0;
        }

        $itemsNeedingPurchase[] = [
          'designation' => $expr['designation'],
          'requested' => $validatedQuantity,
          'available' => max(0, $availableStock),
          'to_purchase' => $qt_acheter,
          'type' => $type,
          'unit' => $unit
        ];
      } else {
        // Si la quantité demandée est inférieure ou égale au stock disponible,
        // toute la quantité sera fournie à partir du stock
        $qt_stock = $validatedQuantity;
      }
    }

    // Vérifier si l'ID commence par "new-" (nouvelle ligne)
    if (strpos($expr['id'], 'new-') === 0) {
      // Nouvelle ligne - insérer
      try {
        // Construire la requête en vérifiant si la colonne "type" existe
        $tableInfoQuery = $pdo->prepare("SHOW COLUMNS FROM expression_dym LIKE 'type'");
        $tableInfoQuery->execute();
        $typeColumnExists = $tableInfoQuery->rowCount() > 0;

        if ($typeColumnExists) {
          $stmt = $pdo->prepare("
                        INSERT INTO expression_dym (
                            idExpression, user_emet, designation, unit, quantity, quantity_reserved, type, 
                            qt_stock, qt_acheter, prix_unitaire, montant, valide_stock, valide_achat
                        ) VALUES (
                            :idExpression, :user_emet, :designation, :unit, :quantity, :quantity, :type, 
                            :qt_stock, :qt_acheter, :prix_unitaire, :montant, 'validé', 'pas validé'
                        )
                    ");
          $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        } else {
          $stmt = $pdo->prepare("
                        INSERT INTO expression_dym (
                            idExpression, user_emet, designation, unit, quantity, quantity_reserved, 
                            qt_stock, qt_acheter, prix_unitaire, montant, valide_stock, valide_achat
                        ) VALUES (
                            :idExpression, :user_emet, :designation, :unit, :quantity, :quantity, 
                            :qt_stock, :qt_acheter, :prix_unitaire, :montant, 'validé', 'pas validé'
                        )
                    ");
        }

        $stmt->bindParam(':idExpression', $expressionId, PDO::PARAM_STR);
        $stmt->bindParam(':user_emet', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
        $stmt->bindParam(':unit', $unit, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
        $stmt->bindValue(':qt_stock', $qt_stock !== null ? formatForDatabase($qt_stock) : null, PDO::PARAM_STR);
        $stmt->bindValue(':qt_acheter', $qt_acheter !== null ? formatForDatabase($qt_acheter) : null, PDO::PARAM_STR);
        $stmt->bindValue(':prix_unitaire', $prix_unitaire !== null ? formatForDatabase($prix_unitaire) : null, PDO::PARAM_STR);
        $stmt->bindValue(':montant', $montant !== null ? formatForDatabase($montant) : null, PDO::PARAM_STR);
        $stmt->execute();
      } catch (PDOException $e) {
        // En cas d'erreur avec la colonne 'type', essayer sans cette colonne
        if (strpos($e->getMessage(), 'Unknown column \'type\'') !== false) {
          $stmt = $pdo->prepare("
                        INSERT INTO expression_dym (
                            idExpression, user_emet, designation, unit, quantity, quantity_reserved, 
                            qt_stock, qt_acheter, prix_unitaire, montant, valide_stock, valide_achat
                        ) VALUES (
                            :idExpression, :user_emet, :designation, :unit, :quantity, :quantity, 
                            :qt_stock, :qt_acheter, :prix_unitaire, :montant, 'validé', 'pas validé'
                        )
                    ");
          $stmt->bindParam(':idExpression', $expressionId, PDO::PARAM_STR);
          $stmt->bindParam(':user_emet', $user_id, PDO::PARAM_INT);
          $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
          $stmt->bindParam(':unit', $unit, PDO::PARAM_STR);
          $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
          $stmt->bindValue(':qt_stock', $qt_stock !== null ? formatForDatabase($qt_stock) : null, PDO::PARAM_STR);
          $stmt->bindValue(':qt_acheter', $qt_acheter !== null ? formatForDatabase($qt_acheter) : null, PDO::PARAM_STR);
          $stmt->bindValue(':prix_unitaire', $prix_unitaire !== null ? formatForDatabase($prix_unitaire) : null, PDO::PARAM_STR);
          $stmt->bindValue(':montant', $montant !== null ? formatForDatabase($montant) : null, PDO::PARAM_STR);
          $stmt->execute();
        } else {
          // Si c'est une autre erreur, la propager
          throw $e;
        }
      }

      // Mettre à jour la quantité réservée dans products (avec support décimal)
      $stmt = $pdo->prepare("
                UPDATE products 
                SET quantity_reserved = COALESCE(quantity_reserved, 0) + :quantity 
                WHERE product_name = :designation
            ");
      $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
      $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
      $stmt->execute();
    } else {
      // Ligne existante - mettre à jour
      // Récupérer les infos actuelles pour comparer
      $stmt = $pdo->prepare("
                SELECT designation, quantity, prix_unitaire 
                FROM expression_dym 
                WHERE id = :id
            ");
      $stmt->bindParam(':id', $expr['id'], PDO::PARAM_INT);
      $stmt->execute();
      $currentExpr = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($currentExpr) {
        $currentQuantity = validateDecimal($currentExpr['quantity']);
        $quantityDiff = $validatedQuantity - $currentQuantity;
        $designationChanged = ($currentExpr['designation'] !== $expr['designation']);

        // Ajuster la quantité réservée dans products si le produit a changé ou la quantité
        if ($designationChanged) {
          // Diminuer la réservation pour l'ancien produit
          $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = GREATEST(0, COALESCE(quantity_reserved, 0) - :quantity) 
                        WHERE product_name = :designation
                    ");
          $stmt->bindValue(':quantity', formatForDatabase($currentQuantity), PDO::PARAM_STR);
          $stmt->bindParam(':designation', $currentExpr['designation'], PDO::PARAM_STR);
          $stmt->execute();

          // Augmenter la réservation pour le nouveau produit
          $stmt = $pdo->prepare("
                        UPDATE products 
                        SET quantity_reserved = COALESCE(quantity_reserved, 0) + :quantity 
                        WHERE product_name = :designation
                    ");
          $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
          $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
          $stmt->execute();
        } elseif (abs($quantityDiff) > 0.001) { // Utilisation d'une tolérance pour les comparaisons décimales
          // La quantité a changé mais pas le produit
          $updateQuantity = abs($quantityDiff);
          $operation = $quantityDiff > 0 ? '+' : '-';

          $query = "
                        UPDATE products 
                        SET quantity_reserved = " . ($operation == '+' ?
            "COALESCE(quantity_reserved, 0) + :updateQuantity" :
            "GREATEST(0, COALESCE(quantity_reserved, 0) - :updateQuantity)") . "
                        WHERE product_name = :designation
                    ";
          $stmt = $pdo->prepare($query);
          $stmt->bindValue(':updateQuantity', formatForDatabase($updateQuantity), PDO::PARAM_STR);
          $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
          $stmt->execute();
        }

        try {
          // Vérifier si la colonne 'type' existe dans la table
          $tableInfoQuery = $pdo->prepare("SHOW COLUMNS FROM expression_dym LIKE 'type'");
          $tableInfoQuery->execute();
          $typeColumnExists = $tableInfoQuery->rowCount() > 0;

          // Mettre à jour l'expression
          if ($typeColumnExists) {
            $stmt = $pdo->prepare("
                            UPDATE expression_dym 
                            SET designation = :designation,
                                unit = :unit,
                                quantity = :quantity,
                                quantity_reserved = :quantity,
                                type = :type,
                                qt_stock = :qt_stock,
                                qt_acheter = :qt_acheter,
                                prix_unitaire = :prix_unitaire,
                                montant = :montant,
                                updated_at = NOW()
                            WHERE id = :id
                        ");
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
          } else {
            $stmt = $pdo->prepare("
                            UPDATE expression_dym 
                            SET designation = :designation,
                                unit = :unit,
                                quantity = :quantity,
                                quantity_reserved = :quantity,
                                qt_stock = :qt_stock,
                                qt_acheter = :qt_acheter,
                                prix_unitaire = :prix_unitaire,
                                montant = :montant,
                                updated_at = NOW()
                            WHERE id = :id
                        ");
          }

          $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
          $stmt->bindParam(':unit', $unit, PDO::PARAM_STR);
          $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
          $stmt->bindValue(':qt_stock', $qt_stock !== null ? formatForDatabase($qt_stock) : null, PDO::PARAM_STR);
          $stmt->bindValue(':qt_acheter', $qt_acheter !== null ? formatForDatabase($qt_acheter) : null, PDO::PARAM_STR);
          $stmt->bindValue(':prix_unitaire', $prix_unitaire !== null ? formatForDatabase($prix_unitaire) : null, PDO::PARAM_STR);
          $stmt->bindValue(':montant', $montant !== null ? formatForDatabase($montant) : null, PDO::PARAM_STR);
          $stmt->bindParam(':id', $expr['id'], PDO::PARAM_INT);
          $stmt->execute();
        } catch (PDOException $e) {
          // En cas d'erreur avec la colonne 'type', essayer sans cette colonne
          if (strpos($e->getMessage(), 'Unknown column \'type\'') !== false) {
            $stmt = $pdo->prepare("
                            UPDATE expression_dym 
                            SET designation = :designation,
                                unit = :unit,
                                quantity = :quantity,
                                quantity_reserved = :quantity,
                                qt_stock = :qt_stock,
                                qt_acheter = :qt_acheter,
                                prix_unitaire = :prix_unitaire,
                                montant = :montant,
                                updated_at = NOW()
                            WHERE id = :id
                        ");
            $stmt->bindParam(':designation', $expr['designation'], PDO::PARAM_STR);
            $stmt->bindParam(':unit', $unit, PDO::PARAM_STR);
            $stmt->bindValue(':quantity', formatForDatabase($validatedQuantity), PDO::PARAM_STR);
            $stmt->bindValue(':qt_stock', $qt_stock !== null ? formatForDatabase($qt_stock) : null, PDO::PARAM_STR);
            $stmt->bindValue(':qt_acheter', $qt_acheter !== null ? formatForDatabase($qt_acheter) : null, PDO::PARAM_STR);
            $stmt->bindValue(':prix_unitaire', $prix_unitaire !== null ? formatForDatabase($prix_unitaire) : null, PDO::PARAM_STR);
            $stmt->bindValue(':montant', $montant !== null ? formatForDatabase($montant) : null, PDO::PARAM_STR);
            $stmt->bindParam(':id', $expr['id'], PDO::PARAM_INT);
            $stmt->execute();
          } else {
            // Si c'est une autre erreur, la propager
            throw $e;
          }
        }
      }
    }
  }

  // 4. Ajouter une entrée dans system_logs
  $logDetails = json_encode([
    'action' => 'update',
    'expression_id' => $expressionId,
    'project_info' => $projectInfo,
    'updated_expressions' => $expressions,
    'deleted_rows' => $deletedRows,
    'items_needing_purchase' => $itemsNeedingPurchase,
    'decimal_support' => true // Indique que cette mise à jour supporte les décimaux
  ]);

  $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, username, action, type, entity_id, entity_name, details, ip_address)
        VALUES (:user_id, :username, 'modification', 'expression', :entity_id, :entity_name, :details, :ip)
    ");

  $username = $_SESSION['username'] ?? 'Unknown';
  $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->bindParam(':entity_id', $expressionId, PDO::PARAM_STR);
  $stmt->bindParam(':entity_name', $projectInfo['code_projet'], PDO::PARAM_STR);
  $stmt->bindParam(':details', $logDetails, PDO::PARAM_STR);
  $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
  $stmt->execute();

  // Valider la transaction
  $pdo->commit();

  // Réponse avec les éventuels produits nécessitant un achat
  echo json_encode([
    'success' => true,
    'message' => 'Expression de besoin mise à jour avec succès.',
    'needsPurchase' => !empty($itemsNeedingPurchase),
    'itemsNeedingPurchase' => $itemsNeedingPurchase,
    'decimalSupport' => true
  ]);
} catch (Exception $e) {
  // Annuler la transaction en cas d'erreur
  $pdo->rollBack();
  echo json_encode([
    'success' => false,
    'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
  ]);
} catch (PDOException $e) {
  // Annuler la transaction en cas d'erreur
  $pdo->rollBack();
  echo json_encode([
    'success' => false,
    'message' => 'Erreur de base de données lors de la mise à jour: ' . $e->getMessage()
  ]);
}
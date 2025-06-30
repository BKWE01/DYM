<?php
header('Content-Type: application/json');

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupérer tous les codes de projet et noms de client
    $query = $pdo->query("SELECT code_projet, nom_client FROM identification_projet ORDER BY created_at DESC");
    $projects = $query->fetchAll(PDO::FETCH_ASSOC);

    // Créer un tableau des codes de projet et noms de client uniques pour l'autocomplétion
    $uniqueCodes = [];
    $uniqueClients = [];

    // Créer un tableau de correspondance entre codes et clients
    $codeToClient = [];
    $clientToCode = [];

    foreach ($projects as $project) {
        if (!empty($project['code_projet']) && !empty($project['nom_client'])) {
            $code = $project['code_projet'];
            $client = $project['nom_client'];

            if (!in_array($code, $uniqueCodes)) {
                $uniqueCodes[] = $code;
            }

            if (!in_array($client, $uniqueClients)) {
                $uniqueClients[] = $client;
            }

            // Stocker les correspondances
            if (!isset($codeToClient[$code])) {
                $codeToClient[$code] = $client;
            }

            if (!isset($clientToCode[$client])) {
                $clientToCode[$client] = $code;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'projectCodes' => $uniqueCodes,
        'clientNames' => $uniqueClients,
        'codeToClient' => $codeToClient,
        'clientToCode' => $clientToCode
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
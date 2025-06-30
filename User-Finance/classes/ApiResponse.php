<?php
/**
 * ApiResponse.php
 * Classe standardisée pour les réponses API
 * 
 * @author DYM MANUFACTURE
 * @version 2.0
 */

class ApiResponse 
{
    private $success;
    private $message;
    private $data;
    private $meta;
    private $errors;
    
    /**
     * Constructeur
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param array $meta
     * @param array $errors
     */
    public function __construct(
        bool $success, 
        string $message = '', 
        $data = null, 
        array $meta = [], 
        array $errors = []
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->meta = $meta;
        $this->errors = $errors;
    }
    
    /**
     * Crée une réponse de succès
     * @param mixed $data
     * @param string $message
     * @param array $meta
     * @return ApiResponse
     */
    public static function success($data = null, string $message = 'Opération réussie', array $meta = []): self
    {
        return new self(true, $message, $data, $meta);
    }
    
    /**
     * Crée une réponse d'erreur
     * @param string $message
     * @param array $errors
     * @param mixed $data
     * @return ApiResponse
     */
    public static function error(string $message = 'Une erreur s\'est produite', array $errors = [], $data = null): self
    {
        return new self(false, $message, $data, [], $errors);
    }
    
    /**
     * Crée une réponse pour des données non trouvées
     * @param string $message
     * @return ApiResponse
     */
    public static function notFound(string $message = 'Ressource non trouvée'): self
    {
        return new self(false, $message, null, [], ['code' => 404]);
    }
    
    /**
     * Crée une réponse pour un accès non autorisé
     * @param string $message
     * @return ApiResponse
     */
    public static function unauthorized(string $message = 'Accès non autorisé'): self
    {
        return new self(false, $message, null, [], ['code' => 401]);
    }
    
    /**
     * Crée une réponse pour des données invalides
     * @param array $validationErrors
     * @param string $message
     * @return ApiResponse
     */
    public static function validationError(array $validationErrors, string $message = 'Données invalides'): self
    {
        return new self(false, $message, null, [], array_merge(['code' => 422], $validationErrors));
    }
    
    /**
     * Ajoute des métadonnées de pagination
     * @param int $currentPage
     * @param int $totalPages
     * @param int $totalItems
     * @param int $itemsPerPage
     * @return self
     */
    public function withPagination(int $currentPage, int $totalPages, int $totalItems, int $itemsPerPage): self
    {
        $this->meta['pagination'] = [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'items_per_page' => $itemsPerPage,
            'has_next' => $currentPage < $totalPages,
            'has_previous' => $currentPage > 1
        ];
        
        return $this;
    }
    
    /**
     * Ajoute des informations de timing
     * @param float $executionTime
     * @return self
     */
    public function withTiming(float $executionTime): self
    {
        $this->meta['timing'] = [
            'execution_time' => round($executionTime, 4),
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
        
        return $this;
    }
    
    /**
     * Ajoute des informations de debug (uniquement en développement)
     * @param array $debugInfo
     * @return self
     */
    public function withDebug(array $debugInfo): self
    {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->meta['debug'] = $debugInfo;
        }
        
        return $this;
    }
    
    /**
     * Ajoute un lien vers la documentation
     * @param string $docUrl
     * @return self
     */
    public function withDocumentation(string $docUrl): self
    {
        $this->meta['documentation'] = $docUrl;
        return $this;
    }
    
    /**
     * Transforme la réponse en tableau
     * @return array
     */
    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
            'message' => $this->message
        ];
        
        if ($this->data !== null) {
            $response['data'] = $this->data;
        }
        
        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }
        
        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }
        
        return $response;
    }
    
    /**
     * Transforme la réponse en JSON
     * @param int $options
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    /**
     * Envoie la réponse HTTP avec les bons headers
     * @param int $httpCode
     * @return void
     */
    public function send(int $httpCode = 200): void
    {
        // Nettoyer la sortie précédente
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Définir le code de statut HTTP
        http_response_code($httpCode);
        
        // Définir les headers
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // CORS si nécessaire
        if (defined('ENABLE_CORS') && ENABLE_CORS) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }
        
        // Envoyer la réponse
        echo $this->toJson();
        exit;
    }
    
    /**
     * Envoie une réponse de succès
     * @return void
     */
    public function sendSuccess(): void
    {
        $this->send(200);
    }
    
    /**
     * Envoie une réponse d'erreur
     * @return void
     */
    public function sendError(): void
    {
        $httpCode = 400;
        
        if (isset($this->errors['code'])) {
            $httpCode = $this->errors['code'];
        }
        
        $this->send($httpCode);
    }
    
    /**
     * Getters
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    public function getMeta(): array
    {
        return $this->meta;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Setters
     */
    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }
    
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }
    
    public function addError(string $key, string $error): self
    {
        $this->errors[$key] = $error;
        return $this;
    }
    
    public function addMeta(string $key, $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }
    
    /**
     * Méthodes utilitaires pour les réponses courantes
     */
    
    /**
     * Réponse pour une liste paginée
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $limit
     * @return ApiResponse
     */
    public static function paginated(array $items, int $total, int $page, int $limit): self
    {
        $totalPages = ceil($total / $limit);
        
        return self::success($items, "Liste récupérée avec succès")
            ->withPagination($page, $totalPages, $total, $limit);
    }
    
    /**
     * Réponse pour une création réussie
     * @param mixed $data
     * @param string $message
     * @return ApiResponse
     */
    public static function created($data, string $message = 'Ressource créée avec succès'): self
    {
        $response = new self(true, $message, $data);
        $response->send(201);
        return $response;
    }
    
    /**
     * Réponse pour une mise à jour réussie
     * @param mixed $data
     * @param string $message
     * @return ApiResponse
     */
    public static function updated($data = null, string $message = 'Ressource mise à jour avec succès'): self
    {
        return new self(true, $message, $data);
    }
    
    /**
     * Réponse pour une suppression réussie
     * @param string $message
     * @return ApiResponse
     */
    public static function deleted(string $message = 'Ressource supprimée avec succès'): self
    {
        return new self(true, $message);
    }
}
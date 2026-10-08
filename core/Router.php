<?php
declare(strict_types=1);

namespace Core;

/**
 * Routeur minimaliste : table de routes statique, segments {param} optionnels,
 * aucune dépendance externe.
 */
final class Router
{
    /** @var array<string, array<string, array{0:string,1:string,2:string}>> */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
    ];

    public function get(string $path, string $controller, string $action): self
    {
        $this->routes['GET'][$path] = [$controller, $action];
        return $this;
    }

    public function post(string $path, string $controller, string $action): self
    {
        $this->routes['POST'][$path] = [$controller, $action];
        return $this;
    }

    /** Dispatch de la requête courante. */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = self::currentPath();

        $match = $this->match($method, $path);
        if ($match === null) {
            http_response_code(404);
            (new View())->render('erreur/404', ['code' => 404], false);
            return;
        }

        [$controllerClass, $action, $params] = $match;
        $this->params = $params;
        Request::setParams($params);

        if (!class_exists($controllerClass)) {
            Logger::error("Controleur introuvable: $controllerClass");
            http_response_code(500);
            exit('Erreur interne.');
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $action)) {
            Logger::error("Action introuvable: $controllerClass::$action");
            http_response_code(500);
            exit('Erreur interne.');
        }

        try {
            $controller->{$action}();
        } catch (\Throwable $e) {
            Logger::error('Exception non interceptee: ' . $action, $e);
            Flash::add('danger', 'Une erreur technique est survenue. L\'operation a ete interrompue.');
            if (Request::isJson()) {
                Response::json(['success' => false, 'message' => 'Erreur technique.'], 500);
            }
            http_response_code(500);
            (new View())->render('erreur/500', [], false);
        }
    }

    /** Valeur d'un paramètre de route capturé. */
    public function param(string $nom, mixed $defaut = null): mixed
    {
        return $this->params[$nom] ?? $defaut;
    }

    /** @var array<string, mixed> */
    private array $params = [];

    /** Recherche la route correspondant à la méthode et au chemin. */
    private function match(string $method, string $path): ?array
    {
        if (isset($this->routes[$method][$path])) {
            return [...$this->routes[$method][$path], []];
        }
        foreach ($this->routes[$method] as $motif => $cible) {
            if (!str_contains($motif, '{')) {
                continue;
            }
            $regex = '#^' . $this->compilerMotif($motif) . '$#';
            if (preg_match($regex, $path, $m) === 1) {
                return [...$cible, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        return null;
    }

    /**
     * Transforme `/admin/dictionnaires/{type}/enregistrer` en une expression
     * régulière : les segments littéraux sont échappés, `{nom}` devient un
     * groupe nommé capturant un unique segment.
     */
    private function compilerMotif(string $motif): string
    {
        $regex   = '';
        $longueur = strlen($motif);
        $i       = 0;

        while ($i < $longueur) {
            $ouvrant = strpos($motif, '{', $i);
            if ($ouvrant === false) {
                $regex .= preg_quote(substr($motif, $i), '#');
                break;
            }
            $fermant = strpos($motif, '}', $ouvrant);
            if ($fermant === false) {
                $regex .= preg_quote(substr($motif, $i), '#');
                break;
            }
            $regex  .= preg_quote(substr($motif, $i, $ouvrant - $i), '#')
                . '(?P<' . substr($motif, $ouvrant + 1, $fermant - $ouvrant - 1) . '>[^/]+)';
            $i = $fermant + 1;
        }

        return $regex;
    }

    /** Chemin courant nettoyé (sans query string, sans slash final). */
    public static function currentPath(): string
    {
        $uri  = $_GET['r'] ?? $_GET['path'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = Url::base();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public')) {
            $uri = substr($uri, strlen('/public'));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }
}

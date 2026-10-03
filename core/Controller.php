<?php
declare(strict_types=1);

namespace Core;

/**
 * Contrôleur de base : helpers de rendu, redirection et contrôle d'accès.
 */
abstract class Controller
{
    protected function view(): View
    {
        return new View();
    }

    protected function baseUrl(): string
    {
        return Url::base();
    }

    /**
     * Rend une vue. `$layout = false` produit une page autonome, sans
     * l'en-tête ni le pied de page applicatifs (assistant d'installation).
     */
    protected function render(string $template, array $data = [], bool $layout = true): void
    {
        $this->view()->render($template, $data, $layout);
    }

    protected function redirect(string $chemin): never
    {
        Response::redirect($this->baseUrl() . $chemin);
    }

    protected function json(array $data, int $code = 200): never
    {
        Response::json($data, $code);
    }

    protected function ok(string $message, array $extra = []): never
    {
        $this->json(['success' => true, 'message' => $message] + $extra);
    }

    protected function ko(string $message, int $code = 422, array $extra = []): never
    {
        $this->json(['success' => false, 'message' => $message] + $extra, $code);
    }

    /** Exige une authentification + un niveau de rôle. */
    protected function guard(string $role = Auth::ROLE_LECTURE): void
    {
        Auth::requireRole($role);
    }

    /** Enveloppe une action d'écriture (CSRF + rôle). */
    protected function guardPost(string $role = Auth::ROLE_MODIFICATION): void
    {
        Csrf::verifyOrFail();
        Auth::requireRole($role);
    }
}

<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Flash;
use Core\Request;
use Models\AuditLog;
use Models\User;

/**
 * Consultation et recherche dans le journal d'audit (réservé aux administrateurs).
 */
final class AuditController extends Controller
{
    /** Consultation filtrable du journal d'audit avec pagination. */
    public function index(): void
    {
        $this->guard(Auth::ROLE_ADMIN);

        $filtres = AuditLog::filtersFromRequest();

        $page = max(1, Request::int('page', 1));
        $parPage = Request::int('par_page', 25);
        if (!in_array($parPage, [15, 25, 50, 100], true)) {
            $parPage = 25;
        }

        $total = AuditLog::count($filtres);
        $nbPages = max(1, (int) ceil($total / $parPage));
        if ($page > $nbPages && $total > 0) {
            $page = $nbPages;
        }
        $offset = ($page - 1) * $parPage;

        $logs = AuditLog::search($filtres, $parPage, $offset);
        $utilisateurs = User::all();

        $this->render('admin/journal', [
            'base_url'     => $this->baseUrl(),
            'logs'         => $logs,
            'filtres'      => $filtres,
            'total'        => $total,
            'page'         => $page,
            'nbPages'      => $nbPages,
            'parPage'      => $parPage,
            'offset'       => $offset,
            'utilisateurs' => $utilisateurs,
            'actions'      => AuditLog::ACTIONS,
            'entites'      => AuditLog::ENTITES,
            'immatriculations' => AuditLog::immatriculations(),
        ]);
    }

    /** Export CSV des entrées filtrées. */
    public function exportCsv(): never
    {
        $this->guard(Auth::ROLE_ADMIN);

        $filtres = AuditLog::filtersFromRequest();
        $logs = AuditLog::search($filtres, 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal_audit_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out !== false) {
            // Émission du BOM UTF-8 pour Excel
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID',
                'Date et Heure',
                'Utilisateur / Auteur',
                'Action',
                'Module / Entité',
                'Immatriculation',
                'Description',
                'Adresse IP',
                'Détails JSON',
            ], ';');

            foreach ($logs as $l) {
                fputcsv($out, [
                    (string) $l['id'],
                    (string) $l['created_at'],
                    (string) ($l['utilisateur_nom'] ?: $l['auteur_actuel_nom'] ?: 'Système'),
                    AuditLog::ACTIONS[$l['action']] ?? (string) $l['action'],
                    AuditLog::ENTITES[$l['entite']] ?? (string) $l['entite'],
                    (string) ($l['immatriculation'] ?? ''),
                    (string) $l['description'],
                    (string) ($l['ip'] ?? ''),
                    (string) ($l['details'] ?? ''),
                ], ';');
            }

            fclose($out);
        }
        exit;
    }

    /** Purge des anciens événements du journal. */
    public function purger(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $jours = max(30, Request::int('jours', 365));
        $supprimes = AuditLog::purge($jours);

        Flash::add('success', "Le journal d'audit a été purgé : $supprimes entrée(s) de plus de $jours jours supprimée(s).");
        $this->redirect('/admin/journal');
    }
}


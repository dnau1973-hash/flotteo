<?php
declare(strict_types=1);

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Mailer;
use Models\Vehicle;

/**
 * Génération et envoi du mail récapitulatif de fin de contrat.
 * Le calcul des paliers d'anticipation est paramétrable (90 / 180 / 270 jours par défaut).
 */
final class AlertService
{
    /**
     * Regroupe les véhicules arrivant à échéance par palier.
     *
     * @return array<int, array{palier: int, label: string, vehicules: array<int, array<string, mixed>>}>
     */
    public static function echeancier(): array
    {
        $paliers = self::paliers();
        $max     = max($paliers);
        $du      = date('Y-m-d');
        $au      = date('Y-m-d', strtotime("+$max days"));

        try {
            $vehicules = Database::all(
                Vehicle::SELECT_BASE . '
                 WHERE v.date_sortie_effective IS NULL
                   AND v.date_sortie_prevue BETWEEN :du AND :au
                 ORDER BY v.date_sortie_prevue ASC',
                ['du' => $du, 'au' => $au]
            );
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'marque_logo') || str_contains($e->getMessage(), 'logo')) {
                $sqlFallback = str_replace('ma.logo AS marque_logo,', 'NULL AS marque_logo,', Vehicle::SELECT_BASE);
                try {
                    $vehicules = Database::all(
                        $sqlFallback . '
                         WHERE v.date_sortie_effective IS NULL
                           AND v.date_sortie_prevue BETWEEN :du AND :au
                         ORDER BY v.date_sortie_prevue ASC',
                        ['du' => $du, 'au' => $au]
                    );
                } catch (\PDOException $e2) {
                    Logger::error('Lecture des échéances impossible', $e2);
                    $vehicules = [];
                }
            } else {
                Logger::error('Lecture des échéances impossible', $e);
                $vehicules = [];
            }
        }

        $groupes = [];
        foreach ($paliers as $p) {
            $groupes[$p] = [
                'palier'    => $p,
                'label'     => self::libellePalier($p),
                'vehicules' => [],
            ];
        }

        foreach ($vehicules as $v) {
            $jours = Vehicle::joursRestants($v);
            if ($jours === null) {
                continue;
            }
            foreach ($paliers as $p) {
                if ($jours <= $p) {
                    $groupes[$p]['vehicules'][] = $v + ['jours_restants' => $jours];
                    break;
                }
            }
        }

        return array_values($groupes);
    }

    /**
     * Envoie le mail condensé au gestionnaire. Aucun envoi si aucun véhicule concerné.
     *
     * @return array{envoi: bool, destinataire: string, concerne: int, message: string}
     */
    public static function notifier(): array
    {
        $destinataire = (string) Mailer::param('email_gestionnaire', '');
        $groupes      = array_values(array_filter(self::echeancier(), static fn (array $g): bool => $g['vehicules'] !== []));

        if ($destinataire === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            Logger::error('Alertes : adresse gestionnaire absente ou invalide.');
            return ['envoi' => false, 'destinataire' => $destinataire, 'concerne' => 0,
                    'message' => 'Adresse gestionnaire non configurée.'];
        }
        if ($groupes === []) {
            return ['envoi' => false, 'destinataire' => $destinataire, 'concerne' => 0,
                    'message' => 'Aucun véhicule n\'arrive à échéance dans les paliers configurés.'];
        }

        $concerne = array_sum(array_map(static fn (array $g): int => count($g['vehicules']), $groupes));
        $ok = Mailer::send(
            $destinataire,
            sprintf('Flotteo — %d véhicule(s) à échéance', $concerne),
            self::renderMail($groupes)
        );

        return [
            'envoi'       => $ok,
            'destinataire' => $destinataire,
            'concerne'    => $concerne,
            'message'     => $ok
                ? "Mail de rappel envoyé à $destinataire ($concerne véhicule(s))."
                : 'Le mail n\'a pas pu être envoyé.',
        ];
    }

    /** Paliers d'anticipation, lus en base avec repli sur la configuration. */
    public static function paliers(): array
    {
        $defauts = (require dirname(__DIR__) . '/config/config.php')['alertes']['paliers_jours'];
        $valeurs = [];

        foreach (array_keys($defauts) as $i => $defaut) {
            $cle  = 'alerte_palier_' . ($i + 1);
            $val  = (int) Mailer::param($cle, $defaut);
            if ($val > 0) {
                $valeurs[] = $val;
            }
        }

        $valeurs = array_values(array_unique($valeurs));
        sort($valeurs);
        return $valeurs !== [] ? $valeurs : $defauts;
    }

    private static function libellePalier(int $jours): string
    {
        $mois = (int) round($jours / 30);
        return $mois >= 2 ? "Sous $mois mois" : 'Sous 1 mois';
    }

    /** Corps HTML compact du mail récapitulatif. */
    private static function renderMail(array $groupes): string
    {
        $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $lignes = '';
        foreach ($groupes as $g) {
            $lignes .= '<tr><td colspan="5" style="padding:10px 0;font-weight:bold;color:#206bc4">'
                . $e($g['label']) . ' — ' . count($g['vehicules']) . ' véhicule(s)</td></tr>';
            foreach ($g['vehicules'] as $v) {
                $lignes .= '<tr style="border-top:1px solid #e6e7e9">'
                    . '<td style="padding:6px 8px">' . $e($v['immatriculation']) . '</td>'
                    . '<td style="padding:6px 8px">' . $e($v['marque_nom'] . ' ' . $v['modele_nom']) . '</td>'
                    . '<td style="padding:6px 8px">' . $e($v['loueur_nom']) . '</td>'
                    . '<td style="padding:6px 8px">' . $e($v['date_sortie_prevue']) . '</td>'
                    . '<td style="padding:6px 8px;text-align:right">J-' . (int) $v['jours_restants'] . '</td>'
                    . '</tr>';
            }
        }

        $total = array_sum(array_map(static fn (array $g): int => count($g['vehicules']), $groupes));

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1a2334">'
            . '<h2 style="margin:0 0 4px">Flotteo — Rapprochement des fins de contrat</h2>'
            . '<p style="margin:0 0 16px;color:#646c7a">'
            . $total . ' véhicule(s) du parc arrivent à échéance. '
            . 'Généré le ' . date('d/m/Y H:i') . '.</p>'
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%">'
            . '<tr style="background:#f6f8fb">'
            . '<th align="left" style="padding:8px">Immat.</th>'
            . '<th align="left" style="padding:8px">Véhicule</th>'
            . '<th align="left" style="padding:8px">Loueur</th>'
            . '<th align="left" style="padding:8px">Sortie prévue</th>'
            . '<th align="right" style="padding:8px">Reste</th>'
            . '</tr>' . $lignes . '</table>'
            . '<p style="margin:16px 0 0;color:#646c7a">Message automatique — ne pas répondre.</p>'
            . '</div>';
    }
}

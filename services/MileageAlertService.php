<?php
declare(strict_types=1);

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Mailer;
use Core\Url;

/**
 * Service de surveillance et relance automatique des relevés mensuels d'odomètre.
 */
final class MileageAlertService
{
    public static function verifierEtRelancer(): array
    {
        if ((int) Mailer::param('km_relance_active', '1') !== 1) {
            return ['statut' => 'desactive', 'relances' => 0];
        }

        $delaiGrace = max(1, (int) Mailer::param('km_delai_jours_relance', '3'));
        $aujourdhui = new \DateTimeImmutable('today');

        // Période M-1
        $premierDuMoisCourant = new \DateTimeImmutable('first day of this month');
        $dernierJourMoisPrecedent = $premierDuMoisCourant->modify('-1 day');
        $periodePrecedente = $dernierJourMoisPrecedent->format('Y-m');

        // Date de grâce = fin de mois + X jours
        $dateLimite = $dernierJourMoisPrecedent->modify("+{$delaiGrace} days");

        if ($aujourdhui <= $dateLimite) {
            return [
                'statut'   => 'en_periode_grace',
                'periode'  => $periodePrecedente,
                'relances' => 0,
            ];
        }

        // Véhicules actifs sans relevé pour M-1
        $vehiculesSansReleve = Database::all(
            "SELECT v.id, v.immatriculation, mo.nom AS modele_nom, ma.nom AS marque_nom, e.nom AS entite_nom
             FROM vehicules v
             JOIN modeles mo ON mo.id = v.modele_id
             JOIN marques ma ON ma.id = mo.marque_id
             JOIN entites e  ON e.id  = v.entite_id
             LEFT JOIN releves_odometre ro ON ro.vehicule_id = v.id AND ro.periode = :p
             WHERE v.statut = 'actif' AND ro.id IS NULL
             ORDER BY v.immatriculation ASC",
            ['p' => $periodePrecedente]
        );

        if ($vehiculesSansReleve === []) {
            return [
                'statut'   => 'tous_saisis',
                'periode'  => $periodePrecedente,
                'relances' => 0,
            ];
        }

        $destinataire = self::resoudreDestinataire();
        if ($destinataire === '') {
            Logger::error('Alerte odomètre : aucun destinataire email configuré.');
            return ['statut' => 'erreur_destinataire', 'relances' => 0];
        }

        $sujet = sprintf(
            '[Flotteo] %d véhicule(s) en attente de relevé kilométrique (%s)',
            count($vehiculesSansReleve),
            $periodePrecedente
        );

        $lien = Url::base() . '/kilometrage?periode=' . urlencode($periodePrecedente);
        $html = self::genererCorpsHtml($vehiculesSansReleve, $periodePrecedente, $lien);

        $ok = Mailer::send($destinataire, $sujet, $html);

        return [
            'statut'   => $ok ? 'succes' : 'erreur_envoi',
            'periode'  => $periodePrecedente,
            'relances' => $ok ? count($vehiculesSansReleve) : 0,
        ];
    }

    private static function resoudreDestinataire(): string
    {
        $type = (string) Mailer::param('km_destinataire_type', 'gestionnaire');
        if ($type === 'fixe') {
            $email = (string) Mailer::param('km_email_fixe', '');
            if ($email !== '') {
                return $email;
            }
        }
        return (string) Mailer::param('email_gestionnaire', '');
    }

    private static function genererCorpsHtml(array $vehicules, string $periode, string $lien): string
    {
        $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $lignes = '';
        foreach ($vehicules as $v) {
            $lignes .= '<tr>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e6e8eb; font-weight: 600;">' . $e($v['immatriculation']) . '</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e6e8eb;">' . $e($v['marque_nom'] . ' ' . $v['modele_nom']) . '</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e6e8eb; color: #64748b;">' . $e($v['entite_nom']) . '</td>
            </tr>';
        }

        return '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Relance relevés kilométriques</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f4f6fa; margin: 0; padding: 24px;">
    <div style="max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e6e8eb; overflow: hidden;">
        <div style="background-color: #206bc4; color: #ffffff; padding: 20px 24px;">
            <h2 style="margin: 0; font-size: 20px;">Flotteo — Rappel des relevés kilométriques</h2>
            <p style="margin: 4px 0 0; opacity: 0.85; font-size: 14px;">Période clôturée : ' . $e($periode) . '</p>
        </div>
        <div style="padding: 24px; color: #1e293b; font-size: 15px; line-height: 1.5;">
            <p>Bonjour,</p>
            <p>La date limite de déclaration kilométrique pour le mois de <strong>' . $e($periode) . '</strong> est échue. Les véhicules ci-dessous sont toujours en attente de leur relevé d\'odomètre :</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc; text-align: left;">
                        <th style="padding: 8px 12px; border-bottom: 2px solid #e6e8eb;">Immatriculation</th>
                        <th style="padding: 8px 12px; border-bottom: 2px solid #e6e8eb;">Modèle</th>
                        <th style="padding: 8px 12px; border-bottom: 2px solid #e6e8eb;">Entité</th>
                    </tr>
                </thead>
                <tbody>' . $lignes . '</tbody>
            </table>

            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $e($lien) . '" style="background-color: #206bc4; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; display: inline-block;">
                    Ouvrir la matrice de saisie rapide
                </a>
            </div>
        </div>
        <div style="background-color: #f8fafc; border-top: 1px solid #e6e8eb; padding: 12px 24px; font-size: 12px; color: #64748b; text-align: center;">
            Message automatique généré par Flotteo.
        </div>
    </div>
</body>
</html>';
    }
}

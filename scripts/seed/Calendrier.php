<?php
declare(strict_types=1);

namespace Seed;

/**
 * Conversions de dates relatives utilisées par le jeu de démonstration.
 *
 * Les fixtures décrivent les dates sous forme d'écarts en jours par rapport à la
 * date du jour : le jeu reste ainsi réaliste quand il est réinjecté, et les
 * paliers d'alerte (90 / 180 / 270 jours) restent atteints.
 */
final class Calendrier
{
    /** Date SQL décalée de `$jours` par rapport à aujourd'hui. */
    public static function jour(int $jours): string
    {
        return (new \DateTimeImmutable('today'))
            ->modify(sprintf('%+d days', $jours))
            ->format('Y-m-d');
    }

    /** Nombre de jours entre aujourd'hui et une date SQL. */
    public static function ecart(string $dateSql): int
    {
        $cible = \DateTimeImmutable::createFromFormat('Y-m-d', $dateSql);

        if ($cible === false) {
            throw new \InvalidArgumentException('Date illisible : ' . $dateSql);
        }

        return (int) $cible->diff(new \DateTimeImmutable('today'))->format('%r%a');
    }
}

<?php
declare(strict_types=1);

namespace Seed;

/**
 * Options de la ligne de commande du générateur de jeu de démonstration.
 */
final class Options
{
    public bool $verification = false;
    public ?string $sql = null;

    /** @param list<string> $argv */
    public static function analyser(array $argv): self
    {
        $options = new self();

        foreach (array_slice($argv, 1) as $argument) {
            switch ($argument) {
                case '--verifier':
                    $options->verification = true;
                    break;
                case '--sql':
                    $options->sql = FLOTTEO_ROOT . '/sql/demodata.sql';
                    break;
                default:
                    // Option inconnue : on l'annonce plutôt que de l'ignorer.
                    fwrite(STDERR, 'Option inconnue : ' . $argument . PHP_EOL);
                    fwrite(STDERR, "Usage : php scripts/seed_demo.php [--verifier|--sql]\n");
                    exit(2);
            }
        }

        return $options;
    }
}
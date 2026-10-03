<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Flash;
use Core\Logger;
use Core\Markdown;
use Core\Request;
use Models\Registre;

/**
 * Registres documentaires, rendus dans le layout général de l'application.
 *
 * Ces quatre pages (guide utilisateur, journal des modifications, fonctionnalités,
 * recette QA) sont du contenu applicatif : elles s'affichent dans la barre
 * supérieure, l'en-tête et le pied de page de Flotteo, comme les autres écrans.
 *
 * Le dossier `docs/` vit hors de la racine servie par Apache, ce qui interdit
 * d'y lier les vues directement : le Markdown est donc converti à la demande par
 * `Core\Markdown`, le moteur également utilisé par `scripts/build_docs.php` pour
 * produire les vues HTML autonomes. Une seule implémentation, donc aucun écart
 * possible entre la page vue dans l'application et le fichier compilé.
 *
 * La conversion à la volée coûte de 0,5 à 3,5 ms par registre, ce qui rend
 * inutile toute copie intermédiaire susceptible de périmer.
 */
final class DocsController extends Controller
{
    /**
     * Registre par défaut lorsqu'aucun n'est demandé.
     *
     * Le guide utilisateur est le point d'entrée logique : c'est la destination
     * du lien imposé par le §3 des règles.
     */
    public function index(): void
    {
        $this->redirect('/docs/user_guide');
    }

    public function show(): void
    {
        $this->guard();

        $cle = (string) Request::param('doc', '');
        if (Registre::obtenir($cle) === null) {
            Flash::add('danger', 'Document introuvable.');
            $this->redirect('/dashboard');
        }

        $chemin = Registre::source($cle);
        if ($chemin === null) {
            Logger::error("Source de registre documentaire introuvable ou illisible : $cle");
            Flash::add('danger', 'Document indisponible.');
            $this->redirect('/dashboard');
        }

        try {
            $contenu = Markdown::render((string) file_get_contents($chemin));
        } catch (\Exception $e) {
            // La trace reste au journal ; l'utilisateur reçoit un message clair.
            Logger::error("Conversion du registre « $cle » impossible", $e);
            Flash::add('danger', 'Document illisible.');
            $this->redirect('/dashboard');
        }

        $this->render('docs/index', [
            'cle'       => $cle,
            'document'  => Registre::obtenir($cle),
            'registres' => Registre::tous(),
            'contenu'   => $contenu,
        ]);
    }
}
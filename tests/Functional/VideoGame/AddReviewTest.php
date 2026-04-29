<?php

declare(strict_types=1);

namespace App\Tests\Functional\VideoGame;

use App\Model\Entity\Review;
use App\Model\Entity\User;
use App\Model\Entity\VideoGame;
use App\Tests\Functional\FunctionalTestCase;

/**
 * Tests fonctionnels pour l'ajout d'une note à un jeu vidéo.
 *
 * Pourquoi un "utilisateur frais" plutôt que user+0 ?
 * Les fixtures assignent systématiquement user+0 à tous les jeux (array_slice commence
 * toujours à l'index 0), donc le formulaire n'est jamais affiché pour user+0.
 * On crée un utilisateur dédié à chaque test via loginFreshUser() — dama/doctrine-test-bundle
 * annule automatiquement la transaction à la fin du test, donc aucun nettoyage manuel n'est requis.
 */
final class AddReviewTest extends FunctionalTestCase
{
    /**
     * Crée un nouvel utilisateur, le persiste et le connecte via loginUser().
     * Cet utilisateur n'a aucune review en base, le formulaire sera donc visible.
     * Le UserListener (EntityListener) hashera automatiquement le plainPassword lors du persist.
     */
    private function loginFreshUser(): User
    {
        $user = (new User())
            ->setEmail('fresh@test.com')
            ->setUsername('freshuser')
            ->setPlainPassword('password');

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        // loginUser() injecte le token de sécurité sans passer par le formulaire de login
        $this->client->loginUser($user);

        return $user;
    }

    /**
     * Cas nominal : un utilisateur connecté soumet une note valide.
     *
     * Vérifie :
     *  - le code HTTP est 302 (redirection après succès)
     *  - la review est correctement enregistrée en base de données
     *  - après redirection, le formulaire n'est plus affiché (déjà noté)
     */
    public function testShouldAddReviewSuccessfully(): void
    {
        $user = $this->loginFreshUser();

        // GET pour charger la page et avoir le formulaire dans le Crawler
        $this->get('/jeu-video-0');

        // submitForm() cherche le bouton "Poster" dans le DOM et soumet le formulaire
        $this->client->submitForm('Poster', [
            'review[rating]' => 3,
            'review[comment]' => 'Super jeu !',
        ]);

        // Le contrôleur redirige vers la même page après une soumission réussie
        self::assertResponseRedirects('/jeu-video-0');

        // Vérifie en base que la review a bien été créée avec les bonnes données
        $videoGame = $this->getEntityManager()
            ->getRepository(VideoGame::class)
            ->findOneBy(['slug' => 'jeu-video-0']);

        // On cherche la review par l'utilisateur pour ne pas confondre avec les reviews des fixtures
        $review = $this->getEntityManager()
            ->getRepository(Review::class)
            ->findOneBy(['videoGame' => $videoGame, 'user' => $user]);

        self::assertNotNull($review);
        self::assertSame(3, $review->getRating());
        self::assertSame('Super jeu !', $review->getComment());

        // Après redirection, le formulaire ne doit plus être affiché :
        // le voter retourne false car l'utilisateur a déjà noté ce jeu
        $this->client->followRedirect();
        self::assertSelectorNotExists('form[name="review"]');
    }

    /**
     * Tests de validation : une note invalide doit retourner 422 (Unprocessable Entity).
     *
     * On utilise request() directement plutôt que submitForm() car le DomCrawler
     * valide les valeurs d'un <select> côté client avant d'envoyer la requête et
     * rejette tout ce qui n'est pas dans les options (impossible de tester '' ou 99).
     * Avec request(), on bypasse cette validation DOM et on laisse Symfony gérer
     * la validation côté serveur → form invalide → 422.
     *
     * @dataProvider provideInvalidFormData
     */
    public function testShouldReturn422WhenFormDataIsInvalid(array $postData): void
    {
        $this->loginFreshUser();

        // POST direct sans GET préalable : le formulaire est considéré "soumis"
        // dès que son nom ('review') est présent dans les données POST
        $this->client->request('POST', '/jeu-video-0', $postData);

        self::assertResponseIsUnprocessable();
    }

    /**
     * Jeux de données invalides pour les tests de validation.
     * Format tableau imbriqué car on utilise request() (pas submitForm()).
     */
    public static function provideInvalidFormData(): iterable
    {
        // Note absente : '' n'est pas dans les choices [1, 2, 3, 4, 5]
        yield 'note manquante' => [['review' => ['rating' => '', 'comment' => '']]];

        // Note hors limites : '99' n'est pas dans les choices valides
        yield 'note hors limites (99)' => [['review' => ['rating' => '99', 'comment' => '']]];
    }

    /**
     * Le formulaire est masqué pour les utilisateurs non connectés.
     *
     * Le template utilise {% if is_granted('review', video_game) %}.
     * Le VideoGameVoter retourne false pour les anonymes → le formulaire est absent du HTML.
     */
    public function testFormShouldNotBeShownForUnauthenticatedUser(): void
    {
        $this->get('/jeu-video-0');

        self::assertSelectorNotExists('form[name="review"]');
    }

    /**
     * Un utilisateur non connecté ne peut pas créer de review, même via un POST direct.
     *
     * Note technique : lorsqu'on POST directement avec request(), le ChoiceType reçoit
     * un entier PHP (3) au lieu d'une chaîne HTML ('3'). La validation échoue avant
     * d'atteindre denyAccessUnlessGranted(), ce qui retourne 422 (form invalide).
     * L'important est qu'aucune review ne soit créée en base de données.
     */
    public function testShouldNotCreateReviewWhenUserIsNotAuthenticated(): void
    {
        $videoGame = $this->getEntityManager()
            ->getRepository(VideoGame::class)
            ->findOneBy(['slug' => 'jeu-video-0']);

        // Compte les reviews existantes avant la tentative de POST
        $reviewCountBefore = $videoGame->getReviews()->count();

        $this->client->request('POST', '/jeu-video-0', [
            'review' => ['rating' => 3, 'comment' => 'Test'],
        ]);

        // Rafraîchit l'entité pour relire la collection depuis la base de données
        $this->getEntityManager()->refresh($videoGame);

        // Vérifie qu'aucune nouvelle review n'a été ajoutée, quel que soit le code HTTP
        self::assertCount($reviewCountBefore, $videoGame->getReviews());
    }
}

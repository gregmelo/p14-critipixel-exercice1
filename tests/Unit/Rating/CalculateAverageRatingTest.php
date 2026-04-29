<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rating;

use App\Model\Entity\Review;
use App\Model\Entity\VideoGame;
use App\Rating\RatingHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire pour la méthode calculateAverage() de RatingHandler.
 *
 * Un test unitaire vérifie une seule chose de façon isolée, sans base de données
 * ni services Symfony. On instancie les objets manuellement et on vérifie le résultat.
 */
final class CalculateAverageRatingTest extends TestCase
{
    // Propriété qui contiendra le service à tester, réinitialisé avant chaque test
    private RatingHandler $handler;

    /**
     * setUp() est exécutée automatiquement par PHPUnit avant chaque test.
     * Elle garantit que chaque test part d'un état propre et identique.
     */
    protected function setUp(): void
    {
        $this->handler = new RatingHandler();
    }

    /**
     * Méthode de test principale.
     *
     * Le préfixe "test" est obligatoire pour que PHPUnit détecte cette méthode.
     * La directive "dataProvider" ci-dessous indique que les paramètres ($ratings, $expectedAverage)
     * sont fournis par la méthode provideRatingsAndExpectedAverage().
     * PHPUnit appellera ce test une fois par jeu de données fourni.
     *
     * @dataProvider provideRatingsAndExpectedAverage
     */
    public function testCalculateAverage(array $ratings, ?int $expectedAverage): void
    {
        // Arrange : on prépare un jeu vidéo vide et on lui ajoute des reviews
        $videoGame = new VideoGame();

        foreach ($ratings as $rating) {
            // On crée une Review avec uniquement la note — les autres champs
            // (user, videoGame) ne sont pas lus par calculateAverage(), donc inutiles ici
            $review = (new Review())->setRating($rating);

            // getReviews() retourne l'ArrayCollection du jeu vidéo,
            // on peut donc y ajouter directement sans passer par Doctrine
            $videoGame->getReviews()->add($review);
        }

        // Act : on appelle la méthode que l'on veut tester
        $this->handler->calculateAverage($videoGame);

        // Assert : on vérifie que la moyenne calculée correspond à la valeur attendue.
        // assertSame() vérifie la valeur ET le type (ex: 3 !== "3"), ce qui est plus strict
        // que assertEquals() qui ferait une comparaison souple.
        self::assertSame($expectedAverage, $videoGame->getAverageRating());
    }

    /**
     * DataProvider : fournit les jeux de données pour testCalculateAverage().
     *
     * Chaque entrée est un tableau associatif nommé (la clé sert de label dans le rapport PHPUnit).
     * Chaque valeur contient : [tableau de notes, moyenne attendue].
     *
     * Cela permet de tester plusieurs scénarios sans dupliquer le code du test.
     * La formule utilisée dans RatingHandler est : ceil(somme / nombre de notes).
     */
    public static function provideRatingsAndExpectedAverage(): array
    {
        return [
            // Cas limite : aucune review → la moyenne doit être null
            'Aucune note → null' => [[], null],

            // Cas simple : une seule note
            'Une seule note de 3 → 3' => [[3], 3],

            // Moyenne exacte : (1+3+5) / 3 = 3.0 → ceil(3.0) = 3
            'Notes [1, 3, 5] → moyenne 3' => [[1, 3, 5], 3],

            // Moyenne exacte : (1+2+3+4+5) / 5 = 3.0 → ceil(3.0) = 3
            'Notes [1, 2, 3, 4, 5] → moyenne 3' => [[1, 2, 3, 4, 5], 3],

            // Arrondi supérieur : (4+5) / 2 = 4.5 → ceil(4.5) = 5
            'Notes [4, 5] → arrondi supérieur → 5' => [[4, 5], 5],

            // Toutes les mêmes notes : (1+1) / 2 = 1.0 → ceil(1.0) = 1
            'Notes [1, 1] → moyenne 1' => [[1, 1], 1],
        ];
    }
}

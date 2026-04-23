<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rating;

use App\Model\Entity\Review;
use App\Model\Entity\VideoGame;
use App\Rating\RatingHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire pour la méthode countRatingsPerValue() de RatingHandler.
 *
 * Cette méthode parcourt les reviews d'un jeu vidéo et incrémente un compteur
 * par valeur de note (1 à 5), stocké dans l'objet NumberOfRatingPerValue.
 */
final class CountRatingsPerValueTest extends TestCase
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
     * @dataProvider provideRatingsAndExpectedCounts indique à PHPUnit de récupérer
     * les paramètres depuis provideRatingsAndExpectedCounts().
     * Ce test sera exécuté autant de fois qu'il y a de jeux de données.
     *
     * @dataProvider provideRatingsAndExpectedCounts
     */
    public function testCountRatingsPerValue(array $ratings, array $expectedCounts): void
    {
        // Arrange : on prépare un jeu vidéo vide et on lui ajoute des reviews
        $videoGame = new VideoGame();

        foreach ($ratings as $rating) {
            // On crée une Review avec uniquement la note — countRatingsPerValue()
            // n'a besoin que de getRating(), les autres champs sont inutiles ici
            $review = (new Review())->setRating($rating);

            // getReviews() retourne l'ArrayCollection du jeu vidéo,
            // on y ajoute directement sans passer par Doctrine
            $videoGame->getReviews()->add($review);
        }

        // Act : on appelle la méthode que l'on veut tester
        $this->handler->countRatingsPerValue($videoGame);

        // Assert : on récupère l'objet qui stocke les compteurs par valeur
        // et on vérifie chaque compteur individuellement (1 assertion par valeur possible)
        $counts = $videoGame->getNumberOfRatingsPerValue();
        self::assertSame($expectedCounts[1], $counts->getNumberOfOne());
        self::assertSame($expectedCounts[2], $counts->getNumberOfTwo());
        self::assertSame($expectedCounts[3], $counts->getNumberOfThree());
        self::assertSame($expectedCounts[4], $counts->getNumberOfFour());
        self::assertSame($expectedCounts[5], $counts->getNumberOfFive());
    }

    /**
     * DataProvider : fournit les jeux de données pour testCountRatingsPerValue().
     *
     * Chaque entrée contient :
     *   - un tableau de notes attribuées (ex: [1, 2, 2, 3, 5])
     *   - un tableau associatif des compteurs attendus, indexé par valeur de note (1 à 5)
     */
    public static function provideRatingsAndExpectedCounts(): array
    {
        return [
            // Cas limite : aucune review → tous les compteurs doivent rester à 0
            'Aucune note → tous à 0' => [
                [],
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ],

            // Une seule note de 1 → seul le compteur "1" passe à 1
            'Une seule note de 1' => [
                [1],
                [1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ],

            // Notes variées avec doublons → vérifie l'accumulation correcte des compteurs
            'Notes variées [1, 2, 2, 3, 5]' => [
                [1, 2, 2, 3, 5],
                [1 => 1, 2 => 2, 3 => 1, 4 => 0, 5 => 1],
            ],

            // Toutes les valeurs représentées une seule fois → chaque compteur vaut 1
            'Toutes les valeurs une fois' => [
                [1, 2, 3, 4, 5],
                [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1],
            ],

            // Plusieurs notes identiques → vérifie que le même compteur s'incrémente bien plusieurs fois
            'Plusieurs notes de 4' => [
                [4, 4, 4],
                [1 => 0, 2 => 0, 3 => 0, 4 => 3, 5 => 0],
            ],
        ];
    }
}

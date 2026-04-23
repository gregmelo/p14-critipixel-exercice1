<?php

namespace App\Doctrine\DataFixtures;

use App\Model\Entity\User;
use App\Model\Entity\VideoGame;
use App\Rating\CalculateAverageRating;
use App\Rating\CountRatingsPerValue;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Generator;
use App\Model\Entity\Tag;
use App\Model\Entity\Review;



use function array_fill_callback;

/**
 * Crée 50 jeux vidéo avec des tags et des reviews aléatoires.
 * Dépend de UserFixtures et TagFixtures qui doivent être chargées en premier.
 */
final class VideoGameFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly Generator $faker,
        private readonly CalculateAverageRating $calculateAverageRating,
        private readonly CountRatingsPerValue $countRatingsPerValue
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Récupère tous les utilisateurs pour les assigner aux reviews
        $users = $manager->getRepository(User::class)->findAll();

        // Génère 50 jeux vidéo avec des données aléatoires via Faker
        /** @var VideoGame[] $videoGames */
        $videoGames = array_fill_callback(
            0,
            50,
            fn(int $index): VideoGame => (new VideoGame)
                ->setTitle(sprintf('Jeu vidéo %d', $index))
                ->setDescription($this->faker->paragraphs(10, true))
                ->setReleaseDate(new DateTimeImmutable())
                ->setTest($this->faker->paragraphs(6, true))
                ->setRating(($index % 5) + 1)
                ->setImageName(sprintf('video_game_%d.png', $index))
                ->setImageSize(2_098_872)
        );

        // Assigne entre 1 et 3 tags aléatoires à chaque jeu
        foreach ($videoGames as $videoGame) {
            $randomTagIndexes = array_rand(range(0, 9), rand(1, 3));
            foreach ((array) $randomTagIndexes as $tagIndex) {
                // Récupère le tag depuis les références créées par TagFixtures
                $videoGame->getTags()->add($this->getReference('tag_' . $tagIndex, Tag::class));
            }
        }

        array_walk($videoGames, [$manager, 'persist']);

        // Premier flush pour persister les jeux et leurs tags avant de créer les reviews
        $manager->flush();

        // Crée des reviews pour chaque jeu et recalcule les statistiques de notation
        foreach ($videoGames as $videoGame) {
            // Sélectionne un sous-ensemble aléatoire d'utilisateurs pour noter le jeu
            $reviewCount = rand(1, count($users));
            $usersToReview = array_slice($users, 0, $reviewCount);

            foreach ($usersToReview as $user) {
                $review = (new Review)
                    ->setVideoGame($videoGame)
                    ->setUser($user)
                    ->setRating(rand(1, 5))
                    ->setComment($this->faker->optional()->paragraph());
                $manager->persist($review);
            }

            // Met à jour la note moyenne et le décompte par valeur après ajout des reviews
            $this->calculateAverageRating->calculateAverage($videoGame);
            $this->countRatingsPerValue->countRatingsPerValue($videoGame);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        // Garantit que les utilisateurs et les tags existent avant de créer les jeux
        return [UserFixtures::class, TagFixtures::class];
    }
}

<?php

declare(strict_types=1);

namespace App\Doctrine\DataFixtures;

use App\Model\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Crée les tags de jeux vidéo en base de données.
 * Doit être chargée avant VideoGameFixtures (référencée dans ses dépendances).
 */
final class TagFixtures extends Fixture
{
    // Liste des tags disponibles pour catégoriser les jeux vidéo
    private const TAGS = [
        'Action', 'Aventure', 'RPG', 'FPS', 'Plateforme',
        'Stratégie', 'Sport', 'Course', 'Horreur', 'Puzzle',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::TAGS as $index => $name) {
            $tag = (new Tag())->setName($name);
            $manager->persist($tag);

            // Stocke une référence pour permettre à VideoGameFixtures de récupérer ce tag
            $this->addReference('tag_' . $index, $tag);
        }

        $manager->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\VideoGame;

use App\Model\Entity\Tag;
use App\Model\Entity\VideoGame;
use App\Tests\Functional\FunctionalTestCase;

final class FilterTest extends FunctionalTestCase
{
    public function testShouldListTenVideoGames(): void
    {
        $this->get('/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'article.game-card');
        $this->client->clickLink('2');
        self::assertResponseIsSuccessful();
    }

    public function testShouldFilterVideoGamesBySearch(): void
    {
        $this->get('/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'article.game-card');
        $this->client->submitForm('Filtrer', ['filter[search]' => 'Jeu vidéo 49'], 'GET');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'article.game-card');
    }

    /**
     * Vérifie que le filtrage par tags retourne le bon nombre de jeux.
     *
     * Pourquoi compter dynamiquement depuis la BDD ?
     * Les fixtures assignent des tags aléatoirement (1 à 3 tags par jeu).
     * Le nombre de jeux correspondant à un filtre varie donc à chaque rechargement des fixtures.
     * On interroge la BDD pour connaître le nombre attendu, puis on vérifie que la page affiche
     * ce même nombre de cartes (plafonné à 10 par la pagination).
     *
     * @dataProvider provideTagFilterData
     */
    public function testShouldFilterVideoGamesByTags(array $tagNames): void
    {
        $tagRepository = $this->getEntityManager()->getRepository(Tag::class);

        // Résolution des noms de tags passés par le DataProvider en entités Tag
        $tags = array_map(
            fn (string $name) => $tagRepository->findOneBy(['name' => $name]),
            $tagNames,
        );

        // Extraction des IDs pour les passer en paramètre du formulaire HTML
        $tagIds = array_map(fn (Tag $tag) => $tag->getId(), $tags);

        // Nombre de jeux répondant aux critères (logique ET : le jeu doit avoir TOUS les tags)
        $expectedTotal = $this->countGamesMatchingTags($tags);

        // La pagination affiche au maximum 10 jeux par page
        $expectedCards = min(10, $expectedTotal);

        // On utilise request() plutôt que submitForm() car le DomCrawler mappe les valeurs
        // du tableau positionellement aux cases à cocher (index 0 → 1re case, index 1 → 2e case).
        // Avec deux tags d'IDs non consécutifs (ex. 1 et 3), submitForm() essaierait d'affecter
        // l'ID 3 à la 2e case (valeur "2"), provoquant une InvalidArgumentException.
        // request() envoie directement les paramètres GET sans validation DOM.
        $queryParams = [] === $tagIds ? [] : ['filter' => ['tags' => $tagIds]];
        $this->client->request('GET', '/', $queryParams);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount($expectedCards, 'article.game-card');
    }

    /**
     * Jeux de données pour testShouldFilterVideoGamesByTags.
     * Le DataProvider est statique (pas d'accès BDD) : il fournit des noms de tags,
     * la méthode de test résout ensuite les noms en entités via le repository.
     */
    public static function provideTagFilterData(): iterable
    {
        // Aucun tag sélectionné → tous les 50 jeux, 10 par page
        yield 'aucun tag sélectionné' => [[]];

        // Un seul tag → filtre sur les jeux possédant ce tag
        yield 'un seul tag (Action)' => [['Action']];

        // Deux tags → logique ET : seuls les jeux ayant les DEUX tags sont retournés
        yield 'deux tags (Action et RPG)' => [['Action', 'RPG']];
    }

    /**
     * Vérifie qu'un ID de tag inexistant est ignoré silencieusement.
     *
     * Comportement attendu :
     *  1. L'EntityType tente de trouver un Tag avec cet ID en BDD → non trouvé.
     *  2. Le champ tags reste une collection vide → aucun filtre tag n'est appliqué.
     *  3. Tous les 50 jeux sont retournés → 10 cartes sur la première page.
     *
     * On utilise request() directement plutôt que submitForm() car les cases à cocher
     * du DOM ne contiennent que des IDs valides ; submitForm() refuserait une valeur
     * absente de la liste de choix.
     */
    public function testShouldIgnoreNonExistentTagId(): void
    {
        // Calcule un ID supérieur à tous les IDs existants → garantit l'inexistence en BDD
        $maxId = (int) $this->getEntityManager()
            ->createQuery('SELECT MAX(t.id) FROM ' . Tag::class . ' t')
            ->getSingleScalarResult();

        $nonExistentId = $maxId + 1;

        // GET direct avec le paramètre filter[tags][] = nonExistentId
        $this->client->request('GET', '/', ['filter' => ['tags' => [$nonExistentId]]]);

        self::assertResponseIsSuccessful();

        // L'ID invalide est ignoré → aucun filtre appliqué → 10 jeux par page (50 au total)
        self::assertSelectorCount(10, 'article.game-card');
    }

    /**
     * Compte les jeux vidéo possédant TOUS les tags spécifiés (logique ET).
     * Reproduit la logique de VideoGameRepository::getVideoGames() sans pagination,
     * afin de calculer le nombre attendu de cartes indépendamment du repository testé.
     *
     * @param Tag[] $tags
     */
    private function countGamesMatchingTags(array $tags): int
    {
        // Sans filtre, tous les 50 jeux de fixtures correspondent
        if ([] === $tags) {
            return 50;
        }

        // Sélectionne les IDs des jeux ayant exactement tous les tags (COUNT DISTINCT = tagCount)
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('vg.id')
            ->from(VideoGame::class, 'vg')
            ->join('vg.tags', 't')
            ->where('t.id IN (:tags)')
            ->groupBy('vg.id')
            ->having('COUNT(DISTINCT t.id) = :tagCount')
            ->setParameter('tags', $tags)
            ->setParameter('tagCount', \count($tags))
            ->getQuery()
            ->getResult();

        // Chaque ligne correspond à un jeu distinct → count() donne le total
        return \count($result);
    }
}

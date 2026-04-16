<?php

namespace App\Twig\Components;

use App\Model\Entity\VideoGame;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Progress
{
    public VideoGame $videoGame;

    public int $number;

    public function getPercent(): int
    {
        $nbOfReviews = count($this->videoGame->getReviews());
        return $nbOfReviews === 0 ? 0 : (int) round(($this->number / $nbOfReviews) * 100);
    }
}

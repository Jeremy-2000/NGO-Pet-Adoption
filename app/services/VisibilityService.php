<?php
declare(strict_types=1);

class VisibilityService {
    private array $weights;
    private array $limits;

    public function __construct(array $weights, array $limits = []) {
        $this->weights = $weights;
        $this->limits = array_merge([
            'max_days_listed' => 180,
            'max_views' => 100,
            'max_favorites' => 25,
            'max_vote_wins' => 50,
        ], $limits);
    }

    public function score(array $animal): float {
        $daysListed = max(0, (int) floor((time() - strtotime((string) $animal['created_at'])) / 86400));
        $views = max(0, (int) ($animal['views_count'] ?? 0));
        $favorites = max(0, (int) ($animal['favorites_count'] ?? 0));
        $voteWins = max(0, (int) ($animal['vote_wins'] ?? 0));
        $isSenior = !empty($animal['is_senior']) ? 1 : 0;

        $daysScore = min(1, $daysListed / max(1, (int) $this->limits['max_days_listed']));
        $viewScore = max(0, 1 - min(1, $views / max(1, (int) $this->limits['max_views'])));
        $engagementScore = max(0, 1 - min(1, $favorites / max(1, (int) $this->limits['max_favorites'])));
        $seniorScore = $isSenior;
        $voteScore = min(1, $voteWins / max(1, (int) $this->limits['max_vote_wins']));

        return round(
            ($daysScore * (float) ($this->weights['days_listed'] ?? 0)) +
            ($viewScore * (float) ($this->weights['low_views'] ?? 0)) +
            ($engagementScore * (float) ($this->weights['low_engagement'] ?? 0)) +
            ($seniorScore * (float) ($this->weights['senior_factor'] ?? 0)) +
            ($voteScore * (float) ($this->weights['vote_boost'] ?? 0)),
            4
        );
    }

    public function explain(array $animal): array
    {
        $score = $this->score($animal);

        return [
            'score' => $score,
            'days_listed' => max(0, (int) floor((time() - strtotime((string) $animal['created_at'])) / 86400)),
            'views_count' => (int) ($animal['views_count'] ?? 0),
            'favorites_count' => (int) ($animal['favorites_count'] ?? 0),
            'vote_wins' => (int) ($animal['vote_wins'] ?? 0),
            'is_senior' => (int) ($animal['is_senior'] ?? 0),
        ];
    }
}

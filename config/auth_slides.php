<?php

/**
 * Auth layout marketing slider. Bump "version" after changing images to bust browser query strings.
 * Manifest is cached (Redis when CACHE_STORE=redis) in HandleInertiaRequests.
 * Place PNGs in public/App/ (1.png … 7.png). Bump version after changes.
 */
return [
    'version' => env('AUTH_SLIDES_VERSION', '3'),
    /** Seconds — Redis/file cache for the manifest payload (not the PNG bytes). */
    'cache_ttl' => (int) env('AUTH_SLIDES_CACHE_TTL', 86400),

    'items' => [
        [
            'image' => '/App/1.png',
            'title' => 'Smart learning dashboard',
            'subtitle' => 'Track lessons, goals, and your growth in one place.',
            'tag' => 'Dashboard',
        ],
        [
            'image' => '/App/2.png',
            'title' => 'Personalized progress',
            'subtitle' => 'See performance insights with a clean visual timeline.',
            'tag' => 'Progress',
        ],
        [
            'image' => '/App/3.png',
            'title' => 'Connect and collaborate',
            'subtitle' => 'Learn with peers, teachers, and your community instantly.',
            'tag' => 'Community',
        ],
        [
            'image' => '/App/4.png',
            'title' => 'Assignments made easy',
            'subtitle' => 'Complete work faster with guided workflows.',
            'tag' => 'Tasks',
        ],
        [
            'image' => '/App/5.png',
            'title' => 'Anytime mobile access',
            'subtitle' => 'Stay consistent with learning on the go.',
            'tag' => 'Mobile',
        ],
        [
            'image' => '/App/6.png',
            'title' => 'Insights that motivate',
            'subtitle' => 'Beautiful reports to keep momentum high.',
            'tag' => 'Insights',
        ],
        [
            'image' => '/App/7.png',
            'title' => 'One app for everything',
            'subtitle' => 'From classes to outcomes - all in SuGanta.',
            'tag' => 'All-in-one',
        ],
    ],
];

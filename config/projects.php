<?php

declare(strict_types=1);

/**
 * Projects to ingest into the history pipeline.
 *
 * Each entry maps a project name to its local path, GitHub repo slug,
 * and known technology stack. Adjust paths to match your Laragon setup.
 */
return [
    'developer' => [
        'name' => 'Moshe Landau',
        'github' => 'moshelandau',
        'email' => null,
    ],

    'github_token' => env('GITHUB_TOKEN', null),

    'projects' => [
        [
            'name' => 'Derech',
            'local_path' => 'C:/laragon/www/Derech',
            'repo' => 'chaim-code/Derech',
            'description' => 'YDSH Tuition System',
            'stack' => ['Laravel', 'PHP', 'Vue 3', 'Inertia.js', 'PostgreSQL'],
            'url' => 'https://github.com/chaim-code/Derech',
        ],
        [
            'name' => 'Momentique',
            'local_path' => 'C:/laragon/www/momentique',
            'repo' => 'moshelandau/Momentique',
            'description' => 'POS / e-commerce system',
            'stack' => ['Laravel', 'PHP', 'Vue 3', 'Inertia.js', 'PostgreSQL'],
            'url' => 'https://github.com/moshelandau/Momentique',
        ],
        [
            'name' => 'Attendance System',
            'local_path' => 'C:/laragon/www/attendance.system',
            'repo' => 'moshelandau/attendance.system',
            'description' => 'UTA student/teacher attendance tracking with SMS/voice and AI parsing',
            'stack' => ['Laravel', 'PHP', 'Vue 3', 'Inertia.js', 'PostgreSQL', 'Plivo', 'Claude AI'],
            'url' => 'https://github.com/moshelandau/attendance.system',
        ],
        [
            'name' => 'SimpleForwarding',
            'local_path' => 'C:/laragon/www/SimpleForwarding',
            'repo' => 'moshelandau/SimpleForwarding',
            'description' => 'CMS / website',
            'stack' => ['Laravel', 'PHP', 'Filament', 'React', 'Inertia.js', 'Tailwind CSS'],
            'url' => 'https://github.com/moshelandau/SimpleForwarding',
        ],
        [
            'name' => 'UTAEN',
            'local_path' => 'C:/laragon/www/UTAEN',
            'repo' => 'jatinthapar1910/utaen.org',
            'description' => 'UTA attendance system',
            'stack' => ['Laravel', 'PHP', 'Vue 3', 'Inertia.js'],
            'url' => 'https://github.com/jatinthapar1910/utaen.org',
        ],
        [
            'name' => 'MealCount',
            'local_path' => 'C:/laragon/www/mealcount.org',
            'repo' => 'jatinthapar1910/mealcount.org',
            'description' => 'School meal counting and bulk closure',
            'stack' => ['Laravel', 'PHP'],
            'url' => 'https://github.com/jatinthapar1910/mealcount.org',
        ],
        [
            'name' => 'MedSync',
            'local_path' => 'C:/laragon/www/medsync',
            'repo' => 'jatinthapar1910/medsync',
            'description' => 'Healthcare app for clients, providers and appointments',
            'stack' => ['Next.js', 'TypeScript', 'Prisma', 'PostgreSQL'],
            'url' => 'https://github.com/jatinthapar1910/medsync',
        ],
        [
            'name' => 'Shipping',
            'local_path' => 'C:/laragon/www/shipping',
            'repo' => 'jatinthapar1910/shipping',
            'description' => 'Shipping management with Google Maps API',
            'stack' => ['Laravel', 'PHP', 'Google Maps API'],
            'url' => 'https://github.com/jatinthapar1910/shipping',
        ],
        [
            'name' => 'Simpletrics',
            'local_path' => 'C:/laragon/www/simpletrics',
            'repo' => 'jatinthapar1910/simpletrics',
            'description' => 'Analytics and reporting platform',
            'stack' => ['Laravel', 'PHP'],
            'url' => 'https://github.com/jatinthapar1910/simpletrics',
        ],
    ],
];

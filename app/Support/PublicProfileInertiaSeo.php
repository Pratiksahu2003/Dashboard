<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Server-rendered title + Open Graph / Twitter meta for public profile routes.
 * Crawlers often do not run JS; Vue Head still updates title/description/image after the API load.
 */
class PublicProfileInertiaSeo
{
    /** Same default as resources/js/utils/publicProfileSeo.js */
    public const DEFAULT_OG_IMAGE = 'https://app.suganta.com/logo/Su250.png';

    /** @return array<string, array<string, string>> */
    public static function viewData(Request $request, string $slug, string $kind): array
    {
        $human = Str::title(str_replace('-', ' ', $slug));
        $siteName = self::siteName();
        $canonical = $request->url();

        if ($kind === 'teacher') {
            $title = "{$human} | Tutor | SuGanta";
            $description = "View {$human}'s tutoring profile on SuGanta — subjects, qualifications, teaching mode, rates, and contact options.";
            $ogType = 'profile';
        } else {
            $title = "{$human} | SuGanta";
            $description = "View {$human} on SuGanta — programs, facilities, leadership, and contact details.";
            $ogType = 'website';
        }

        $description = Str::limit($description, 160, '…');

        return [
            'inertiaPageSeo' => [
                'title' => $title,
                'description' => $description,
                'canonical_url' => $canonical,
                'og_image' => self::DEFAULT_OG_IMAGE,
                'site_name' => $siteName,
                'og_type' => $ogType,
            ],
        ];
    }

    private static function siteName(): string
    {
        $n = trim((string) config('company.name', 'SuGanta'));
        $n = preg_replace('/\.\s*$/', '', $n) ?? $n;

        return $n !== '' ? $n : 'SuGanta';
    }
}

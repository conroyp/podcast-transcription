<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate XML sitemap for all episodes
     */
    public function index(): Response
    {
        $episodes = Episode::where('transcription_status', 'completed')
            ->orderBy('published_at', 'desc')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        // Add homepage
        $xml .= '  <url>'."\n";
        $xml .= '    <loc>'.url('/').'</loc>'."\n";
        $xml .= '    <changefreq>weekly</changefreq>'."\n";
        $xml .= '    <priority>1.0</priority>'."\n";
        $xml .= '  </url>'."\n";

        // Add episodes view
        $xml .= '  <url>'."\n";
        $xml .= '    <loc>'.url('/?view=episodes').'</loc>'."\n";
        $xml .= '    <changefreq>weekly</changefreq>'."\n";
        $xml .= '    <priority>0.8</priority>'."\n";
        $xml .= '  </url>'."\n";

        // Add each episode
        foreach ($episodes as $episode) {
            $episodeUrl = url('/?view=episodes&episode='.$episode->id);

            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($episodeUrl).'</loc>'."\n";

            if ($episode->published_at) {
                $xml .= '    <lastmod>'.$episode->published_at->format('Y-m-d').'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>monthly</changefreq>'."\n";
            $xml .= '    <priority>0.6</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
    }
}

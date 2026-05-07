<?php

namespace App\Http\Controllers\Seo;

use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Actions\Seo\GenerateSitemapAction;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $action = new GenerateSitemapAction(
            type: 'index',
        );

        $xml = $action->execute();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    public function show(string $type, int $page = 1): Response
    {
        $action = new GenerateSitemapAction(
            type: $type,
            page: $page,
        );

        $xml = $action->execute();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}

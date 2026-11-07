<?php
namespace App\Controller;

use App\Service\PublicPageMetadata;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CrawlerController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(PublicPageMetadata $metadata): Response
    {
        return new Response("User-agent: *\nAllow: /\nSitemap: ".$metadata->url('app_sitemap')."\n", headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(PublicPageMetadata $metadata): Response
    {
        return $this->render('public/sitemap.xml.twig', ['pages' => $metadata->pages()], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}

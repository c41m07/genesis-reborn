<?php
namespace App\Service\Player;

use League\CommonMark\Extension\CommonMark\Node\Inline\{Image,Link};
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\{ChildNodeRendererInterface,NodeRendererInterface};
use League\CommonMark\Util\HtmlElement;

final class MessageLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string|\Stringable
    {
        if (!$node instanceof Link && !$node instanceof Image) throw new \InvalidArgumentException('Nœud de lien invalide.');
        $label = $childRenderer->renderNodes($node->children());
        if ($node instanceof Image) return $label;
        $url = $node->getUrl();
        if (!preg_match('~\Ahttps?://~i', $url) || preg_match('/[\x00-\x20\x7f]/', $url)
            || str_contains($url, '\\') || !parse_url($url, PHP_URL_HOST)) return $label;
        return new HtmlElement('a', ['href' => $url, 'rel' => 'nofollow ugc noreferrer'], $label);
    }
}

<?php
namespace App\Service\Player;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark as CommonMark;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\CommonMark\Node;
use League\CommonMark\Parser;
use League\CommonMark\Renderer;
use League\Config\ConfigurationBuilderInterface;
use Nette\Schema\Expect;

final class MessageMarkdownExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        $builder->addSchema('commonmark', Expect::structure([
            'enable_strong' => Expect::bool(true),
            'enable_em' => Expect::bool(true),
            'unordered_list_markers' => Expect::listOf('string')->default(['*', '+', '-']),
        ]));
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Seules les syntaxes validées sont enregistrées : aucun parseur HTML ou titre.
        foreach ([
            CommonMark\Parser\Block\BlockQuoteStartParser::class => 70,
            CommonMark\Parser\Block\FencedCodeStartParser::class => 50,
            CommonMark\Parser\Block\ListBlockStartParser::class => 10,
            CommonMark\Parser\Block\IndentedCodeStartParser::class => -100,
        ] as $parser => $priority) $environment->addBlockStartParser(new $parser(), $priority);

        foreach ([
            Parser\Inline\NewlineParser::class => 200,
            CommonMark\Parser\Inline\BacktickParser::class => 150,
            CommonMark\Parser\Inline\EscapableParser::class => 80,
            CommonMark\Parser\Inline\EntityParser::class => 70,
            CommonMark\Parser\Inline\AutolinkParser::class => 50,
            CommonMark\Parser\Inline\CloseBracketParser::class => 30,
            CommonMark\Parser\Inline\OpenBracketParser::class => 20,
            CommonMark\Parser\Inline\BangParser::class => 10,
        ] as $parser => $priority) $environment->addInlineParser(new $parser(), $priority);

        foreach ([
            Node\Block\Document::class => Renderer\Block\DocumentRenderer::class,
            Node\Block\Paragraph::class => Renderer\Block\ParagraphRenderer::class,
            Node\Inline\Newline::class => Renderer\Inline\NewlineRenderer::class,
            Node\Inline\Text::class => Renderer\Inline\TextRenderer::class,
            CommonMark\Node\Block\BlockQuote::class => CommonMark\Renderer\Block\BlockQuoteRenderer::class,
            CommonMark\Node\Block\FencedCode::class => CommonMark\Renderer\Block\FencedCodeRenderer::class,
            CommonMark\Node\Block\IndentedCode::class => CommonMark\Renderer\Block\IndentedCodeRenderer::class,
            CommonMark\Node\Block\ListBlock::class => CommonMark\Renderer\Block\ListBlockRenderer::class,
            CommonMark\Node\Block\ListItem::class => CommonMark\Renderer\Block\ListItemRenderer::class,
            CommonMark\Node\Inline\Code::class => CommonMark\Renderer\Inline\CodeRenderer::class,
            CommonMark\Node\Inline\Emphasis::class => CommonMark\Renderer\Inline\EmphasisRenderer::class,
            CommonMark\Node\Inline\Strong::class => CommonMark\Renderer\Inline\StrongRenderer::class,
        ] as $node => $renderer) $environment->addRenderer($node, new $renderer());

        $environment->addRenderer(CommonMark\Node\Inline\Link::class, new MessageLinkRenderer());
        $environment->addRenderer(CommonMark\Node\Inline\Image::class, new MessageLinkRenderer());
        $environment->addDelimiterProcessor(new CommonMark\Delimiter\Processor\EmphasisDelimiterProcessor('*'));
        $environment->addDelimiterProcessor(new CommonMark\Delimiter\Processor\EmphasisDelimiterProcessor('_'));
    }
}

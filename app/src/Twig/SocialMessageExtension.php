<?php
namespace App\Twig;

use App\Entity\SocialMessage;
use App\Service\Player\MessageMarkdownExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SocialMessageExtension extends AbstractExtension
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'max_delimiters_per_line' => 100,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
        $environment->addExtension(new MessageMarkdownExtension());
        $this->converter = new MarkdownConverter($environment);
    }

    public function getFilters(): array
    {
        return [new TwigFilter('social_message', $this->render(...), ['is_safe' => ['html']])];
    }

    public function render(SocialMessage $message): string
    {
        if ($message->contentFormat !== 'markdown') {
            return '<p class="message-plain">'.htmlspecialchars($message->content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }
        return (string) $this->converter->convert($message->content);
    }
}

<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ReportNoticeRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ReportNoticeExtension extends AbstractExtension
{
    public function __construct(private ReportNoticeRepository $notices)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('report_notice_count', [$this->notices, 'count']),
            new TwigFunction('report_notice_unread', [$this->notices, 'unread']),
        ];
    }
}

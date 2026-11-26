<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/** Encadre les templates générateurs sans enregistrer leur nom ni leur contexte. */
final class TwigPerformanceExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function getNodeVisitors(): array
    {
        return [new class implements NodeVisitorInterface {
            public function enterNode(Node $node, Environment $env): Node
            {
                return $node;
            }

            public function leaveNode(Node $node, Environment $env): Node
            {
                if ($node instanceof ModuleNode) {
                    $node->setNode('display_start', new Nodes([$this->marker(true), $node->getNode('display_start')]));
                    $node->setNode('display_end', new Nodes([$node->getNode('display_end'), $this->marker(false)]));
                }

                return $node;
            }

            private function marker(bool $start): Node
            {
                return new #[YieldReady] class ($start) extends Node {
                    public function __construct(bool $start)
                    {
                        parent::__construct([], ['start' => $start]);
                    }

                    public function compile(Compiler $compiler): void
                    {
                        $compiler->write('$this->extensions[')
                            ->repr(TwigPerformanceExtension::class)
                            ->raw($this->getAttribute('start') ? "]->enter();\n" : "]->leave();\n");
                    }
                };
            }

            public function getPriority(): int
            {
                return 0;
            }
        }];
    }

    public function enter(): void
    {
        $profile = $this->profile();
        if ($profile !== null) {
            $profile->enterTwig();
        }
    }

    public function leave(): void
    {
        $profile = $this->profile();
        if ($profile !== null) {
            $profile->leaveTwig();
        }
    }

    private function profile(): ?RequestPerformance
    {
        $profile = $this->requests->getMainRequest()?->attributes->get(RequestPerformance::ATTRIBUTE);

        return $profile instanceof RequestPerformance ? $profile : null;
    }
}

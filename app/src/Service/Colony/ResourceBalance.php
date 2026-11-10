<?php
namespace App\Service\Colony;

use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Une date explicite sépare les deux régimes sans réécrire les stocks existants. */
final class ResourceBalance
{
    private ?\DateTimeImmutable $effectiveAt = null;

    public function __construct(
        private GameCatalog $catalog,
        #[Autowire('%env(RESOURCE_BALANCE_EFFECTIVE_AT)%')] string $effectiveAt,
    ) {
        if ($effectiveAt === '') return;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $effectiveAt));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new \InvalidArgumentException('RESOURCE_BALANCE_EFFECTIVE_AT doit être une date ISO 8601 avec fuseau, par exemple 2026-12-01T00:00:00Z.');
        }
        $this->effectiveAt = $date;
    }

    public function effectiveAt(): ?\DateTimeImmutable { return $this->effectiveAt; }

    public function rulesAt(\DateTimeImmutable $at): array
    {
        $rules = $this->catalog->rules();
        return $this->effectiveAt === null || $at < $this->effectiveAt
            ? array_replace($rules, $rules['resource_balance_previous'] ?? [])
            : $rules;
    }
}

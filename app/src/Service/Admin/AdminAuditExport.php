<?php

namespace App\Service\Admin;

use App\Repository\AdminReadRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminAuditExport
{
    public function __construct(private AdminReadRepository $reads) {}

    public function response(array $filters): StreamedResponse
    {
        return new StreamedResponse(function () use ($filters): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Administrateur', 'Action', 'Cible', 'Détails'], ';', '"', '', "\r\n");
            foreach ($this->reads->auditExport($filters) as $row) {
                $cells = [$row['createdAt']->format('d/m/Y H:i:s'), $row['actorName'] ?? 'Compte supprimé',
                    $row['action'], $row['targetType'].' #'.$row['targetId'], json_encode($row['details'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                fputcsv($stream, array_map(self::cell(...), $cells), ';', '"', '', "\r\n");
            }
            fclose($stream);
        }, 200, ['Content-Type'=>'text/csv; charset=UTF-8', 'Cache-Control'=>'private, no-store',
            'Content-Disposition'=>'attachment; filename="genesis-admin-journal-'.gmdate('Y-m-d').'.csv"']);
    }

    private static function cell(string $value): string
    {
        // Préfixer les caractères de formule pour que le tableur conserve ces cellules en texte.
        $value = trim(preg_replace('/\R/u', ' ', $value));
        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}

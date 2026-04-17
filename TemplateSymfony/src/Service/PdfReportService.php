<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Service de génération de rapports PDF.
 *
 * Implémentation en deux modes :
 *
 * 1) Si Dompdf est installé (`composer require dompdf/dompdf`), on le détecte
 *    dynamiquement via class_exists et on rend en PDF réel.
 * 2) Sinon, fallback : on renvoie le HTML brut avec un header approprié.
 *    Suffisant pour le MVP (impression navigateur -> PDF).
 */
final class PdfReportService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{body: string, contentType: string, mode: 'pdf'|'html'}
     */
    public function renderReport(string $template, array $context, string $filename = 'report.pdf'): array
    {
        $html = $this->twig->render($template, $context);

        if (class_exists('\\Dompdf\\Dompdf')) {
            // @phpstan-ignore-next-line (class de dépendance optionnelle)
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return [
                'body' => (string) $dompdf->output(),
                'contentType' => 'application/pdf',
                'mode' => 'pdf',
            ];
        }

        $this->logger->info('[PDF] Dompdf absent, fallback HTML.');

        return [
            'body' => $html,
            'contentType' => 'text/html; charset=utf-8',
            'mode' => 'html',
        ];
    }
}

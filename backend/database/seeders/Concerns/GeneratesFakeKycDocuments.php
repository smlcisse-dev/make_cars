<?php

namespace Database\Seeders\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Justificatifs KYC factices pour les seeders de test (inscriptions,
 * catalogue) : un vrai PDF minimal mais structurellement valide (pas juste
 * un fichier de taille arbitraire avec un mimeType forcé, que
 * UploadedFile::fake()->create() produirait — un lecteur PDF strict, ex.
 * Firefox, l'ouvrirait avec « 0 sur 0 pages »).
 */
trait GeneratesFakeKycDocuments
{
    private function fakeBusinessRegistrationDocument(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'registre-commerce.pdf',
            $this->minimalPdfContent('Registre de commerce - Document de test (Make Cars)'),
        );
    }

    /**
     * Certificat d'Identification Personnelle factice (CLAUDE.md §5, ajout
     * v0.27), exigé à la soumission du dossier.
     */
    private function fakeIdentityCertificateDocument(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'certificat-identification-personnelle.pdf',
            $this->minimalPdfContent('Certificat d Identification Personnelle - Document de test (Make Cars)'),
        );
    }

    private function fakePremisesPhoto(): UploadedFile
    {
        return UploadedFile::fake()->image('photo-local.jpg', 640, 480);
    }

    private function fakeCatalogImage(string $filename): UploadedFile
    {
        return UploadedFile::fake()->image($filename, 640, 480);
    }

    /**
     * Construit un PDF 1.4 minimal (une page, un bloc de texte) : catalogue,
     * arbre de pages, page, police, flux de contenu, puis table xref dont
     * les offsets sont calculés à partir de la position réelle de chaque
     * objet dans le flux généré, plutôt que codés en dur.
     */
    private function minimalPdfContent(string $text): string
    {
        $stream = "BT /F1 12 Tf 20 100 Td ({$text}) Tj ET";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($stream).' >>'."\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $entryCount = count($objects) + 1;

        $xref = "xref\n0 {$entryCount}\n0000000000 65535 f \n";
        foreach ($objects as $id => $body) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf.$xref."trailer\n<< /Size {$entryCount} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }
}

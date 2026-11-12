<?php

namespace App\Services\Subscriptions;

use App\Models\Contract;
use App\Support\Settings;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genereaza PDF-ul contractului cu aceeasi structura ca pdf_build() din simulator:
 * rezumat, nota de valabilitate, textul contractului (sablonul aprobat, neschimbat),
 * anexa cu tabelul de servicii si semnatura beneficiarului.
 */
class ContractPdf
{
    /** Completeaza cele patru randuri cu spatii libere din sablon, exact ca in sursa (SIM:676-690). */
    public function filledLines(Contract $contract): array
    {
        $p = $contract->payload;
        $species = $contract->species === 'dog' ? 'Caine' : 'Pisica';
        $sex = ($p['sex'] ?? '') === 'male' ? 'Mascul' : 'Femela';

        return array_map(function (string $line) use ($p, $species, $sex) {
            if (str_starts_with($line, 'Dl./Dna.')) {
                return 'Dl./Dna. '.$p['owner_name'].', CNP '.$p['owner_cnp'].', domiciliat(a) in '.$p['owner_addr'].',';
            }
            if (str_starts_with($line, 'identificat')) {
                return 'identificat(a) cu CI seria '.$p['owner_ci_serie'].' nr. '.$p['owner_ci_nr'].', telefon '.$p['owner_phone'].', e-mail '.$p['owner_email'].', denumit(a) in continuare "Beneficiarul", avand in posesie animalul identificat prin';
            }
            if (str_starts_with($line, 'Nume animal')) {
                return 'Nume animal '.$p['pet_name'].'  Specie '.$species.'  Rasa '.$p['breed'];
            }
            if (str_starts_with($line, 'Sex ')) {
                return 'Sex '.$sex.'  Greutate '.$p['weight'].' kg  Microcip/Nr. Carnet de sanatate '.($p['pet_microchip'] ?? '').'.';
            }
            if (str_starts_with($line, 'Nr. ____')) {
                return 'Nr. '.$p['contract_number'].' / Data '.$p['contract_date'];
            }

            return $line;
        }, $contract->template->lines);
    }

    public function render(Contract $contract, ?string $signaturePngPath = null): string
    {
        $contract->loadMissing('template');

        $signature = $signaturePngPath && is_file($signaturePngPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($signaturePngPath))
            : null;

        $html = view('pdf.contract', [
            'contract' => $contract,
            'p' => $contract->payload,
            'lines' => $this->filledLines($contract),
            'signature' => $signature,
            'headerTitle' => 'HIKEVET - ABONAMENTE',
            'headerSub' => 'Contract abonament medical veterinar',
            'validDays' => $contract->priceList?->contract_valid_days ?? 5,
            'legalName' => Settings::get('clinic.legal_name'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [storage_path('app'), resource_path()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}

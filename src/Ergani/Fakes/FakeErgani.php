<?php

namespace Sonole\GovGr\Ergani\Fakes;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Sonole\GovGr\Ergani\Data\EmploymentHistoryEntry;
use Sonole\GovGr\Ergani\Data\EmploymentRelation;
use Sonole\GovGr\Ergani\Data\EmploymentStatus;
use Sonole\GovGr\Ergani\Ergani;
use Throwable;

/**
 * Ergani without the network: install it with Ergani::fake(). Every method answers from the AFM alone, writes
 * no audit record and needs no credentials.
 *
 * Any 9-digit AFM is employed full time on an indefinite contract by an employer derived from the AFM; the
 * history has that one job covering the whole period asked. Anything else has no current job (null), an
 * unknown status and an empty history. Override single AFMs with $relations, $statuses and $history, each
 * keyed by AFM: the object (or null / a list of objects), the service's record array (current / sev / a list
 * of hist records), or a Throwable to throw.
 */
class FakeErgani extends Ergani
{
    /**
     * @param array<string, EmploymentRelation|array<string, mixed>|Throwable|null> $relations
     * @param array<string, EmploymentStatus|array<string, mixed>|Throwable> $statuses
     * @param array<string, list<EmploymentHistoryEntry>|list<array<string, mixed>>|Throwable> $history
     */
    public function __construct(
        private readonly array $relations = [],
        private readonly array $statuses = [],
        private readonly array $history = [],
    ) {
    }

    public function employeeRelation(string $afm, ?Request $request = null): ?EmploymentRelation
    {
        if (array_key_exists($afm, $this->relations)) {
            $response = $this->throwIfThrowable($this->relations[$afm]);

            return is_array($response) ? EmploymentRelation::fromRecord($response) : $response;
        }

        if (! $this->isEmployed($afm)) {
            return null;
        }

        return EmploymentRelation::fromRecord([
            'employerTaxNumber' => $this->employerAfm($afm), 'employerName' => $this->employerName($afm),
            'employeeTaxNumber' => $afm, 'employeeSalary' => '1500.00', 'employeeHourSalary' => '9.00', 'employeeWeekHours' => '40.0',
        ]);
    }

    public function employmentRelationship(string $afm, string $refdate, ?Request $request = null): EmploymentStatus
    {
        $response = $this->throwIfThrowable($this->statuses[$afm] ?? null);

        if ($response instanceof EmploymentStatus) {
            return $response;
        }

        if (is_array($response)) {
            return EmploymentStatus::fromRecord($response);
        }

        return EmploymentStatus::fromRecord($this->isEmployed($afm) ? ['kind' => '0', 'typos' => '0'] : ['kind' => '-1', 'typos' => '-1']);
    }

    public function employmentHistoryByPeriod(string $afm, string $refdateFrom, string $refdateTo, ?Request $request = null): array
    {
        $response = $this->throwIfThrowable($this->history[$afm] ?? null);

        if (is_array($response)) {
            return array_map(fn ($item) => $item instanceof EmploymentHistoryEntry ? $item : EmploymentHistoryEntry::fromRecord($item), $response);
        }

        if (! $this->isEmployed($afm)) {
            return [];
        }

        $from = Carbon::createFromFormat('!d/m/Y', $refdateFrom) ?: now()->startOfYear();
        $to = Carbon::createFromFormat('!d/m/Y', $refdateTo) ?: now();

        return [EmploymentHistoryEntry::fromRecord([
            'merTaxId' => $this->employerAfm($afm), 'merName' => $this->employerName($afm),
            'aaMain' => '0', 'stakodMainCode' => '6201', 'stakodMainDescr' => 'Δραστηριότητες προγραμματισμού ηλεκτρονικών συστημάτων',
            'addressMain' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ', 'addressArithmosMain' => '10', 'tkMain' => '12345',
            'kalDhmoiMainCode' => '9186', 'kalDhmoiMainDescr' => 'ΑΘΗΝΑΙΩΝ',
            'nomMorfMainCode' => '007', 'nomMorfMainDescr' => 'ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ (ΙΚΕ)',
            'aaMpa' => '0', 'stakodMpaCode' => '6201', 'stakodMpaDescr' => 'Δραστηριότητες προγραμματισμού ηλεκτρονικών συστημάτων',
            'addressMpa' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ', 'addressArithmosMpa' => '10', 'tkMpa' => '12345',
            'kalDhmoiMpaCode' => '9186', 'kalDhmoiMpaDescr' => 'ΑΘΗΝΑΙΩΝ',
            'step2Code' => '21', 'step2Descr' => 'ΦΥΣΙΚΟΙ, ΜΑΘΗΜΑΤΙΚΟΙ ΚΑΙ ΑΣΚΟΥΝΤΕΣ ΣΥΝΑΦΗ ΕΠΑΓΓΕΛΜΑΤΑ',
            'step3Code' => '213', 'step3Descr' => 'ΠΡΟΣΩΠΑ ΠΟΥ ΑΝΑΠΤΥΣΣΟΥΝ ΕΠΑΓΓΕΛΜΑΤΙΚΗ ΔΡΑΣΤΗΡΙΟΤΗΤΑ ΣΤΟΝ ΤΟΜΕΑ ΤΗΣ ΠΛΗΡΟΦΟΡΙΚΗΣ',
            'step4Code' => '2131', 'step4Descr' => 'ΣΧΕΔΙΑΣΤΕΣ, ΑΝΑΛΥΤΕΣ ΚΑΙ ΠΡΟΓΡΑΜΜΑΤΙΣΤΕΣ ΣΥΣΤΗΜΑΤΩΝ ΥΠΟΛΟΓΙΣΤΩΝ',
            'step6Code' => '213100', 'step6Descr' => 'ΣΧΕΔΙΑΣΤΕΣ, ΑΝΑΛΥΤΕΣ ΚΑΙ ΠΡΟΓΡΑΜΜΑΤΙΣΤΕΣ ΣΥΣΤΗΜΑΤΩΝ ΥΠΟΛΟΓΙΣΤΩΝ',
            'kathestws' => '0', 'apodoxes' => '1500.00', 'weekHours' => '40.0',
            'dateFrom' => $from->toIso8601String(), 'dateTo' => $to->toIso8601String(),
        ])];
    }

    private function isEmployed(string $afm): bool
    {
        return preg_match('/^\d{9}$/', $afm) === 1;
    }

    private function employerAfm(string $afm): string
    {
        return '099'.str_pad((string) (crc32($afm) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function employerName(string $afm): string
    {
        $names = ['ΔΟΚΙΜΑΣΤΙΚΗ ΑΕ', 'ΔΟΚΙΜΑΣΤΙΚΗ ΙΚΕ', 'ΔΟΚΙΜΑΣΤΙΚΗ ΕΠΕ', 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΕ'];

        return $names[crc32($afm) % 4];
    }

    /** @throws Throwable */
    private function throwIfThrowable(mixed $response): mixed
    {
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}

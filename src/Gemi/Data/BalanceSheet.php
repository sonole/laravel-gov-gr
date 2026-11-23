<?php

namespace Sonole\GovGr\Gemi\Data;

use Sonole\GovGr\Core\Support\Normalize;

/**
 * One fiscal year's filed financial statements: an item of Gemi::balanceSheets() (companyBalanceSheets).
 * A year with nothing filed carries only `year` and an empty `files`; the figures are null until the registry
 * has them. `year` stays a string: besides "2021" the registry also sends labels like "Από 1/1 έως 31/12".
 */
final class BalanceSheet
{
    /**
     * @param list<BalanceSheetFile> $files
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        public readonly string $year,
        /** Καθαρό ύψος κύκλου εργασιών. */
        public readonly ?float $netTurnover,
        /** Σύνολο ενεργητικού. */
        public readonly ?float $totalAssets,
        /** Μέσος όρος απασχολούμενων (numStuff). */
        public readonly ?float $averageEmployees,
        public readonly ?float $inventories,
        /** Απαιτήσεις. */
        public readonly ?float $accountsReceivable,
        /** Προκαταβολές και έσοδα εισπρακτέα. */
        public readonly ?float $collectedAdvancesRevenue,
        /** Σύνολο καθαρής θέσης (sumAssets). */
        public readonly ?float $equity,
        /** Σύνολο υποχρεώσεων (sumLiabilities). */
        public readonly ?float $liabilities,
        /** Αποτελέσματα προ φόρων. */
        public readonly ?float $preTaxResults,
        /** Πάγια. */
        public readonly ?float $fixedAssets,
        public readonly bool $isXbrl,
        public readonly ?string $ixbrlUrl,
        public readonly array $files,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array; shared by every item of the same call. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    public function hasFiles(): bool
    {
        return $this->files !== [];
    }

    /**
     * @param array<string, mixed> $record one companyBalancesheets item
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        $number = fn (string $key): ?float => Normalize::number($record[$key] ?? null);

        return new self(
            year: (string) Normalize::field($record, 'year'),
            netTurnover: $number('netTurnover'),
            totalAssets: $number('totalAssets'),
            averageEmployees: $number('numStuff'),
            inventories: $number('inventories'),
            accountsReceivable: $number('accountsReceivable'),
            collectedAdvancesRevenue: $number('collectedAdvancesRevenue'),
            equity: $number('sumAssets'),
            liabilities: $number('sumLiabilities'),
            preTaxResults: $number('preTaxResults'),
            fixedAssets: $number('fixedAssets'),
            isXbrl: Normalize::bool($record['isXbrl'] ?? null) === true,
            ixbrlUrl: Normalize::field($record, 'ixbrlUrl'),
            files: array_map([BalanceSheetFile::class, 'fromRecord'], Normalize::list($record['files'] ?? null)),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}

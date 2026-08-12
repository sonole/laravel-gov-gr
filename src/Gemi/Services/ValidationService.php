<?php

namespace Sonole\GovGr\Gemi\Services;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Services\AuditedSoapService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Sonole\GovGr\Gemi\Exceptions\AfmNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class ValidationService extends AuditedSoapService
{
    protected function service(): GovService
    {
        return GovService::Gemi;
    }

    protected function configKey(): string
    {
        return 'gemi';
    }

    protected function section(): ?string
    {
        return 'validation';
    }

    protected function namespacePrefix(): string
    {
        return 'gem';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/gemiValidationInterface';
    }

    /**
     * checkAfmExists - confirms an AFM exists in the registry and returns its companies.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function validate(string $afm, ?Request $request = null): array
    {
        return $this->call('checkAfmExists', XmlHelper::tag('afm', $afm), $request);
    }

    /**
     * All valid GEMI numbers (padded to 12 digits) across every company the
     * registry returns for $afm, branches included. Empty if $afm has no
     * companies, or none has a usable gemiNumber.
     *
     * @return string[]
     *
     * @throws Throwable
     */
    public function getGemiNumbers(string $afm, ?Request $request = null): array
    {
        $gemiNumbers = [];

        foreach ($this->companiesFor($afm, $request) as $company) {
            $gemiNumber = $this->normalizeGemiNumber(data_get($company, 'gemiNumber'));

            if ($gemiNumber !== null) {
                $gemiNumbers[] = $gemiNumber;
            }
        }

        return $gemiNumbers;
    }

    /**
     * The GEMI number (padded to 12 digits) of the first non-branch company
     * found for $afm, or null if $afm has no companies, none is a
     * non-branch company, or its gemiNumber isn't usable.
     *
     * @throws Throwable
     */
    public function getGemiNumber(string $afm, ?Request $request = null): ?string
    {
        $company = $this->companiesFor($afm, $request)->first(fn (array $company) => data_get($company, 'isBranch') === 'false');

        return $company ? $this->normalizeGemiNumber(data_get($company, 'gemiNumber')) : null;
    }

    /** @throws Throwable */
    public function resolveGemiNumber(string $afm, ?Request $request = null): string
    {
        return $this->getGemiNumber($afm, $request)
            ?? throw (new AfmNotFoundException("No non-branch company with a usable GEMI number was found for AFM [$afm]."))
                ->withService($this->service());
    }

    /**
     * Normalizes companies.company into a list even when the registry
     * returns a single company (the raw XML->array conversion collapses a
     * single <company> to an associative array instead of a list).
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @throws Throwable
     */
    private function companiesFor(string $afm, ?Request $request): Collection
    {
        $companies = data_get($this->validate($afm, $request), 'result.companies.company', []);

        if (empty($companies) || ! is_array($companies)) {
            return collect();
        }

        if (isset($companies['gemiNumber'])) {
            $companies = [$companies];
        }

        return collect($companies);
    }

    /** Rejects anything that isn't a plausible GEMI number before padding it to 12 digits. */
    private function normalizeGemiNumber(mixed $gemiNumber): ?string
    {
        $gemiNumber = (string) $gemiNumber;

        if ($gemiNumber === '' || ! is_numeric($gemiNumber) || strlen($gemiNumber) > 12) {
            return null;
        }

        return str_pad($gemiNumber, 12, '0', STR_PAD_LEFT);
    }
}

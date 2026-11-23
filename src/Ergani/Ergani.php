<?php

namespace Sonole\GovGr\Ergani;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Services\AuditedSoapService;
use Sonole\GovGr\Core\Support\Normalize;
use Sonole\GovGr\Core\Support\XmlHelper;
use Sonole\GovGr\Ergani\Data\EmploymentHistoryEntry;
use Sonole\GovGr\Ergani\Data\EmploymentRelation;
use Sonole\GovGr\Ergani\Data\EmploymentStatus;
use Illuminate\Http\Request;
use Throwable;

class Ergani extends AuditedSoapService
{
    protected function service(): GovService
    {
        return GovService::Ergani;
    }

    protected function configKey(): string
    {
        return 'ergani';
    }

    protected function namespacePrefix(): string
    {
        return 'erg';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/ErganiInterface';
    }

    /**
     * getEmployeeRelation - the employee's current job for $afm: employer, monthly salary, hourly wage and
     * weekly hours. Null when the service answers without a current record. The record as returned is on ->raw.
     *
     * @throws Throwable
     */
    public function employeeRelation(string $afm, ?Request $request = null): ?EmploymentRelation
    {
        $response = $this->lookup('getEmployeeRelation', XmlHelper::tag('afm', $afm), $request);
        $record = $this->first($response['result'], 'current');

        return $record === null ? null : EmploymentRelation::fromRecord($record, $response['audit_record']);
    }

    /**
     * getEmploymentRelationship - the kind (indefinite, fixed-term, project) and type (full-time, part-time,
     * rotating) of employment for $afm as of $refdate (DD/MM/YYYY). Either is null when the service sent a code
     * outside the documented ones; the sev record as returned is on ->raw.
     *
     * @throws Throwable
     */
    public function employmentRelationship(string $afm, string $refdate, ?Request $request = null): EmploymentStatus
    {
        $body = XmlHelper::tag('refdate', $refdate).XmlHelper::tag('afm', $afm);
        $response = $this->lookup('getEmploymentRelationship', $body, $request);

        return EmploymentStatus::fromRecord($this->first($response['result'], 'sev') ?? [], $response['audit_record']);
    }

    /**
     * getEmploymentHistoryByPeriod - every period of employment of $afm between $refdateFrom and $refdateTo
     * (DD/MM/YYYY): employer, workplace, occupation, pay, hours and dates. Every item shares the call's
     * ->auditRecord.
     *
     * @return list<EmploymentHistoryEntry>
     *
     * @throws Throwable
     */
    public function employmentHistoryByPeriod(string $afm, string $refdateFrom, string $refdateTo, ?Request $request = null): array
    {
        $body = XmlHelper::tag('afm', $afm).XmlHelper::tag('refdateFrom', $refdateFrom).XmlHelper::tag('refdateTo', $refdateTo);
        $response = $this->lookup('getEmploymentHistoryByPeriod', $body, $request);
        $entries = [];

        foreach (Normalize::list($response['result']) as $doc) {
            foreach (Normalize::list($doc['hist'] ?? null) as $hist) {
                $entries[] = EmploymentHistoryEntry::fromRecord($hist, $response['audit_record']);
            }
        }

        return $entries;
    }

    /**
     * The $key record of the first doc. The spec calls doc "a list of records (usually one)", so a single doc
     * comes as the record itself and several as a list.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>|null
     */
    private function first(array $doc, string $key): ?array
    {
        $docs = Normalize::list($doc);
        $record = Normalize::list($docs[0][$key] ?? null);

        return $record[0] ?? null;
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    private function lookup(string $operation, string $body, ?Request $request): array
    {
        return $this->call($operation, $body, $request, function (array $output) {
            $code = data_get($output, 'code');

            if ($code !== '000') {
                throw $this->newException(data_get($output, 'message', "ERGANI error code $code."));
            }

            return data_get($output, 'doc', []);
        });
    }
}

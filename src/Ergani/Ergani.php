<?php

namespace Sonole\GovGr\Ergani;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Services\AuditedSoapService;
use Sonole\GovGr\Core\Support\XmlHelper;
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
     * getEmployeeRelation - the employee's current employment relationship for $afm, if any.
     *
     * Example:
     * ['result' => ['current' => [
     *     'employerTaxNumber' => '099028220',
     *     'employerName' => 'ΑΚΑΔΗΜΑΙΚΟ ΔΙΑΔΙΚΤΥΟ',
     *     'employeeTaxNumber' => '161182011',
     *     'employeeSalary' => '2600.00',
     *     'employeeHourSalary' => '15.60',
     *     'employeeWeekHours' => '40.0',
     * ]]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function employeeRelation(string $afm, ?Request $request = null): array
    {
        $body = XmlHelper::tag('afm', $afm);

        return $this->lookup('getEmployeeRelation', $body, $request);
    }

    /**
     * getEmploymentRelationship - the type and status of employment for $afm as of $refdate.
     *
     * Example:
     * ['result' => ['sev' => ['kind' => '1', 'typos' => '0']]]
     *
     * kind (Σχέση Απασχόλησης): -1 unknown, 0 indefinite, 1 fixed-term, 2 fixed-term project.
     * typos (Καθεστώς Απασχόλησης): -1 unknown, 0 full-time, 1 part-time, 2 rotating/shift.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function employmentRelationship(string $afm, string $refdate, ?Request $request = null): array
    {
        $body = XmlHelper::tag('refdate', $refdate).XmlHelper::tag('afm', $afm);

        return $this->lookup('getEmploymentRelationship', $body, $request);
    }

    /**
     * getEmploymentHistoryByPeriod - employment history entries for $afm between $refdateFrom and $refdateTo.
     *
     * Example:
     * ['result' => ['hist' => [[
     *     'merTaxId' => '802660454', 'merName' => 'WOW BURGERS HELLAS ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ',
     *     'aaMain' => '0', 'stakodMainCode' => '5611', 'stakodMainDescr' => 'Δραστηριότητες εστιατορίων',
     *     'addressMain' => 'ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ', 'addressArithmosMain' => '11', 'tkMain' => '15232',
     *     'kalDhmoiMainCode' => '9178', 'kalDhmoiMainDescr' => 'ΧΑΛΑΝΔΡΙΟΥ',
     *     'nomMorfMainCode' => '007', 'nomMorfMainDescr' => 'ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ (ΙΚΕ)',
     *     'aaMpa' => '0', 'stakodMpaCode' => '5611', 'stakodMpaDescr' => 'Δραστηριότητες εστιατορίων',
     *     'addressMpa' => 'ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ', 'addressArithmosMpa' => '11', 'tkMpa' => '15232',
     *     'kalDhmoiMpaCode' => '9178', 'kalDhmoiMpaDescr' => 'ΧΑΛΑΝΔΡΙΟΥ',
     *     'step3Code' => '514', 'step3Descr' => 'ΜΑΓΕΙΡΟΙ', 'step2Code' => '51',
     *     'step2Descr' => 'ΑΠΑΣΧΟΛΟΥΜΕΝΟΙ ΣΤΗΝ ΠΑΡΟΧΗ ΠΡΟΣΩΠΙΚΩΝ ΥΠΗΡΕΣΙΩΝ',
     *     'step4Code' => '5140', 'step4Descr' => 'ΜΑΓΕΙΡΟΙ', 'step6Code' => '514002', 'step6Descr' => 'ΜΑΓΕΙΡΟΙ, ΓΕΝΙΚΑ',
     *     'kathestws' => '0', 'apodoxes' => '880.00', 'weekHours' => '40.0',
     *     'dateFrom' => '2025-11-24T00:00:00+02:00', 'dateTo' => '2025-11-27T17:12:00.85+02:00',
     * ]]]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function employmentHistoryByPeriod(string $afm, string $refdateFrom, string $refdateTo, ?Request $request = null): array
    {
        $body = XmlHelper::tag('afm', $afm).XmlHelper::tag('refdateFrom', $refdateFrom).XmlHelper::tag('refdateTo', $refdateTo);

        return $this->lookup('getEmploymentHistoryByPeriod', $body, $request);
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

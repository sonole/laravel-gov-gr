<?php

namespace Sonole\GovGr\TaxRegistry\Services;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Services\AuditedSoapService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Request;
use Throwable;

class RegistryService extends AuditedSoapService
{
    protected function service(): GovService
    {
        return GovService::TaxRegistry;
    }

    protected function configKey(): string
    {
        return 'tax-registry';
    }

    protected function section(): ?string
    {
        return 'registry';
    }

    protected function namespacePrefix(): string
    {
        return 'reg';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/RegistryInterface';
    }

    /** registryService uses one shared "{op}Record" tag for both input and output. */
    protected function inputRecordTag(string $operation): string
    {
        return "{$operation}Record";
    }

    protected function outputRecordKey(string $operation): string
    {
        return "{$operation}Record";
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function retrieveInfosByADT(string $adt, int $kind, ?Request $request = null): array
    {
        $body = XmlHelper::tag('adt', $adt).XmlHelper::tag('kind', (string) $kind);

        return $this->call('retrieveInfosByADT', $body, $request);
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function retrieveInfoByName(
        string $surnameA,
        ?string $fathersFirstName = null,
        ?string $surnameB = null,
        ?string $nameA = null,
        ?string $nameB = null,
        ?string $mothersFirstName = null,
        ?string $birthYear = null,
        ?string $birthMonth = null,
        ?string $birthDay = null,
        ?string $carPlateNumber = null,
        ?Request $request = null,
    ): array {
        $body = XmlHelper::tag('surnameA', $surnameA)
            .XmlHelper::optionalTag('surnameB', $surnameB)
            .XmlHelper::optionalTag('nameA', $nameA)
            .XmlHelper::optionalTag('nameB', $nameB)
            .XmlHelper::optionalTag('fathersFirstName', $fathersFirstName)
            .XmlHelper::optionalTag('mothersFirstName', $mothersFirstName)
            .XmlHelper::optionalTag('birthYear', $birthYear)
            .XmlHelper::optionalTag('birthMonth', $birthMonth)
            .XmlHelper::optionalTag('birthDay', $birthDay)
            .XmlHelper::optionalTag('carPlateNumber', $carPlateNumber);

        return $this->call('retrieveInfoByName', $body, $request);
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function retrieveInfoByAFM(
        string $afm,
        ?string $firmActivitiesRequest = null,
        ?string $membershipsRequest = null,
        ?string $relationsRequest = null,
        ?string $symmetoxesRequest = null,
        ?string $sysxetiseisRequest = null,
        ?string $branchActivitiesRequest = null,
        ?string $branchAbroadRequest = null,
        ?string $firmAbroadRequest = null,
        ?Request $request = null,
    ): array {
        $body = XmlHelper::tag('afm', $afm)
            .XmlHelper::optionalTag('firmActivitiesRequest', $firmActivitiesRequest)
            .XmlHelper::optionalTag('membershipsRequest', $membershipsRequest)
            .XmlHelper::optionalTag('relationsRequest', $relationsRequest)
            .XmlHelper::optionalTag('symmetoxesRequest', $symmetoxesRequest)
            .XmlHelper::optionalTag('sysxetiseisRequest', $sysxetiseisRequest)
            .XmlHelper::optionalTag('branchActivitiesRequest', $branchActivitiesRequest)
            .XmlHelper::optionalTag('branchAbroadRequest', $branchAbroadRequest)
            .XmlHelper::optionalTag('firmAbroadRequest', $firmAbroadRequest);

        return $this->call('retrieveInfoByAFM', $body, $request);
    }
}

<?php

namespace Sonole\GovGr\TaxRegistry;

use Sonole\GovGr\TaxRegistry\Services\PublicLookupService;
use Sonole\GovGr\TaxRegistry\Services\RegistryService;
use Illuminate\Http\Request;
use Throwable;

class TaxRegistry
{
    public function __construct(
        private readonly RegistryService $registry,
        private readonly PublicLookupService $public,
    ) {
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function retrieveInfosByADT(string $adt, int $kind, ?Request $request = null): array
    {
        return $this->registry->retrieveInfosByADT($adt, $kind, $request);
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
        return $this->registry->retrieveInfoByName(
            $surnameA, $fathersFirstName, $surnameB, $nameA, $nameB,
            $mothersFirstName, $birthYear, $birthMonth, $birthDay, $carPlateNumber, $request,
        );
    }

    /**
     * retrieveInfoByAFM - registry record for $afm (natural person or firm): basic info plus a
     * set of related-record arrays (activities, memberships, relations, branches, etc), each
     * requestable via the corresponding *Request flag and empty when not requested/found.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'basicInfo' => [
     *         'afm' => '000000000', 'doy' => '0000', 'doyDescr' => 'ΔΕΙΓΜΑ ΔΟΥ',
     *         'iNiFlag' => '1', 'deactivationFlag' => '1',
     *         'onomasia' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ,,ΓΙΑΝΝΗΣ,ΠΕΤΡΟΣ', 'mothersFirstName' => 'ΜΑΡΙΑ',
     *         'birthDate' => '1990-01-01T00:00:00+02:00', 'indPhone' => '2100000000', 'cntResidenceDescr' => 'ΕΛΛΑΔΑ',
     *         'residenceAddress' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'residenceAddressNo' => '1', 'residenceZipCode' => '10000',
     *         'residenceParDescr' => 'ΔΕΙΓΜΑ',
     *         'firmDoy' => '0000', 'firmAddress' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'firmAddressNo' => '1',
     *         'firmZipCode' => '10000', 'firmParDescr' => 'ΔΕΙΓΜΑ', 'firmPhone' => '2100000000',
     *     ],
     *     'firmActivitiesArray' => [], 'membershipsArray' => [], 'relationsArray' => [], 'symmetoxesArray' => [],
     *     'sysxetiseisArray' => [], 'branchArray' => [], 'branchActivitiesArray' => [],
     *     'branchAbroadArray' => [], 'firmAbroad' => [],
     * ]]
     *
     * onomasia is comma-joined as "surname,,firstName,fatherName" (for a natural person). Note the
     * double comma where a middle field is unused. This is real, sensitive personal data - handle with care.
     *
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
        return $this->registry->retrieveInfoByAFM(
            $afm, $firmActivitiesRequest, $membershipsRequest, $relationsRequest, $symmetoxesRequest,
            $sysxetiseisRequest, $branchActivitiesRequest, $branchAbroadRequest, $firmAbroadRequest, $request,
        );
    }

    /**
     * lookupByAfm - AADE's public RgWsPublic2 AFM lookup (a lighter, differently-shaped
     * record than retrieveInfoByAFM's: snake_case fields, no auditRecord/ΚΕΔ envelope).
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'call_seq_id' => '000000000',
     *     'error_rec' => ['error_code' => [], 'error_descr' => []],
     *     'afm_called_by_rec' => [
     *         'token_username' => 'USERNAME@USERNAME', 'token_afm' => '000000000', 'token_afm_fullname' => 'ΔΕΙΓΜΑ ΑΕ',
     *         'afm_called_by' => '000000000', 'afm_called_by_fullname' => 'ΔΕΙΓΜΑ ΑΕ', 'as_on_date' => '2026-01-01',
     *     ],
     *     'basic_rec' => [
     *         'afm' => '000000000', 'doy' => '0000', 'doy_descr' => 'ΔΕΙΓΜΑ ΔΟΥ', 'i_ni_flag_descr' => 'ΜΗ ΦΠ',
     *         'deactivation_flag' => '1', 'deactivation_flag_descr' => 'ΕΝΕΡΓΟΣ ΑΦΜ', 'firm_flag_descr' => 'ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ',
     *         'onomasia' => 'ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΙΑ', 'commer_title' => [], 'legal_status_descr' => 'ΑΕ',
     *         'postal_address' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'postal_address_no' => '1', 'postal_zip_code' => '10000',
     *         'postal_area_description' => 'ΔΕΙΓΜΑ', 'regist_date' => '2000-01-01', 'stop_date' => [],
     *         'normal_vat_system_flag' => 'Y',
     *     ],
     *     'firm_act_tab' => ['item' => [
     *         ['firm_act_code' => '00000000', 'firm_act_descr' => 'ΔΕΙΓΜΑ', 'firm_act_kind' => '1', 'firm_act_kind_descr' => 'ΚΥΡΙΑ'],
     *     ]],
     * ]]
     *
     * Audit logging for this one is opt-in (see PublicLookupService::auditingEnabled()),
     * so audit_record is null unless TAX_REGISTRY_PUBLIC_AUDIT_ENABLED=true.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>|null}
     *
     * @throws Throwable
     */
    public function lookupByAfm(string $afm, ?string $afmCalledBy = null, ?Request $request = null): array
    {
        return $this->public->lookup($afm, $afmCalledBy, $request);
    }
}
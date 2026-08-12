<?php

namespace Sonole\GovGr\Amka;

use Sonole\GovGr\Amka\Services\AmkaInfoService;
use Sonole\GovGr\Amka\Services\AmkaRelationshipService;
use Illuminate\Http\Request;
use Throwable;

class Amka
{
    public function __construct(
        private readonly AmkaRelationshipService $relationship,
        private readonly AmkaInfoService $info,
    ) {
    }

    /**
     * confirmRelAmka - confirms a family relationship between $relAmka and a parent.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'confirmRelAmkaOutputRecord' => ['confirmation' => 'true', 'info' => 'ΤΕΚΝΟ'],
     *     'callSequenceId' => '1', 'callSequenceDate' => '2026-01-01T00:00:00+02:00', 'errorRecord' => [],
     * ]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function confirmRelAmka(
        string $relAmka,
        ?string $parentAmka = null,
        ?string $parentFirstname = null,
        ?string $parentSurname = null,
        ?string $parentFathername = null,
        ?string $parentMothername = null,
        ?string $parentBirthdate = null,
        ?Request $request = null,
    ): array {
        return $this->relationship->confirmRelAmka(
            $relAmka, $parentAmka, $parentFirstname, $parentSurname,
            $parentFathername, $parentMothername, $parentBirthdate, $request,
        );
    }

    /**
     * confirmRelAmkaMultiple - confirms one or more relAmkas against a single parent.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'confirmRelAmkaMultipleOutputRecord' => ['confirmList' => ['item' => [
     *         ['relAmka' => '01234567890', 'confirmation' => 'true', 'info' => 'ΤΕΚΝΟ'],
     *         ['relAmka' => '09876543210', 'confirmation' => 'true', 'info' => 'ΤΕΚΝΟ'],
     *     ]]],
     *     'callSequenceId' => '1', 'callSequenceDate' => '2026-01-01T00:00:00+02:00', 'errorRecord' => [],
     * ]]
     *
     * With a single $relAmkas entry, 'item' collapses to a single associative
     * array instead of a list (same XML->array quirk as GEMI's companies). When
     * the parent can't be resolved, confirmRelAmkaMultipleOutputRecord instead
     * just has a 'message' key describing the failure.
     *
     * @param  string[]  $relAmkas
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function confirmRelAmkaMultiple(
        array $relAmkas,
        ?string $parentAmka = null,
        ?string $parentFirstname = null,
        ?string $parentSurname = null,
        ?string $parentFathername = null,
        ?string $parentMothername = null,
        ?string $parentBirthdate = null,
        ?Request $request = null,
    ): array {
        return $this->relationship->confirmRelAmkaMultiple(
            $relAmkas, $parentAmka, $parentFirstname, $parentSurname,
            $parentFathername, $parentMothername, $parentBirthdate, $request,
        );
    }

    /**
     * amka2data - person data for a given AMKA.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'amka2dataOutputRecord' => [
     *         'aaInput' => '1', 'amkaInput' => '01234567890', 'amkaCurrent' => '01234567890',
     *         'lastDataLogDate' => '01/01/2020', 'adtType' => 'Τ', 'adt' => 'Α0000000',
     *         'yearOfAdtPublished' => '2020', 'nationalityCountry' => 'ΕΛΛΑΔΑ', 'nationalityCountryCode' => 'GR',
     *         'sex' => 'ΑΡΡΕΝ', 'afm' => '000000001',
     *         'birthSurnameEl' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'idSurnameEl' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'firstNameEl' => 'ΓΙΑΝΝΗΣ',
     *         'fatherNameEl' => 'ΠΕΤΡΟΣ', 'motherNameEl' => 'ΜΑΡΙΑ',
     *         'birthSurnameEn' => 'PAPADOPOULOS', 'idSurnameEn' => 'PAPADOPOULOS', 'firstNameEn' => 'GIANNIS',
     *         'fatherNameEn' => 'PETROS', 'motherNameEn' => 'MARIA', 'birthDate' => '01/01/1990',
     *         'fictitiousBirthDateIndication' => [], 'birthCountry' => 'ΕΛΛΑΔΑ', 'birthCountryCode' => 'GR',
     *         'birthMunicipality' => 'ΑΘΗΝΑ', 'birthCountyCode' => 'ΑΤΤΙ',
     *         'addressStreetNum' => 'ΟΔΟΣ ΔΕΙΓΜΑ 1', 'addressCity' => 'ΑΘΗΝΑ', 'addressCountyCode' => 'ΑΤΤΙ',
     *         'addressPostalCode' => '10000', 'addressCountry' => 'ΕΛΛΑΔΑ', 'addressCountryCode' => 'GR',
     *         'phone1' => '2100000000', 'phone2' => [], 'deathIndication' => [], 'deathDate' => [],
     *     ],
     *     'callSequenceId' => '1', 'callSequenceDate' => '2026-01-01T00:00:00+02:00', 'errorRecord' => [],
     * ]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2data(string $amka, ?Request $request = null): array
    {
        return $this->info->amka2data($amka, $request);
    }

    /**
     * data2amka - the AMKA matching the given personal details.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => [
     *     'data2amkaOutputRecord' => [
     *         'aaInput' => '1', 'surnameInput' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'firstNameInput' => 'ΓΙΑΝΝΗΣ',
     *         'fatherNameInput' => 'ΠΕΤΡΟΣ', 'motherNameInput' => [], 'birthDateInput' => '01/01/1990',
     *         'afmInput' => [], 'adtInput' => [], 'amkaCurrent' => '01234567890', 'lastDataLogDate' => '01/01/2020',
     *         'adt' => 'Α0000000', 'nationalityCountry' => 'ΕΛΛΑΔΑ', 'nationalityCountryCode' => 'GR',
     *         'afm' => '000000001', 'birthSurnameEl' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'idSurnameEl' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ',
     *         'firstNameEl' => 'ΓΙΑΝΝΗΣ', 'fatherNameEl' => 'ΠΕΤΡΟΣ', 'motherNameEl' => 'ΜΑΡΙΑ',
     *         'birthSurnameEn' => 'PAPADOPOULOS', 'idSurnameEn' => 'PAPADOPOULOS', 'firstNameEn' => 'GIANNIS',
     *         'fatherNameEn' => 'PETROS', 'motherNameEn' => 'MARIA', 'birthDate' => '01/01/1990',
     *         'fictitiousBirthDateIndication' => [], 'deathIndication' => [], 'deathDate' => [],
     *     ],
     *     'callSequenceId' => '1', 'callSequenceDate' => '2026-01-01T00:00:00+02:00', 'errorRecord' => [],
     * ]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function data2amka(
        string $surname,
        string $firstName,
        ?string $fatherName = null,
        ?string $motherName = null,
        ?string $birthDate = null,
        ?string $afm = null,
        ?string $adt = null,
        ?Request $request = null,
    ): array {
        return $this->info->data2amka($surname, $firstName, $fatherName, $motherName, $birthDate, $afm, $adt, $request);
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataE(string $amka, ?Request $request = null): array
    {
        return $this->info->amka2dataE($amka, $request);
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataLE(string $amka, ?Request $request = null): array
    {
        return $this->info->amka2dataLE($amka, $request);
    }
}
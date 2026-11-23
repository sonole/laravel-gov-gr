<?php

namespace Sonole\GovGr\Amka;

use Illuminate\Http\Request;
use Sonole\GovGr\Amka\Data\AmkaPerson;
use Sonole\GovGr\Amka\Data\RelationshipConfirmation;
use Sonole\GovGr\Amka\Services\AmkaInfoService;
use Sonole\GovGr\Amka\Services\AmkaRelationshipService;
use Sonole\GovGr\Core\Exceptions\GovApiException;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Exceptions\GovUnavailableException;
use Sonole\GovGr\Core\Support\Failures;
use Sonole\GovGr\Rules\ValidAfm;
use Sonole\GovGr\Rules\ValidAmka;
use Throwable;

/**
 * The AMKA services. The methods that return an object keep the service's raw record on ->raw and the
 * audit_records row of the call on ->auditRecord. AMKA and AFM arguments are checked (checksum included)
 * before anything is sent, unless govgr.validate_input is off.
 */
class Amka
{
    public function __construct(
        private readonly AmkaRelationshipService $relationship,
        private readonly AmkaInfoService $info,
    ) {
    }

    /**
     * Confirms a family relationship between $relAmka and a parent. Give the parent's $parentAmka, or all of
     * the parent's names and birth date (DD/MM/YYYY).
     *
     * @throws \InvalidArgumentException for an invalid AMKA / AFM or incomplete parent details
     * @throws GovNotFoundException when the service rejects the AMKA as unknown
     * @throws GovUnavailableException credentials, transport or a server error
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
    ): RelationshipConfirmation {
        ValidAmka::ensure($relAmka, 'relAmka');

        if ($parentAmka !== null) {
            ValidAmka::ensure($parentAmka, 'parentAmka');
        }

        try {
            $response = $this->relationship->confirmRelAmka(
                $relAmka, $parentAmka, $parentFirstname, $parentSurname,
                $parentFathername, $parentMothername, $parentBirthdate, $request,
            );
        } catch (Throwable $e) {
            throw Failures::sortRejection($e);
        }

        return RelationshipConfirmation::fromRecord(
            (array) ($response['result']['confirmRelAmkaOutputRecord'] ?? []), $relAmka, $response['audit_record'],
        );
    }

    /**
     * Confirms one or more relAmkas against a single parent, with one call.
     *
     * @param  string[]  $relAmkas
     * @return list<RelationshipConfirmation> in the order the service answered, one per relAmka
     *
     * @throws \InvalidArgumentException for an invalid AMKA / AFM or incomplete parent details
     * @throws GovNotFoundException when the service rejects an AMKA as unknown
     * @throws GovUnavailableException credentials, transport or a server error
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
        foreach ($relAmkas as $relAmka) {
            ValidAmka::ensure((string) $relAmka, 'relAmkas');
        }

        if ($parentAmka !== null) {
            ValidAmka::ensure($parentAmka, 'parentAmka');
        }

        try {
            $response = $this->relationship->confirmRelAmkaMultiple(
                $relAmkas, $parentAmka, $parentFirstname, $parentSurname,
                $parentFathername, $parentMothername, $parentBirthdate, $request,
            );
        } catch (Throwable $e) {
            throw Failures::sortRejection($e);
        }

        // One item comes back as a single record, several as a list.
        $items = (array) ($response['result']['confirmRelAmkaMultipleOutputRecord']['confirmList']['item'] ?? []);
        $items = $items !== [] && ! array_is_list($items) ? [$items] : $items;

        return array_map(
            fn (array $item) => RelationshipConfirmation::fromRecord($item, null, $response['audit_record']),
            $items,
        );
    }

    /**
     * Looks up the person with this AMKA.
     *
     * @throws \InvalidArgumentException for an invalid AMKA
     * @throws GovNotFoundException when the AMKA is unknown
     * @throws GovUnavailableException credentials, transport or a server error
     */
    public function amka2data(string $amka, ?Request $request = null): AmkaPerson
    {
        ValidAmka::ensure($amka);

        return $this->person(fn () => $this->info->amka2data($amka, $request), 'amka2dataOutputRecord', "AMKA [$amka]");
    }

    /**
     * Looks up the person, and so the current AMKA (->amka), matching these details. Gender and ID type are not
     * part of this answer. Needs a birth date, an AFM or an ID number besides the names.
     *
     * @throws \InvalidArgumentException for missing or invalid input
     * @throws GovNotFoundException when nobody matches
     * @throws GovUnavailableException credentials, transport or a server error
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
    ): AmkaPerson {
        if ($afm !== null) {
            ValidAfm::ensure($afm);
        }

        return $this->person(
            fn () => $this->info->data2amka($surname, $firstName, $fatherName, $motherName, $birthDate, $afm, $adt, $request),
            'data2amkaOutputRecord',
            'these details',
        );
    }

    /**
     * Like amka2data(), plus the ΠΑΑΥΠΑ status indication. The raw response, unmapped.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataE(string $amka, ?Request $request = null): array
    {
        return $this->info->amka2dataE($amka, $request);
    }

    /**
     * A lighter field set than amka2dataE(), with the same status indication. The raw response, unmapped.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataLE(string $amka, ?Request $request = null): array
    {
        return $this->info->amka2dataLE($amka, $request);
    }

    /**
     * @param  \Closure(): array{result: array<string, mixed>, audit_record: array<string, mixed>}  $request
     *
     * @throws GovNotFoundException
     * @throws GovApiException
     */
    private function person(\Closure $request, string $recordKey, string $what): AmkaPerson
    {
        try {
            $response = $request();
        } catch (Throwable $e) {
            throw Failures::sortRejection($e);
        }

        return AmkaPerson::fromRecord((array) ($response['result'][$recordKey] ?? []), $response['audit_record'])
            ?? throw new GovNotFoundException("No person found for $what.");
    }
}

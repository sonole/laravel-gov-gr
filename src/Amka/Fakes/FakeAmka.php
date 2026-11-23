<?php

namespace Sonole\GovGr\Amka\Fakes;

use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Sonole\GovGr\Amka\Amka;
use Sonole\GovGr\Amka\Data\AmkaPerson;
use Sonole\GovGr\Amka\Data\RelationshipConfirmation;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Fakes\FakeData;
use Throwable;

/**
 * Amka without the network: install it with Amka::fake(). amka2data() and data2amka() answer from their input
 * alone, write no audit record and need no credentials.
 *
 * Only a valid AMKA is found. data2amka() builds a valid AMKA from the details that starts with the birth date,
 * and amka2data() derives gender, birth date and the rest from the AMKA, leaving the AFM empty (a fake cannot
 * know which AFM an AMKA was built for). Override single answers, each a Throwable to throw, an AmkaPerson, or
 * a record array: $amka2data is keyed by AMKA, $data2amka by AFM. confirmRelAmka() confirms every relAmka as
 * "ΤΕΚΝΟ" unless $confirmations says otherwise (a bool, a RelationshipConfirmation, or a Throwable).
 */
class FakeAmka extends Amka
{
    /**
     * @param array<string, AmkaPerson|array<string, mixed>|Throwable> $amka2data
     * @param array<string, AmkaPerson|array<string, mixed>|Throwable> $data2amka
     * @param array<string, RelationshipConfirmation|bool|Throwable> $confirmations keyed by relAmka; default confirmed
     */
    public function __construct(
        private readonly array $amka2data = [],
        private readonly array $data2amka = [],
        private readonly array $confirmations = [],
    ) {
    }

    public function amka2data(string $amka, ?Request $request = null): AmkaPerson
    {
        if ($person = $this->override($this->amka2data[$amka] ?? null)) {
            return $person;
        }

        if (! FakeData::isValidAmka($amka)) {
            throw new GovNotFoundException("No person found for AMKA [$amka].");
        }

        $hash = crc32($amka);
        $male = $hash % 2 === 0;

        return AmkaPerson::fromRecord([
            'amkaInput' => $amka,
            'amkaCurrent' => $amka,
            'sex' => $male ? 'ΑΡΡΕΝ' : 'ΘΗΛΥ',
            'firstNameEl' => $male ? 'ΓΕΩΡΓΙΟΣ' : 'ΜΑΡΙΑ',
            'idSurnameEl' => ['ΓΕΩΡΓΙΟΥ', 'ΝΙΚΟΛΑΟΥ', 'ΔΗΜΗΤΡΙΟΥ', 'ΙΩΑΝΝΟΥ'][$hash % 4],
            'fatherNameEl' => 'ΑΝΤΩΝΙΟΣ',
            'motherNameEl' => 'ΕΛΕΝΗ',
            'birthDate' => FakeData::birthDateOf($amka)->format('d/m/Y'),
            'birthMunicipality' => 'ΑΘΗΝΑ',
            'birthCountry' => 'ΕΛΛΑΔΑ',
            'adtType' => 'Τ',
            'adt' => 'ΑΒ'.str_pad((string) ($hash % 1000000), 6, '0', STR_PAD_LEFT),
            'nationalityCountry' => 'ΕΛΛΑΔΑ',
        ]) ?? throw new GovNotFoundException("No person found for AMKA [$amka].");
    }

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
        // The same input rules as the real service.
        if ($surname === '' || $firstName === '') {
            throw new InvalidArgumentException('data2amka() requires both surname and firstName to be non-empty.');
        }

        if (empty($birthDate) && empty($afm) && empty($adt)) {
            throw new InvalidArgumentException('data2amka() requires at least one of birthDate, afm, or adt to be given.');
        }

        if ($afm !== null && $person = $this->override($this->data2amka[$afm] ?? null)) {
            return $person;
        }

        $date = $birthDate ? Carbon::createFromFormat('!d/m/Y', $birthDate) : false;

        if ($birthDate && (! $date || $date->format('d/m/Y') !== $birthDate)) {
            throw new InvalidArgumentException("data2amka() requires birthDate in DD/MM/YYYY format, got [$birthDate].");
        }

        $date = $date ?: FakeData::birthDate($afm.$adt.$surname.$firstName);

        return AmkaPerson::fromRecord([
            'amkaCurrent' => FakeData::amka($date, $afm.$adt.$surname.$firstName),
            'idSurnameEl' => $surname,
            'firstNameEl' => $firstName,
            'fatherNameEl' => $fatherName,
            'motherNameEl' => $motherName,
            'birthDate' => $date->format('d/m/Y'),
            'afm' => $afm,
            'adt' => $adt,
        ]) ?? throw new GovNotFoundException('No person found for these details.');
    }

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
        $this->guardParent($parentAmka, $parentFirstname, $parentSurname, $parentFathername, $parentMothername, $parentBirthdate);

        return $this->confirmation($relAmka);
    }

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
        $this->guardParent($parentAmka, $parentFirstname, $parentSurname, $parentFathername, $parentMothername, $parentBirthdate);

        if ($relAmkas === []) {
            throw new InvalidArgumentException('confirmRelAmkaMultiple() requires at least one relAmka.');
        }

        return array_map(fn (string $relAmka) => $this->confirmation($relAmka), array_values($relAmkas));
    }

    public function amka2dataE(string $amka, ?Request $request = null): array
    {
        throw $this->notFaked(__FUNCTION__);
    }

    public function amka2dataLE(string $amka, ?Request $request = null): array
    {
        throw $this->notFaked(__FUNCTION__);
    }

    private function confirmation(string $relAmka): RelationshipConfirmation
    {
        $response = $this->confirmations[$relAmka] ?? true;

        if ($response instanceof Throwable) {
            throw $response;
        }

        if ($response instanceof RelationshipConfirmation) {
            return $response;
        }

        return new RelationshipConfirmation($relAmka, $response, $response ? 'ΤΕΚΝΟ' : null);
    }

    /** The real service wants the parent's AMKA, or all of the parent's details. */
    private function guardParent(?string $amka, ?string $first, ?string $surname, ?string $father, ?string $mother, ?string $birthDate): void
    {
        if (empty($amka) && (empty($first) || empty($surname) || empty($father) || empty($mother) || empty($birthDate))) {
            throw new InvalidArgumentException("The parent's details are incomplete. Either parentAmka must be given, or all of the parent's names and birth date.");
        }
    }

    /** Throws a Throwable override, turns an array into a person, passes a person through, null when there is none. */
    private function override(mixed $response): ?AmkaPerson
    {
        if ($response instanceof Throwable) {
            throw $response;
        }

        return is_array($response) ? AmkaPerson::fromRecord($response) : $response;
    }

    private function notFaked(string $method): BadMethodCallException
    {
        return new BadMethodCallException("Amka::$method() is not covered by Amka::fake().");
    }
}

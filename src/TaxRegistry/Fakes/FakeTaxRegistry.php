<?php

namespace Sonole\GovGr\TaxRegistry\Fakes;

use BadMethodCallException;
use Illuminate\Http\Request;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Fakes\FakeData;
use Sonole\GovGr\TaxRegistry\Data\RegistryEntity;
use Sonole\GovGr\TaxRegistry\TaxRegistry;
use Throwable;

/**
 * TaxRegistry without the network: install it with TaxRegistry::fake(). retrieveInfoByAFM() answers from the
 * AFM alone, writes no audit record and needs no credentials.
 *
 * By convention an AFM starting 09, 8 or 9 is a legal entity, any other a natural person whose details derive
 * from the AFM. Override single AFMs with $responses, keyed by AFM: a RegistryEntity, a basicInfo array, or a
 * Throwable to throw (e.g. new GovNotFoundException).
 */
class FakeTaxRegistry extends TaxRegistry
{
    /** @param array<string, RegistryEntity|array<string, mixed>|Throwable> $responses */
    public function __construct(private readonly array $responses = [])
    {
    }

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
    ): RegistryEntity {
        $response = $this->responses[$afm] ?? null;

        if ($response instanceof Throwable) {
            throw $response;
        }

        if ($response instanceof RegistryEntity) {
            return $response;
        }

        if (is_array($response)) {
            return RegistryEntity::fromRecord(['basicInfo' => $response]);
        }

        if (preg_match('/^\d{9}$/', $afm) !== 1) {
            throw new GovNotFoundException("The AFM [$afm] is not valid.");
        }

        return RegistryEntity::fromRecord(['basicInfo' => preg_match('/^(09|8|9)/', $afm) === 1
            ? $this->legalEntity($afm)
            : $this->naturalPerson($afm)]);
    }

    public function retrieveInfosByADT(string $adt, int $kind, ?Request $request = null): array
    {
        throw $this->notFaked(__FUNCTION__);
    }

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
        throw $this->notFaked(__FUNCTION__);
    }

    public function lookupByAfm(string $afm, ?string $afmCalledBy = null, ?Request $request = null): array
    {
        throw $this->notFaked(__FUNCTION__);
    }

    private function notFaked(string $method): BadMethodCallException
    {
        return new BadMethodCallException("TaxRegistry::$method() is not covered by TaxRegistry::fake().");
    }

    /** @return array<string, string> */
    private function legalEntity(string $afm): array
    {
        return [
            'afm' => $afm,
            'doy' => '1117',
            'doyDescr' => 'Δ.Ο.Υ. ΙΖ ΑΘΗΝΩΝ',
            'iNiFlag' => '2',
            'deactivationFlag' => '1',
            'onomasia' => 'ΔΟΚΙΜΑΣΤΙΚΟΣ ΣΥΛΛΟΓΟΣ',
            'indPhone' => '2101234567',
            'cntResidenceDescr' => 'ΕΛΛΑΔΑ',
            'residenceAddress' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ',
            'residenceAddressNo' => '10',
            'residenceZipCode' => '12345',
            'residenceParDescr' => 'ΑΘΗΝΑ',
            'firmDoy' => '1117',
            'firmAddress' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ',
            'firmAddressNo' => '10',
            'firmZipCode' => '12345',
            'firmParDescr' => 'ΑΘΗΝΑ',
            'firmPhone' => '2101234567',
        ];
    }

    /** @return array<string, string> */
    private function naturalPerson(string $afm): array
    {
        $hash = crc32($afm);
        $surnames = ['ΓΕΩΡΓΙΟΥ', 'ΝΙΚΟΛΑΟΥ', 'ΔΗΜΗΤΡΙΟΥ', 'ΚΩΝΣΤΑΝΤΙΝΟΥ', 'ΑΝΤΩΝΙΟΥ', 'ΙΩΑΝΝΟΥ', 'ΒΑΣΙΛΕΙΟΥ', 'ΠΑΠΑΝΙΚΟΛΑΟΥ'];
        $names = ['ΓΕΩΡΓΙΟΣ', 'ΜΑΡΙΑ', 'ΝΙΚΟΛΑΟΣ', 'ΕΛΕΝΗ', 'ΔΗΜΗΤΡΙΟΣ', 'ΑΙΚΑΤΕΡΙΝΗ', 'ΚΩΝΣΤΑΝΤΙΝΟΣ', 'ΣΟΦΙΑ'];
        $fathers = ['ΑΝΤΩΝΙΟΣ', 'ΠΑΝΑΓΙΩΤΗΣ', 'ΧΡΗΣΤΟΣ', 'ΣΠΥΡΙΔΩΝ', 'ΘΕΟΔΩΡΟΣ', 'ΕΥΑΓΓΕΛΟΣ'];
        $mothers = ['ΜΑΡΙΑ', 'ΕΛΕΝΗ', 'ΑΙΚΑΤΕΡΙΝΗ', 'ΣΟΦΙΑ'];

        return [
            'afm' => $afm,
            'doy' => '1117',
            'doyDescr' => 'Δ.Ο.Υ. ΙΖ ΑΘΗΝΩΝ',
            'iNiFlag' => '1',
            'deactivationFlag' => '1',
            // onomasia packs a natural person as "SURNAME,SECOND SURNAME,NAME,FATHER NAME".
            'onomasia' => implode(',', [
                $surnames[$hash % count($surnames)],
                '',
                $names[intdiv($hash, 8) % count($names)],
                $fathers[intdiv($hash, 64) % count($fathers)],
            ]),
            'mothersFirstName' => $mothers[intdiv($hash, 512) % count($mothers)],
            'birthDate' => FakeData::birthDate($afm)->format('Y-m-d'),
            'indPhone' => '2101234567',
            'cntResidenceDescr' => 'ΕΛΛΑΔΑ',
            'residenceAddress' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ',
            'residenceAddressNo' => '10',
            'residenceZipCode' => '12345',
            'residenceParDescr' => 'ΑΘΗΝΑ',
        ];
    }
}

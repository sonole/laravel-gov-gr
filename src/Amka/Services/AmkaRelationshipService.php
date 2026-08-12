<?php

namespace Sonole\GovGr\Amka\Services;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AmkaRelationshipService extends AmkaSoapService
{
    protected function service(): GovService
    {
        return GovService::AmkaRelationship;
    }

    protected function configKey(): string
    {
        return 'amka';
    }

    protected function section(): ?string
    {
        return 'relationship';
    }

    protected function namespacePrefix(): string
    {
        return 'con';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/confirmRelAmkaInterface';
    }

    /**
     * Confirms $relAmka against a single parent, identified
     * either by $parentAmka or by their personal details.
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
        $this->validateParentDetails($parentAmka, $parentFirstname, $parentSurname, $parentFathername, $parentMothername, $parentBirthdate);

        $body = XmlHelper::tag('relAmka', $relAmka)
            .XmlHelper::optionalTag('parentAmka', $parentAmka)
            .XmlHelper::optionalTag('parentFirstname', $parentFirstname)
            .XmlHelper::optionalTag('parentSurname', $parentSurname)
            .XmlHelper::optionalTag('parentFathername', $parentFathername)
            .XmlHelper::optionalTag('parentMothername', $parentMothername)
            .XmlHelper::optionalTag('parentBirthdate', $parentBirthdate);

        return $this->call('confirmRelAmka', $body, $request);
    }

    /**
     * Confirms one or more $relAmkas against a single parent, identified
     * either by $parentAmka or by their personal details.
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
        $this->validateParentDetails($parentAmka, $parentFirstname, $parentSurname, $parentFathername, $parentMothername, $parentBirthdate);
        $this->validateRelAmkas($relAmkas);

        $relAmkaTags = implode('', array_map(
            fn (string $amka) => XmlHelper::tag('relAmka', $amka),
            $relAmkas,
        ));

        $body = $relAmkaTags
            .XmlHelper::optionalTag('parentAmka', $parentAmka)
            .XmlHelper::optionalTag('parentFirstname', $parentFirstname)
            .XmlHelper::optionalTag('parentSurname', $parentSurname)
            .XmlHelper::optionalTag('parentFathername', $parentFathername)
            .XmlHelper::optionalTag('parentMothername', $parentMothername)
            .XmlHelper::optionalTag('parentBirthdate', $parentBirthdate);

        return $this->call('confirmRelAmkaMultiple', $body, $request);
    }

    /**
     * A parent must be identified either by their AMKA alone,
     * or by all five personal-detail fields together
     *
     * @throws InvalidArgumentException
     */
    private function validateParentDetails(
        ?string $parentAmka,
        ?string $parentFirstname,
        ?string $parentSurname,
        ?string $parentFathername,
        ?string $parentMothername,
        ?string $parentBirthdate,
    ): void {
        $allPersonalDetailsGiven = ! empty($parentFirstname) && ! empty($parentSurname)
            && ! empty($parentFathername) && ! empty($parentMothername) && ! empty($parentBirthdate);

        if (empty($parentAmka) && ! $allPersonalDetailsGiven) {
            throw new InvalidArgumentException(
                "The parent's details are incomplete. Either parentAmka must be given, or all of parentFirstname, parentSurname, parentFathername, parentMothername, and parentBirthdate must be given."
            );
        }

        if (! empty($parentBirthdate) && ! self::isValidBirthDate($parentBirthdate)) {
            throw new InvalidArgumentException("parentBirthdate must be in DD/MM/YYYY format, got [$parentBirthdate].");
        }
    }

    /**
     * @param  string[]  $relAmkas
     *
     * @throws InvalidArgumentException
     */
    private function validateRelAmkas(array $relAmkas): void
    {
        if (empty($relAmkas)) {
            throw new InvalidArgumentException('confirmRelAmkaMultiple() requires at least one relAmka.');
        }

        foreach ($relAmkas as $relAmka) {
            if (trim($relAmka) === '') {
                throw new InvalidArgumentException('confirmRelAmkaMultiple() requires every relAmka to be a non-empty string.');
            }
        }
    }
}

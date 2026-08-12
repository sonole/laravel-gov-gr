<?php

namespace Sonole\GovGr\Amka\Services;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AmkaInfoService extends AmkaSoapService
{
    protected function service(): GovService
    {
        return GovService::AmkaInfo;
    }

    protected function configKey(): string
    {
        return 'amka';
    }

    protected function section(): ?string
    {
        return 'info';
    }

    protected function namespacePrefix(): string
    {
        return 'amk';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/AmkaInfoInterface';
    }

    /**
     * Looks up person data by AMKA.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2data(string $amka, ?Request $request = null): array
    {
        return $this->call(
            'amka2data',
            fn () => XmlHelper::tag('aa', $this->transactionId()).XmlHelper::tag('amka', $amka),
            $request,
        );
    }

    /**
     * Looks up the AMKA matching the given personal details.
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
        if (empty($surname) || empty($firstName)) {
            throw new InvalidArgumentException('data2amka() requires both surname and firstName to be non-empty.');
        }

        if (empty($birthDate) && empty($afm) && empty($adt)) {
            throw new InvalidArgumentException('data2amka() requires at least one of birthDate, afm, or adt to be given.');
        }

        if (! empty($birthDate) && ! self::isValidBirthDate($birthDate)) {
            throw new InvalidArgumentException("data2amka() requires birthDate in DD/MM/YYYY format, got [$birthDate].");
        }

        return $this->call(
            'data2amka',
            fn () => XmlHelper::tag('aa', $this->transactionId())
                .XmlHelper::tag('surname', $surname)
                .XmlHelper::tag('firstName', $firstName)
                .XmlHelper::optionalTag('fatherName', $fatherName)
                .XmlHelper::optionalTag('motherName', $motherName)
                .XmlHelper::optionalTag('birthDate', $birthDate)
                .XmlHelper::optionalTag('afm', $afm)
                .XmlHelper::optionalTag('adt', $adt),
            $request,
        );
    }

    /**
     * Like amka2data(), plus the ΠΑΑΥΠΑ status indication.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataE(string $amka, ?Request $request = null): array
    {
        return $this->call(
            'amka2dataE',
            fn () => XmlHelper::tag('aa', $this->transactionId()).XmlHelper::tag('amka', $amka),
            $request,
        );
    }

    /**
     * A lighter field set than amka2dataE(), with the same status indication.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function amka2dataLE(string $amka, ?Request $request = null): array
    {
        return $this->call('amka2dataLE', XmlHelper::tag('amka', $amka), $request);
    }
}

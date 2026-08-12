<?php

namespace Sonole\GovGr\TaxisnetOAuth\Support;

/**
 * The identity fields GSIS returns from `userinfo?format=xml`
 */
final readonly class TaxisnetOAuthIdentity
{
    public function __construct(
        public string $afm,
        public ?string $lastName,
        public ?string $firstName,
        public ?string $fatherName,
        public ?string $motherName,
        public ?string $username,
        public ?string $birthYear,
    ) {
    }

    /** @param array<string, string> $attributes raw `userinfo` element attributes, e.g. from SimpleXMLElement::attributes() cast to string */
    public static function fromAttributes(array $attributes): self
    {
        $normalize = function (?string $value): ?string {
            $value = $value === null ? null : trim($value);

            return $value === null || $value === '' || $value === 'null' ? null : $value;
        };

        return new self(
            afm: $normalize($attributes['taxid'] ?? '') ?? '',
            lastName: $normalize($attributes['lastname'] ?? null),
            firstName: $normalize($attributes['firstname'] ?? null),
            fatherName: $normalize($attributes['fathername'] ?? null),
            motherName: $normalize($attributes['mothername'] ?? null),
            username: $normalize($attributes['userid'] ?? null),
            birthYear: $normalize($attributes['birthyear'] ?? null),
        );
    }

    /** A legal-entity (company) login has no first name. GSIS sends the literal string "null" for it, normalized to null above. */
    public function isLegalEntity(): bool
    {
        return $this->firstName === null;
    }

    /** Company name for a legal-entity login. GSIS carries it in `lastname`, not a dedicated attribute. */
    public function companyName(): ?string
    {
        return $this->isLegalEntity() ? $this->lastName : null;
    }

    /** @return array{afm: string, last_name: ?string, first_name: ?string, father_name: ?string, mother_name: ?string, username: ?string, birth_year: ?string, is_legal_entity: bool} */
    public function toArray(): array
    {
        return [
            'afm' => $this->afm,
            'last_name' => $this->lastName,
            'first_name' => $this->firstName,
            'father_name' => $this->fatherName,
            'mother_name' => $this->motherName,
            'username' => $this->username,
            'birth_year' => $this->birthYear,
            'is_legal_entity' => $this->isLegalEntity(),
        ];
    }
}

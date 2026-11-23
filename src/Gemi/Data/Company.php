<?php

namespace Sonole\GovGr\Gemi\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Support\Normalize;

/**
 * A company's GEMI record: the result of Gemi::basicInfo() (getBasicInfoV3). The id / descr pairs keep the
 * registry's own codes next to their descriptions. `raw` is the whole company record, for anything not mapped.
 */
final class Company
{
    /**
     * @param list<string> $titles
     * @param list<string> $branches
     * @param list<CompanyApplication> $applications
     * @param list<CompanyActivity> $activities
     * @param list<Stock> $stocks
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        /** arGemi, 12 digits. */
        public readonly string $gemiNumber,
        public readonly ?string $afm,
        public readonly ?string $legalTypeId,
        /** "ΑΕ", "ΙΚΕ", "ΑΤΟΜΙΚΗ", ... */
        public readonly ?string $legalTypeDescr,
        public readonly ?string $statusId,
        /** memberStatusDescr: "Ενεργή", "Διαγραφή", ... */
        public readonly ?string $statusDescr,
        public readonly ?string $doy,
        public readonly ?string $doyDescr,
        /** businessName */
        public readonly string $name,
        /** businessTitles: the trade names (διακριτικοί τίτλοι). */
        public readonly array $titles,
        public readonly ?string $englishName,
        /** companyGoal */
        public readonly ?string $goal,
        /** The GEMI office (chamber) in charge: orgId / orgDescr. */
        public readonly ?string $orgId,
        public readonly ?string $orgDescr,
        public readonly ?string $prefectureId,
        public readonly ?string $prefectureDescr,
        public readonly ?string $municipalityId,
        public readonly ?string $municipalityDescr,
        /** street, streetNumber, tk and city. */
        public readonly Address $address,
        public readonly ?string $region,
        public readonly ?string $phone,
        public readonly ?string $fax,
        public readonly ?string $mobile,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly ?string $eshop,
        /** dateGemiRegistered */
        public readonly ?Carbon $gemiRegisteredAt,
        /** dateProfessionStarted */
        public readonly ?Carbon $professionStartedAt,
        /** dateRegistered */
        public readonly ?Carbon $registeredAt,
        /** dateIncorporated */
        public readonly ?Carbon $incorporatedAt,
        /** lastStateChangeDate */
        public readonly ?Carbon $lastStatusChangeAt,
        /** dateLastChange */
        public readonly ?Carbon $lastChangeAt,
        /** The pre-GEMI ΜΑΕ number of an ΑΕ. */
        public readonly ?string $arMae,
        public readonly ?string $departmentId,
        public readonly ?string $departmentDescr,
        /** Duration in years, as the registry gives it. */
        public readonly ?string $duration,
        /** expirationDate */
        public readonly ?Carbon $expiresAt,
        /** Null for a parent company, the kind of branch otherwise. */
        public readonly ?BranchType $branchType,
        /** The GEMI numbers of the company's branches (branch). */
        public readonly array $branches,
        /** businessApplications */
        public readonly array $applications,
        /** businessSubjects, the ΚΑΔ activities. */
        public readonly array $activities,
        /** Null when the registry sent an empty capital record. */
        public readonly ?Capital $capital,
        public readonly array $stocks,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    public function isBranch(): bool
    {
        return $this->branchType !== null;
    }

    /** Whether a deletion from GEMI has been registered (an application flagged deletionSubject). */
    public function isDeleted(): bool
    {
        foreach ($this->applications as $application) {
            if ($application->isDeletion) {
                return true;
            }
        }

        return false;
    }

    /** @return list<CompanyActivity> */
    public function activeActivities(): array
    {
        return array_values(array_filter($this->activities, fn (CompanyActivity $a) => $a->isActive()));
    }

    /**
     * @param array<string, mixed> $record the getBasicInfoV3OutputRecord's company
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);
        $capital = Normalize::list($record['capital'] ?? null);

        return new self(
            gemiNumber: $text('arGemi') ?? '',
            afm: $text('afm'),
            legalTypeId: $text('legalTypeId'),
            legalTypeDescr: $text('legalTypeDescr'),
            statusId: $text('memberStatusId'),
            statusDescr: $text('memberStatusDescr'),
            doy: $text('doyId'),
            doyDescr: $text('doyDescr'),
            name: (string) $text('businessName'),
            titles: Normalize::strings($record['businessTitles'] ?? null),
            englishName: $text('englishName'),
            goal: $text('companyGoal'),
            orgId: $text('orgId'),
            orgDescr: $text('orgDescr'),
            prefectureId: $text('prefectureId'),
            prefectureDescr: $text('prefectureDescr'),
            municipalityId: $text('municipalityId'),
            municipalityDescr: $text('municipalityDescr'),
            address: new Address($text('street'), $text('streetNumber'), $text('tk'), $text('city')),
            region: $text('region'),
            phone: $text('phone'),
            fax: $text('fax'),
            mobile: $text('mobile'),
            email: $text('email'),
            website: $text('website'),
            eshop: $text('eshop'),
            gemiRegisteredAt: Normalize::date($text('dateGemiRegistered')),
            professionStartedAt: Normalize::date($text('dateProfessionStarted')),
            registeredAt: Normalize::date($text('dateRegistered')),
            incorporatedAt: Normalize::date($text('dateIncorporated')),
            lastStatusChangeAt: Normalize::date($text('lastStateChangeDate')),
            lastChangeAt: Normalize::date($text('dateLastChange')),
            arMae: $text('arMae'),
            departmentId: $text('departmentId'),
            departmentDescr: $text('departmentDescr'),
            duration: $text('duration'),
            expiresAt: Normalize::date($text('expirationDate')),
            branchType: BranchType::tryFrom((string) Normalize::text(data_get($record, 'branchType.branchTypeId'))),
            branches: Normalize::strings($record['branch'] ?? null),
            applications: array_map([CompanyApplication::class, 'fromRecord'], Normalize::list($record['businessApplications'] ?? null)),
            activities: array_map([CompanyActivity::class, 'fromRecord'], Normalize::list($record['businessSubjects'] ?? null)),
            capital: $capital === [] ? null : Capital::fromRecord($capital[0]),
            stocks: array_map([Stock::class, 'fromRecord'], Normalize::list($record['stocks'] ?? null)),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}

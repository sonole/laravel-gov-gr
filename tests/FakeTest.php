<?php

namespace Sonole\GovGr\Tests;

use BadMethodCallException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Sonole\GovGr\Amka\Data\AmkaPerson;
use Sonole\GovGr\Amka\Facades\Amka;
use Sonole\GovGr\BankConfirm\Facades\BankConfirm;
use Sonole\GovGr\Ergani\Data\EmploymentKind;
use Sonole\GovGr\Ergani\Data\EmploymentRelation;
use Sonole\GovGr\Ergani\Data\EmploymentType;
use Sonole\GovGr\Ergani\Facades\Ergani;
use Sonole\GovGr\Gemi\Data\Company;
use Sonole\GovGr\Gemi\Exceptions\AfmNotFoundException;
use Sonole\GovGr\Gemi\Facades\Gemi;
use Sonole\GovGr\Core\Enums\Gender;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Fakes\FakeData;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\GovGrServiceProvider;
use Sonole\GovGr\TaxRegistry\Data\EntityKind;
use Sonole\GovGr\TaxRegistry\Data\RegistryEntity;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry;

class FakeTest extends TestCase
{
    protected function tearDown(): void
    {
        Amka::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_a_natural_person_afm_answers_without_the_network_or_an_audit_record(): void
    {
        Http::fake();
        TaxRegistry::fake();

        $entity = TaxRegistry::retrieveInfoByAFM('123456780');

        $this->assertSame(EntityKind::NaturalPerson, $entity->kind);
        $this->assertNotEmpty($entity->surname);
        $this->assertNotEmpty($entity->firstName);
        $this->assertNotNull($entity->birthDate);
        $this->assertEquals($entity, TaxRegistry::retrieveInfoByAFM('123456780'));
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_an_afm_starting_09_8_or_9_is_a_legal_entity(): void
    {
        TaxRegistry::fake();

        foreach (['099360608', '812345678', '912345678'] as $afm) {
            $entity = TaxRegistry::retrieveInfoByAFM($afm);

            $this->assertSame(EntityKind::LegalEntity, $entity->kind);
            $this->assertSame('ΔΟΚΙΜΑΣΤΙΚΟΣ ΣΥΛΛΟΓΟΣ', $entity->name);
            $this->assertNull($entity->surname);
        }
    }

    public function test_an_invalid_afm_is_not_found(): void
    {
        TaxRegistry::fake();

        $this->expectException(GovNotFoundException::class);

        TaxRegistry::retrieveInfoByAFM('12345');
    }

    public function test_registry_responses_can_be_overridden_per_afm(): void
    {
        $entity = RegistryEntity::fromRecord(['basicInfo' => ['afm' => '111111111', 'iNiFlag' => '1', 'onomasia' => 'ΑΒΓ,,ΔΕΖ,ΗΘΙ']]);
        TaxRegistry::fake([
            '111111111' => $entity,
            '222222222' => ['afm' => '222222222', 'iNiFlag' => '2', 'onomasia' => 'ΕΤΑΙΡΙΑ'],
            '333333333' => new GovNotFoundException('Ανύπαρκτος Α.Φ.Μ.'),
        ]);

        $this->assertSame($entity, TaxRegistry::retrieveInfoByAFM('111111111'));
        $this->assertSame('ΕΤΑΙΡΙΑ', TaxRegistry::retrieveInfoByAFM('222222222')->name);

        $this->expectException(GovNotFoundException::class);
        TaxRegistry::retrieveInfoByAFM('333333333');
    }

    public function test_the_other_registry_methods_are_not_faked(): void
    {
        TaxRegistry::fake();

        $this->expectException(BadMethodCallException::class);

        TaxRegistry::lookupByAfm('123456789');
    }

    public function test_data2amka_builds_a_valid_amka_that_starts_with_the_birth_date(): void
    {
        Http::fake();
        Amka::fake();

        $person = Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', 'ΠΕΤΡΟΣ', birthDate: '02/01/1990', afm: '123456780');

        $this->assertTrue(FakeData::isValidAmka($person->amka));
        $this->assertStringStartsWith('020190', $person->amka);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $person->surname);
        $this->assertSame('ΠΕΤΡΟΣ', $person->fatherName);
        $this->assertSame('123456780', $person->afm);
        $this->assertSame($person->amka, Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', 'ΠΕΤΡΟΣ', birthDate: '02/01/1990', afm: '123456780')->amka);
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_amka2data_derives_the_person_from_the_amka(): void
    {
        Amka::fake();
        $amka = Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', birthDate: '02/01/1990')->amka;

        $person = Amka::amka2data($amka);

        $this->assertSame($amka, $person->amka);
        $this->assertSame('1990-01-02', $person->birthDate?->format('Y-m-d'));
        $this->assertInstanceOf(Gender::class, $person->gender);
        $this->assertTrue($person->hasPoliceId());
        $this->assertSame('ΑΘΗΝΑ', $person->birthPlace);
        $this->assertNull($person->afm);
    }

    public function test_only_a_valid_amka_is_found(): void
    {
        Amka::fake();

        $this->expectException(GovNotFoundException::class);

        Amka::amka2data('12345678901');
    }

    public function test_data2amka_keeps_the_real_input_rules(): void
    {
        Amka::fake();

        $this->expectException(InvalidArgumentException::class);

        Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ');
    }

    public function test_amka_responses_can_be_overridden(): void
    {
        $person = AmkaPerson::fromRecord(['amkaCurrent' => '01019000015', 'firstNameEl' => 'Α', 'idSurnameEl' => 'Β', 'afm' => '000000001']);
        Amka::fake(
            amka2data: ['01019000015' => $person, '02029039233' => new GovNotFoundException('x')],
            data2amka: ['000000001' => ['amkaCurrent' => '01019000015', 'firstNameEl' => 'Γ', 'idSurnameEl' => 'Δ']],
        );

        $this->assertSame($person, Amka::amka2data('01019000015'));
        $this->assertSame('01019000015', Amka::data2amka('Δ', 'Γ', afm: '000000001')->amka);

        $this->expectException(GovNotFoundException::class);
        Amka::amka2data('02029039233');
    }

    public function test_amka2data_e_and_le_are_faked_as_an_active_amka(): void
    {
        Amka::fake();

        $person = Amka::amka2dataE('01019000015');

        $this->assertSame(\Sonole\GovGr\Amka\Data\AmkaStatus::Active, $person->status);
        $this->assertSame(Amka::amka2data('01019000015')->surname, $person->surname);
        $this->assertSame(\Sonole\GovGr\Amka\Data\AmkaStatus::Active, Amka::amka2dataLE('01019000015')->status);
    }

    public function test_the_config_switch_installs_the_fakes_and_refuses_production(): void
    {
        config(['govgr.fake' => true]);
        (new GovGrServiceProvider($this->app))->boot();

        $this->assertSame(EntityKind::NaturalPerson, TaxRegistry::retrieveInfoByAFM('123456780')->kind);
        $this->assertTrue(FakeData::isValidAmka(Amka::data2amka('Α', 'Β', birthDate: '01/01/1990')->amka));
        $this->assertSame('123456780000', Gemi::basicInfo('123456780')->gemiNumber);
        $this->assertSame(EmploymentKind::Indefinite, Ergani::employmentRelationship('123456780', '01/01/2025')->kind);
        $this->assertSame('true', BankConfirm::confirmAccount('GR1601101250000000012300695', '123456780')['result']['confirmed']);
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());

        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new GovGrServiceProvider($this->app))->boot();
    }

    public function test_gemi_answers_for_any_nine_digit_afm_without_the_network(): void
    {
        Http::fake();
        Gemi::fake();

        $company = Gemi::basicInfo('123456780');
        $board = Gemi::companyBoard('123456780');
        $sheets = Gemi::balanceSheets('123456780');

        $this->assertSame('123456780000', $company->gemiNumber);
        $this->assertSame('123456780', $company->afm);
        $this->assertSame('ΙΚΕ', $company->legalTypeDescr);
        $this->assertFalse($company->isDeleted());
        $this->assertSame(5000.0, $company->capital?->amount);
        $this->assertSame(['123456780000'], Gemi::getGemiNumbers('123456780'));
        $this->assertSame('123456780000', data_get(Gemi::validate('123456780'), 'result.companies.company.gemiNumber'));
        $this->assertCount(1, $board->representatives());
        $this->assertSame('ΑΘΗΝΑ', $board->members[0]->address->area);
        $this->assertCount(2, $sheets);
        $this->assertTrue($sheets[0]->hasFiles());
        $this->assertSame('123456780000', $sheets[0]->files[0]->gemiNumber);
        $this->assertFalse($sheets[1]->hasFiles());
        $this->assertSame($company->name, Gemi::basicInfo('123456780')->name);
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_gemi_does_not_know_an_afm_that_is_not_nine_digits(): void
    {
        Gemi::fake();

        $this->assertNull(Gemi::getGemiNumber('12345'));
        $this->assertSame([], Gemi::getGemiNumbers('12345'));
        $this->assertSame([], Gemi::validate('12345')['result']['companies']);

        $this->expectException(AfmNotFoundException::class);
        Gemi::basicInfo('12345');
    }

    public function test_gemi_responses_can_be_overridden_per_afm(): void
    {
        $company = Company::fromRecord(['arGemi' => '000000000001', 'businessName' => 'ΔΕΙΓΜΑ ΑΕ']);
        Gemi::fake(
            companies: ['111111111' => $company, '222222222' => ['arGemi' => '000000000002', 'businessName' => 'ΑΛΛΗ ΑΕ'], '333333333' => new GovNotFoundException('x')],
            boards: ['111111111' => ['fp' => [], 'np' => []]],
            balanceSheets: ['111111111' => [['year' => '2020', 'netTurnover' => '1', 'files' => []]]],
        );

        $this->assertSame($company, Gemi::basicInfo('111111111'));
        $this->assertSame('ΑΛΛΗ ΑΕ', Gemi::basicInfo('222222222')->name);
        $this->assertTrue(Gemi::companyBoard('111111111')->isEmpty());
        $this->assertSame(1.0, Gemi::balanceSheets('111111111')[0]->netTurnover);

        $this->expectException(GovNotFoundException::class);
        Gemi::basicInfo('333333333');
    }

    public function test_ergani_employs_any_nine_digit_afm_full_time_without_the_network(): void
    {
        Http::fake();
        Ergani::fake();

        $job = Ergani::employeeRelation('123456780');
        $status = Ergani::employmentRelationship('123456780', '01/06/2025');
        $history = Ergani::employmentHistoryByPeriod('123456780', '01/01/2025', '31/12/2025');

        $this->assertNotNull($job);
        $this->assertSame('123456780', $job->employeeAfm);
        $this->assertSame(1500.0, $job->monthlySalary);
        $this->assertSame($job->employerAfm, Ergani::employeeRelation('123456780')?->employerAfm);
        $this->assertSame(EmploymentKind::Indefinite, $status->kind);
        $this->assertSame(EmploymentType::FullTime, $status->type);
        $this->assertCount(1, $history);
        $this->assertSame($job->employerName, $history[0]->employerName);
        $this->assertSame('2025-01-01', $history[0]->from?->format('Y-m-d'));
        $this->assertSame('2025-12-31', $history[0]->to?->format('Y-m-d'));
        $this->assertSame('213100', $history[0]->occupationCode);
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_ergani_knows_no_job_for_an_afm_that_is_not_nine_digits(): void
    {
        Ergani::fake();

        $this->assertNull(Ergani::employeeRelation('12345'));
        $this->assertSame(EmploymentKind::Unknown, Ergani::employmentRelationship('12345', '01/06/2025')->kind);
        $this->assertSame([], Ergani::employmentHistoryByPeriod('12345', '01/01/2025', '31/12/2025'));
    }

    public function test_ergani_responses_can_be_overridden_per_afm(): void
    {
        $relation = EmploymentRelation::fromRecord(['employerName' => 'ΔΕΙΓΜΑ']);
        Ergani::fake(
            relations: ['111111111' => $relation, '222222222' => null, '333333333' => ['employerName' => 'ΑΛΛΗ'], '444444444' => new GovNotFoundException('x')],
            statuses: ['111111111' => ['kind' => '1', 'typos' => '1']],
            history: ['111111111' => []],
        );

        $this->assertSame($relation, Ergani::employeeRelation('111111111'));
        $this->assertNull(Ergani::employeeRelation('222222222'));
        $this->assertSame('ΑΛΛΗ', Ergani::employeeRelation('333333333')?->employerName);
        $this->assertSame(EmploymentType::PartTime, Ergani::employmentRelationship('111111111', '01/01/2025')->type);
        $this->assertSame([], Ergani::employmentHistoryByPeriod('111111111', '01/01/2025', '31/12/2025'));

        $this->expectException(GovNotFoundException::class);
        Ergani::employeeRelation('444444444');
    }

    public function test_bank_confirm_confirms_every_account_unless_told_otherwise(): void
    {
        Http::fake();
        BankConfirm::fake(['GR0000000000000000000000002' => false, 'GR0000000000000000000000003' => ['bank' => 'ΑΛΛΗ', 'confirmed' => 'true']]);

        $this->assertSame('true', BankConfirm::confirmAccount('GR0000000000000000000000001', '123456780')['result']['confirmed']);
        $this->assertSame('false', BankConfirm::confirmAccount('GR0000000000000000000000002', '123456780')['result']['confirmed']);
        $this->assertSame('ΑΛΛΗ', BankConfirm::confirmAccount('GR0000000000000000000000003', '123456780')['result']['bank']);
        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_the_fake_confirms_relationships_unless_told_otherwise(): void
    {
        Amka::fake(confirmations: ['02029039233' => false, '03039039233' => new GovNotFoundException('x')]);

        $yes = Amka::confirmRelAmka('01019000015', parentAmka: '01019000015');
        $no = Amka::confirmRelAmka('02029039233', parentAmka: '01019000015');
        $many = Amka::confirmRelAmkaMultiple(['01019000015', '02029039233'], parentAmka: '01019000015');

        $this->assertTrue($yes->confirmed);
        $this->assertSame('ΤΕΚΝΟ', $yes->info);
        $this->assertFalse($no->confirmed);
        $this->assertSame([true, false], array_map(fn ($c) => $c->confirmed, $many));

        $this->expectException(GovNotFoundException::class);
        Amka::confirmRelAmka('03039039233', parentAmka: '01019000015');
    }

    public function test_the_fake_wants_the_parent_like_the_real_service(): void
    {
        Amka::fake();

        $this->expectException(InvalidArgumentException::class);

        Amka::confirmRelAmka('01019000015');
    }
}

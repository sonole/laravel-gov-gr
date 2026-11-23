<?php

namespace Sonole\GovGr\Tests;

use BadMethodCallException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Sonole\GovGr\Amka\Data\AmkaPerson;
use Sonole\GovGr\Amka\Facades\Amka;
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

    public function test_the_other_amka_methods_are_not_faked(): void
    {
        Amka::fake();

        $this->expectException(BadMethodCallException::class);

        Amka::amka2dataE('01019000015');
    }

    public function test_the_config_switch_installs_the_fakes_and_refuses_production(): void
    {
        config(['govgr.fake' => true]);
        (new GovGrServiceProvider($this->app))->boot();

        $this->assertSame(EntityKind::NaturalPerson, TaxRegistry::retrieveInfoByAFM('123456780')->kind);
        $this->assertTrue(FakeData::isValidAmka(Amka::data2amka('Α', 'Β', birthDate: '01/01/1990')->amka));

        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new GovGrServiceProvider($this->app))->boot();
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

<?php

namespace Sonole\GovGr\Ergani\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Sonole\GovGr\Ergani\Fakes\FakeErgani;
use Sonole\GovGr\Ergani\Data\EmploymentHistoryEntry;
use Sonole\GovGr\Ergani\Data\EmploymentRelation;
use Sonole\GovGr\Ergani\Data\EmploymentStatus;

/**
 * @method static ?EmploymentRelation employeeRelation(string $afm, ?Request $request = null)
 * @method static EmploymentStatus employmentRelationship(string $afm, string $refdate, ?Request $request = null)
 * @method static list<EmploymentHistoryEntry> employmentHistoryByPeriod(string $afm, string $refdateFrom, string $refdateTo, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Ergani\Ergani
 */
class Ergani extends Facade
{
    /**
     * Replaces the service with FakeErgani, which never calls the network. See FakeErgani for the answers it
     * gives and how to override single ones.
     *
     * @param array<string, EmploymentRelation|array<string, mixed>|\Throwable|null> $relations keyed by AFM
     * @param array<string, EmploymentStatus|array<string, mixed>|\Throwable> $statuses keyed by AFM
     * @param array<string, list<EmploymentHistoryEntry>|list<array<string, mixed>>|\Throwable> $history keyed by AFM
     */
    public static function fake(array $relations = [], array $statuses = [], array $history = []): FakeErgani
    {
        static::swap($fake = new FakeErgani($relations, $statuses, $history));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Ergani\Ergani::class;
    }
}
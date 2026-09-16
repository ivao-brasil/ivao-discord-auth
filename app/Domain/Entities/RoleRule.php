<?php

namespace App\Domain\Entities;

use Illuminate\Support\Collection;

class RoleRule
{
    public const DIVISION_ANY = 'any';
    public const DIVISION_IN = 'in';
    public const DIVISION_NOT_IN = 'not_in';

    private const LEGACY_MEMBER_SUFFIX = 'Member';

    private string $id;
    private string $name;
    /** @var Collection<int, string> */
    private Collection $roles;
    /** @var Collection<int, string> */
    private Collection $staff;
    private bool $includeTrial;
    private string $divisionMode;
    /** @var Collection<int, string> */
    private Collection $divisions;
    private ?int $minAtcRating;
    private ?int $minPilotRating;
    private ?float $minHours;
    private bool $requiresGca;
    private bool $requiresVaOwnership;

    public function __construct(array $data)
    {
        $this->id = (string) ($data['id'] ?? bin2hex(random_bytes(4)));
        $this->name = trim((string) ($data['name'] ?? ''));
        $this->roles = self::strings($data['roles'] ?? []);
        $this->staff = self::strings($data['staff'] ?? []);
        $this->includeTrial = (bool) ($data['includeTrial'] ?? true);
        $this->divisionMode = in_array($data['divisionMode'] ?? null, [self::DIVISION_IN, self::DIVISION_NOT_IN], true)
            ? $data['divisionMode']
            : self::DIVISION_ANY;
        $this->divisions = self::strings($data['divisions'] ?? [])->map(fn ($division) => strtoupper($division));
        $this->minAtcRating = self::nullableInt($data['minAtcRating'] ?? null);
        $this->minPilotRating = self::nullableInt($data['minPilotRating'] ?? null);
        $this->minHours = isset($data['minHours']) && $data['minHours'] !== '' ? (float) $data['minHours'] : null;
        $this->requiresGca = (bool) ($data['requiresGca'] ?? false);
        $this->requiresVaOwnership = (bool) ($data['requiresVaOwnership'] ?? false);
    }

    /**
     * Builds rules from stored data, accepting the legacy format ({hash, id: [roles], sulfix}).
     *
     * @return Collection<int, RoleRule>
     */
    public static function collection(iterable $rules): Collection
    {
        return Collection::make($rules)
            ->map(fn ($rule) => self::fromStored((array) $rule))
            ->filter(fn (RoleRule $rule) => $rule->roles->isNotEmpty())
            ->values();
    }

    public static function fromStored(array $data): self
    {
        if (! array_key_exists('sulfix', $data)) {
            return new self($data);
        }

        $suffix = trim((string) $data['sulfix']);

        return new self([
            'id' => $data['hash'] ?? null,
            'roles' => (array) ($data['id'] ?? []),
            'staff' => $suffix === self::LEGACY_MEMBER_SUFFIX ? [] : explode(':', $suffix),
            'includeTrial' => true,
        ]);
    }

    public function matches(Member $member): bool
    {
        if ($this->staff->isNotEmpty()
            && $member->getStaffPositions($this->includeTrial)->intersect($this->staff)->isEmpty()) {
            return false;
        }

        if ($this->divisionMode === self::DIVISION_IN && ! $this->divisions->contains($member->getDivision())) {
            return false;
        }

        if ($this->divisionMode === self::DIVISION_NOT_IN && $this->divisions->contains($member->getDivision())) {
            return false;
        }

        if ($this->minAtcRating !== null && ($member->getAtcRating() ?? 0) < $this->minAtcRating) {
            return false;
        }

        if ($this->minPilotRating !== null && ($member->getPilotRating() ?? 0) < $this->minPilotRating) {
            return false;
        }

        if ($this->minHours !== null && $member->getTotalHours() < $this->minHours) {
            return false;
        }

        if ($this->requiresGca && ! $member->hasGca()) {
            return false;
        }

        return ! $this->requiresVaOwnership || $member->ownsVirtualAirline();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, string> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /** @return Collection<int, string> */
    public function getStaff(): Collection
    {
        return $this->staff;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles->all(),
            'staff' => $this->staff->all(),
            'includeTrial' => $this->includeTrial,
            'divisionMode' => $this->divisionMode,
            'divisions' => $this->divisions->all(),
            'minAtcRating' => $this->minAtcRating,
            'minPilotRating' => $this->minPilotRating,
            'minHours' => $this->minHours,
            'requiresGca' => $this->requiresGca,
            'requiresVaOwnership' => $this->requiresVaOwnership,
        ];
    }

    private static function strings(iterable $values): Collection
    {
        return Collection::make($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private static function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}

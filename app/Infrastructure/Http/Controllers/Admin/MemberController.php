<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncStatusStore;
use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Entities\Guild;
use App\Infrastructure\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    private const PER_PAGE = 50;

    private $sync;
    private $statuses;
    private $guildService;

    public function __construct(MemberSyncService $sync, SyncStatusStore $statuses, GuildServiceContract $guildService)
    {
        $this->sync = $sync;
        $this->statuses = $statuses;
        $this->guildService = $guildService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in([SyncStatusStore::AWAY, SyncStatusStore::PENDING])],
        ]);

        $page = ConsentmentModel::query()
            ->where('status', true)
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('userVid', 'like', "{$search}%")
                ->orWhere('discordId', $search)
                ->orWhere('nickName', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereIn('id', $this->accountsWith($status)))
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $statuses = $this->statuses->all();

        return response()->json([
            'total' => $page->total(),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'members' => collect($page->items())->map(fn (ConsentmentModel $account) => [
                'id' => $account->id,
                'vid' => $account->userVid,
                'discordId' => $account->discordId,
                'nickname' => $account->nickName,
                'linkedAt' => $account->created_at?->toIso8601String(),
                'status' => $statuses[$account->id]['status'] ?? null,
            ]),
        ]);
    }

    public function show(ConsentmentModel $account): JsonResponse
    {
        try {
            $plan = $this->sync->plan($account);
        } catch (\Throwable $e) {
            Log::warning($e->getMessage(), ['event' => 'admin.member.plan_failed', 'vid' => $account->userVid]);

            return response()->json(['message' => __('admin.errors.unreachable')], 503);
        }

        $names = $this->roleNames();
        $member = $plan->member;

        return response()->json([
            'id' => $account->id,
            'vid' => $account->userVid,
            'discordId' => $account->discordId,
            'linkedAt' => $account->created_at?->toIso8601String(),
            'status' => $this->statuses->get($account->id),
            'away' => $plan->isAway(),
            'discord' => $plan->isAway() ? null : [
                'nickname' => $plan->discordMember['nick'] ?? $plan->discordMember['user']['username'] ?? null,
                'roles' => $names(Collection::make($plan->discordMember['roles'] ?? [])),
            ],
            'ivao' => $plan->isAway() ? null : ($member === null ? ['found' => false] : [
                'found' => true,
                'active' => $member->isActive(),
                'eligible' => $plan->eligible,
                'division' => $member->getDivision(),
                'staff' => $member->getStaff()->all(),
                'hours' => round($member->getTotalHours()),
            ]),
            'changes' => [
                'add' => $names(Collection::make($plan->add)),
                'remove' => $names(Collection::make($plan->remove)),
                'nickname' => $plan->nickname,
            ],
        ]);
    }

    /**
     * Asks the scheduler to sync every linked member on its next run.
     */
    public function syncAll(): JsonResponse
    {
        $this->sync->requestFullRun();

        Log::notice('Full sync requested', ['event' => 'admin.sync.requested']);

        return response()->json(['queued' => true]);
    }

    public function sync(ConsentmentModel $account): JsonResponse
    {
        try {
            $result = $this->sync->sync($account);
        } catch (\Throwable $e) {
            Log::warning($e->getMessage(), ['event' => 'admin.member.sync_failed', 'vid' => $account->userVid]);

            return response()->json(['message' => __('admin.errors.unreachable')], 503);
        }

        $names = $this->roleNames();

        return response()->json([
            'away' => $result->status === $result::AWAY,
            'added' => $names(Collection::make($result->added)),
            'removed' => $names(Collection::make($result->removed)),
            'nickname' => $result->nickname,
            'skipped' => $result->skipped,
        ]);
    }

    public function destroy(ConsentmentModel $account, ConsentmentServiceContract $consentments, IVAOApiServiceContract $IVAOAPI): JsonResponse
    {
        $this->guildService->removeFromServer($account->discordId, Guild::FromService($this->guildService));
        $consentments->deactivate($account);

        Log::notice('Member removed from the Discord server', [
            'event' => 'admin.member.removed',
            'admin' => $IVAOAPI->getUserData()['id'],
            'vid' => $account->userVid,
        ]);

        return response()->json(['removed' => true]);
    }

    /** @return int[] Failed syncs are listed together with pending changes */
    private function accountsWith(string $status): array
    {
        return $status === SyncStatusStore::PENDING
            ? array_merge($this->statuses->accountsWith(SyncStatusStore::PENDING), $this->statuses->accountsWith(SyncStatusStore::FAILED))
            : $this->statuses->accountsWith($status);
    }

    /** @return \Closure(Collection<int, string>): string[] */
    private function roleNames(): \Closure
    {
        $roles = Collection::make($this->guildService->getServerRoles(Guild::FromService($this->guildService)))->pluck('name', 'id');

        return fn (Collection $roleIds) => $roleIds->map(fn (string $roleId) => $roles[$roleId] ?? $roleId)->values()->all();
    }
}

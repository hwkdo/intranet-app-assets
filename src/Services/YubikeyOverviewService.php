<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class YubikeyOverviewService
{
    public function resolveYubikeyTypeId(): ?int
    {
        $typeId = AssetType::query()
            ->where('name', RegisterFidoAssetService::ASSET_TYPE_NAME)
            ->value('id');

        return $typeId !== null ? (int) $typeId : null;
    }

    /**
     * @return LengthAwarePaginator<User>
     */
    public function paginateActiveUsers(
        bool $onlyWithoutYubikey = false,
        string $search = '',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $yubikeyTypeId = $this->resolveYubikeyTypeId();
        $search = trim($search);

        $query = User::query()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->orderBy('id');

        if ($onlyWithoutYubikey && $yubikeyTypeId !== null) {
            $query->whereNotExists(function ($sub) use ($yubikeyTypeId): void {
                $sub->selectRaw('1')
                    ->from('intranet_app_assets_assets')
                    ->whereColumn('intranet_app_assets_assets.user_id', 'users.id')
                    ->where('intranet_app_assets_assets.asset_type_id', $yubikeyTypeId)
                    ->whereNull('intranet_app_assets_assets.deleted_at');
            });
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term, $yubikeyTypeId): void {
                $q->where('vorname', 'like', $term)
                    ->orWhere('nachname', 'like', $term)
                    ->orWhere('username', 'like', $term);

                if ($yubikeyTypeId !== null) {
                    $q->orWhereExists(function ($sub) use ($term, $yubikeyTypeId): void {
                        $sub->selectRaw('1')
                            ->from('intranet_app_assets_assets')
                            ->whereColumn('intranet_app_assets_assets.user_id', 'users.id')
                            ->where('intranet_app_assets_assets.asset_type_id', $yubikeyTypeId)
                            ->whereNull('intranet_app_assets_assets.deleted_at')
                            ->where('serial_number', 'like', $term);
                    });
                }
            });
        }

        /** @var LengthAwarePaginator<User> $users */
        $users = $query->paginate($perPage);

        if ($yubikeyTypeId !== null) {
            $this->attachYubikeys($users->getCollection(), $yubikeyTypeId, $search);
        }

        return $users;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function attachYubikeys(Collection $users, int $yubikeyTypeId, string $search = ''): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $assetsByUserId = Asset::query()
            ->with(['vendor', 'type'])
            ->where('asset_type_id', $yubikeyTypeId)
            ->whereIn('user_id', $users->pluck('id'))
            ->orderBy('serial_number')
            ->get()
            ->groupBy('user_id');

        $search = trim($search);

        foreach ($users as $user) {
            $all = $assetsByUserId->get($user->id, collect());

            if ($search === '' || $this->userMatchesSearch($user, $search)) {
                $user->setRelation('yubikeys', $all);

                continue;
            }

            $term = mb_strtolower($search);
            $filtered = $all->filter(
                fn (Asset $asset): bool => str_contains(mb_strtolower($asset->serial_number), $term)
            )->values();

            $user->setRelation('yubikeys', $filtered);
        }
    }

    private function userMatchesSearch(User $user, string $search): bool
    {
        $term = mb_strtolower($search);

        return str_contains(mb_strtolower((string) $user->vorname), $term)
            || str_contains(mb_strtolower((string) $user->nachname), $term)
            || str_contains(mb_strtolower((string) $user->username), $term);
    }
}

<?php


namespace App\Infrastructure\Services;


use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Entities\Consentment;
use Illuminate\Support\LazyCollection;

class ConsentmentService implements ConsentmentServiceContract
{

    public function create(Consentment $consentment)
    {
        $data = $consentment->toRaw();
        $data['roles'] = mb_substr((string) $data['roles'], 0, 255);

        $consentment = new ConsentmentModel($data);
        $consentment->save();
    }

    public function remove($userVid)
    {
        ConsentmentModel::where('userVid', $userVid)->where('status', true)->update(['status' => 0]);
    }

    public function hasAnotherLinkedAccount($userVid, $discordId)
    {
        $data = $this->getAnotherLinkedAccounts($userVid, $discordId);
        return sizeof($data) != 0;
    }

    public function getAnotherLinkedAccounts($userVid, $discordId)
    {
        return ConsentmentModel::where('uservid', $userVid)->where('discordId', '!=', $discordId)->get();
    }

    public function getActiveAccounts($userVid)
    {
        return ConsentmentModel::where('userVid', $userVid)->where('status', true)->get();
    }

    public function findActiveByDiscordId(string $discordId): ?ConsentmentModel
    {
        return ConsentmentModel::where('discordId', $discordId)->where('status', true)->latest('id')->first();
    }

    public function allActive(): LazyCollection
    {
        return ConsentmentModel::where('status', true)->lazyById();
    }

    public function updateSynced(ConsentmentModel $account, string $nickname, string $roles): void
    {
        $account->update([
            'nickName' => $nickname,
            'roles' => mb_substr($roles, 0, 255),
        ]);
    }
}

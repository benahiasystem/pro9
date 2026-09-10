<?php

namespace Modules\MultiUser\Services;

use App\Models\System\Client;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\MultiUser\Models\System\MultiUser;

class MultiUserAccessService
{
    /**
     * La identidad es el par empresa/usuario: los IDs de usuarios se repiten entre tenants.
     * Una cuenta espejo puede volver al origen o visitar los destinos de ese mismo origen.
     */
    public function resolve(Client $client, User $user, int $id, bool $isDestination): MultiUser
    {
        abort_unless($user->isActive(), 403, 'No tienes permiso para cambiar a esta empresa.');

        $anchor = $this->originAssociation($client, $user);
        $query = MultiUser::query();

        if ($isDestination) {
            $query->where(function (Builder $query) use ($client, $user, $anchor) {
                $query->where(function (Builder $query) use ($client, $user) {
                    $query->where('origin_client_id', $client->id)
                        ->where('origin_user_id', $user->id);
                });

                if ($anchor) {
                    $query->orWhere(function (Builder $query) use ($anchor) {
                        $query->where('origin_client_id', $anchor->origin_client_id)
                            ->where('origin_user_id', $anchor->origin_user_id);
                    });
                }
            });
        } else {
            // Solo el enlace que acredita a esta cuenta espejo permite el regreso.
            $query->whereKey($anchor ? $anchor->id : 0);
        }

        $association = $query->whereKey($id)->first();
        abort_unless($association, 403, 'No tienes permiso para cambiar a esta empresa.');

        return $association;
    }

    public function originAssociation(Client $client, User $user): ?MultiUser
    {
        if (! $user->is_multi_user || ! $user->multi_user_id) {
            return null;
        }

        return MultiUser::query()
            ->whereKey($user->multi_user_id)
            ->where('destination_client_id', $client->id)
            ->where('destination_user_id', $user->id)
            ->first();
    }
}

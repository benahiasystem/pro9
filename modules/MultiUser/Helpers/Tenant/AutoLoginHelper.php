<?php

namespace Modules\MultiUser\Helpers\Tenant;

use Illuminate\Support\Facades\Auth;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Illuminate\Support\Facades\Cache;
use Modules\MultiUser\Models\System\MultiUser;
use App\Models\Tenant\User;
use App\Models\System\Client;
use Modules\MultiUser\Services\MultiUserPermissionSync;
use Exception;
use Illuminate\Support\Facades\DB;

class AutoLoginHelper
{
    
    private const CACHE_KEY = 'auto_login';
    private const CACHE_TIME = 10;

    
    /**
     * 
     * Iniciar proceso de validacion e inicio de sesion automatico
     *
     * @return void
     */
    public function startProcess()
    {
        $hostname = $this->getCurrentHostname();

        if($hostname)
        {
            if($this->existLoginRequest($hostname->fqdn))
            {
                $login_data = $this->getLoginData($hostname->fqdn);

                $this->validateFqdn($login_data->fqdn, $hostname->fqdn);
    
                $this->deleteLoginRequest($hostname->fqdn);

                $this->runLogin($login_data);

                return;
            }
            
            $this->throwException("Acceso automático inválido, solicitud incorrecta: {$hostname->fqdn}");
        }
    }


    /**
     *
     * @param  object $login_data
     * @return void
     */
    private function runLogin($login_data)
    {
        $multi_user = $this->getMultiUser($login_data->multi_user_id);

        foreach (['origin_client_id', 'origin_user_id', 'destination_client_id', 'destination_user_id'] as $field) {
            abort_unless(isset($login_data->association->{$field})
                && (int) $login_data->association->{$field} === (int) $multi_user->{$field},
                403, 'La asociación del acceso solicitado ya no es válida.');
        }

        // La asociación puede cambiar mientras el acceso está pendiente en caché.
        $client_id = $login_data->is_destination ? $multi_user->destination_client_id : $multi_user->origin_client_id;
        $client = Client::filterDataMultiUser()->find($client_id);
        abort_unless($client && $client->hostname && $client->hostname->fqdn === $login_data->fqdn,
            403, 'La empresa del acceso solicitado ya no es válida.');

        $user_id = $login_data->is_destination ? $multi_user->destination_user_id : $multi_user->origin_user_id;

        $user = $this->findUser($user_id);

        $this->loginById($user);

        if ($user->is_multi_user) {
            MultiUserPermissionSync::syncFromTenantAdmin($user);
        }

        // \Log::info(
        //     "status: ". Auth::check()
        //     ." name: ".auth()->user()->name
        //     ." updated_at: ".auth()->user()->updated_at
        // );
    }

    
    /**
     *
     * @param  User $user
     * @return void
     */
    public function loginById(User $user)
    {
        Auth::loginUsingId($user->id);
    }


    /**
     *
     * @return CurrentHostname
     */
    public function getCurrentHostname()
    {
        return app(CurrentHostname::class);
    }

        
    /**
     *
     * @param  string $fqdn
     * @return bool
     */
    private function existLoginRequest($fqdn)
    {
        return Cache::has(self::CACHE_KEY."_{$fqdn}");
    }
       
        
    /**
     *
     * @param  array $data
     * @return void
     */
    public function saveLoginRequest($fqdn, $data)
    {
        $namespace = DB::table('hostnames')
                        ->select('websites.uuid')
                        ->join('websites', 'hostnames.website_id', '=', 'websites.id')
                        ->where('fqdn', $fqdn)
                        ->value('uuid');
        Cache::setPrefix($namespace);
        Cache::put(self::CACHE_KEY."_{$fqdn}", json_encode($data), self::CACHE_TIME);
    } 

    
    /**
     *
     * @param  string $fqdn
     * @param  string $previous_route
     * @return string
     */
    public function redirectUrl($fqdn, $previous_route)
    {
        $protocol = config('tenant.force_https') ? 'https' : 'http';
        
        return "{$protocol}://".$fqdn."/auto-login/{$fqdn}?previous_route={$previous_route}";
    }


    /**
     *
     * @param  string $fqdn
     * @return object
     */
    private function getLoginData($fqdn)
    {
        return json_decode(Cache::get(self::CACHE_KEY."_{$fqdn}"));
    }
    

    /**
     *
     * @param  string $fqdn
     * @return void
     */
    private function deleteLoginRequest($fqdn)
    {
        Cache::forget(self::CACHE_KEY."_{$fqdn}");
    }
        
    
    /**
     * @param  int $user_id
     * @return User
     */
    public function findUser($user_id)
    {
        $user = User::whereFilterWithOutRelations()->findOrFail($user_id);
        abort_unless($user->isActive(), 403, 'El usuario de la empresa destino está inactivo.');

        return $user;
    }

    
    /**
     *
     * @param  int $multi_user_id
     * @return MultiUser
     */
    public function getMultiUser($multi_user_id)
    {
        return MultiUser::select([
                                'id', 
                                'destination_user_id', 
                                'destination_client_id',
                                'origin_client_id',
                                'origin_user_id'
                            ])
                            ->findOrFail($multi_user_id);
    }

    
    /**
     *
     * @param  string $input_fqdn
     * @param  string $current_fqdn
     * @return void
     */
    public function validateFqdn($input_fqdn, $current_fqdn)
    {
        abort_unless($input_fqdn === $current_fqdn, 403, 'La empresa del acceso solicitado no coincide.');
    }

    
    /**
     *
     * @param  string $message
     * @return void
     */
    public function throwException($message)
    {
        throw new Exception($message);
    }


}

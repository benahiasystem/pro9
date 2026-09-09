<?php

namespace Modules\MultiUser\Traits\System;

use App\Models\System\Client;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;
use Modules\MultiUser\Models\System\MultiUser;
use App\Models\Tenant\User;
use App\Models\Tenant\ColumnsToReport;
use Modules\MultiUser\Services\MultiUserPermissionSync;
use Exception;


trait MultiUserTrait
{

    /**
     *
     * @param  Client $row
     * @param  array $users
     * @return void
     */
    private function setAllTenantUsers($row, &$users)
    {
        $tenancy = app(Environment::class);
        $tenancy->tenant($row->hostname->website);
        $client_id = $row->id;
        $client_full_name = $row->getFullName();

        DB::connection('tenant')
            ->table('users')
            ->where('is_multi_user', false)
            ->select([
                'id',
                'name',
                'email',
                'establishment_id',
                'type'
            ])
            ->get()
            ->each(function($row) use($users, $client_id, $client_full_name){

                $users->push([
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'establishment_id' => $row->establishment_id,
                    'type' => $row->type,
                    'client_id' => $client_id,
                    'client_full_name' => $client_full_name,
                    'composed_id' => "{$row->id}-{$client_id}",
                    'full_name' => "{$row->name} - {$row->email}",
                ]);

            });
    }


    /**
     *
     * @return array
     */
    private function getDataMultiUser()
    {
        $users = collect();
        $clients = collect();
        $base_clients = Client::filterDataMultiUser()->get();

        foreach ($base_clients as $client)
        {
            $this->setAllTenantUsers($client, $users);

            $clients->push([
                'id' => $client->id,
                'full_name' => $client->getFullName(),
            ]);
        }

        $users = $this->setLinkedClients($users);

        return compact('clients', 'users');
    }


    /**
     *
     * @param  \Illuminate\Support\Collection $users
     * @return \Illuminate\Support\Collection
     */
    private function setLinkedClients($users)
    {
        $linked = MultiUser::select(['origin_client_id', 'origin_user_id', 'destination_client_id'])
                            ->get()
                            ->groupBy(function($row){
                                return "{$row->origin_user_id}-{$row->origin_client_id}";
                            })
                            ->map(function($group){
                                return $group->pluck('destination_client_id')->unique()->values()->all();
                            });

        return $users->map(function($user) use($linked){

            $user['linked_client_ids'] = $linked->get($user['composed_id'], []);

            return $user;

        });
    }


    /**
     *
     * Listado agrupado: una fila por usuario con todas sus empresas vinculadas
     *
     * @param  Request $request
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    private function getGroupedMultiUserRecords($request)
    {
        $paginator = MultiUser::filterGroupedRecords($request)->paginate(config('tenant.items_per_page'));

        return $paginator->setCollection($this->buildGroupedMultiUserRows($paginator->getCollection()));
    }


    /**
     *
     * Arma cada fila del listado: usuario, empresa principal y todos sus vínculos
     *
     * @param  \Illuminate\Support\Collection $keys
     * @return \Illuminate\Support\Collection
     */
    private function buildGroupedMultiUserRows($keys)
    {
        if($keys->isEmpty()) return collect();

        $links = $this->getMultiUserLinks($keys);
        $types = $this->getCurrentUserTypes($links);

        return $keys->map(function($key) use($links, $types){

            $group = $links->get($this->getMultiUserGroupKey($key), collect());
            $first = $group->first();

            if(!$first) return null;

            $group->each(function($link) use($types){
                $link->current_type = $types["{$link->destination_client_id}-{$link->destination_user_id}"] ?? null;
            });

            return (object)[
                'id' => $key->id,
                'composed_id' => "{$key->origin_user_id}-{$key->origin_client_id}",
                'email' => $first->email,
                'user' => $first->user,
                'type' => $types["{$key->origin_client_id}-{$key->origin_user_id}"] ?? ($first->user->type ?? null),
                'origin_client' => $first->origin_client,
                'links' => $group->values(),
            ];

        })->filter()->values();
    }


    /**
     *
     * @param  \Illuminate\Support\Collection $keys
     * @return \Illuminate\Support\Collection
     */
    private function getMultiUserLinks($keys)
    {
        return MultiUser::withClientData()
                        ->whereIn('origin_user_id', $keys->pluck('origin_user_id')->unique()->values())
                        ->whereIn('origin_client_id', $keys->pluck('origin_client_id')->unique()->values())
                        ->orderBy('id')
                        ->get()
                        ->groupBy(function($row){
                            return $this->getMultiUserGroupKey($row);
                        });
    }


    /**
     *
     * @param  \Illuminate\Support\Collection $links
     * @return array
     */
    private function getCurrentUserTypes($links)
    {
        $types = [];
        $tenancy = app(Environment::class);

        foreach ($this->getUsersByClient($links) as $target)
        {
            $website = optional(optional($target['client'])->hostname)->website;

            if(!$website) continue;

            try
            {
                $tenancy->tenant($website);

                DB::connection('tenant')
                    ->table('users')
                    ->whereIn('id', $target['user_ids'])
                    ->select(['id', 'type'])
                    ->get()
                    ->each(function($row) use(&$types, $target){
                        $types["{$target['client_id']}-{$row->id}"] = $row->type;
                    });
            }
            catch(Exception $e)
            {
                continue;
            }
        }

        return $types;
    }


    /**
     *
     * @param  \Illuminate\Support\Collection $links
     * @return array
     */
    private function getUsersByClient($links)
    {
        $targets = [];

        foreach ($links->collapse() as $link)
        {
            $rows = [
                [$link->origin_client_id, $link->origin_user_id, $link->origin_client],
                [$link->destination_client_id, $link->destination_user_id, $link->destination_client],
            ];

            foreach ($rows as [$client_id, $user_id, $client])
            {
                if(!$client || !$user_id) continue;

                if(!isset($targets[$client_id]))
                {
                    $targets[$client_id] = [
                        'client_id' => $client_id,
                        'client' => $client,
                        'user_ids' => [],
                    ];
                }

                $targets[$client_id]['user_ids'][$user_id] = $user_id;
            }
        }

        return $targets;
    }


    /**
     *
     * @param  mixed $row
     * @return string
     */
    private function getMultiUserGroupKey($row)
    {
        return "{$row->origin_client_id}-{$row->origin_user_id}";
    }


    /**
     *
     * Gestionar multi usuario
     *
     * @param  MultiUserRequest $request
     * @return array
     */
    private function storeMultiUser($request)
    {
        $composed_id = $this->parseComposedId($request->composed_id);

        $origin_user_id = $composed_id['origin_user_id'];
        $origin_client_id = $composed_id['origin_client_id'];
        $destination_client_id = $request->destination_client_id;

        $exist_user_client = $this->existUserInClient($origin_client_id, $origin_user_id, $destination_client_id);
        if(!$exist_user_client['success']) return $exist_user_client;


        $origin_user = $this->getOriginUser($origin_user_id, $origin_client_id);

        // cambiar conexion a tenant destino
        $this->changeClientConnection($destination_client_id);

        $validate_destination_data = $this->validateDestinationData($request['user']['email']);
        if(!$validate_destination_data['success']) return $validate_destination_data;

        $this->saveClientData($origin_user, $origin_client_id, $request->all());

        return $this->generalResponse(true, 'Acceso registrado correctamente.');
    }


    /**
     *
     * Datos de usuario (cliente origen)
     *
     * @param  int $origin_user_id
     * @param  int $origin_client_id
     * @return User
     */
    private function getOriginUser($origin_user_id, $origin_client_id)
    {
        $this->changeClientConnection($origin_client_id);

        return User::whereFilterWithOutRelations()->findOrFail($origin_user_id);
    }


    /**
     *
     * @param  int $client_id
     * @return void
     */
    private function changeClientConnection($client_id)
    {
        $client = Client::findOrFail($client_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);
    }


    /**
     *
     * Validar si existe multi usuario registrado
     *
     * @param  int $origin_client_id
     * @param  int $destination_client_id
     * @param  int $origin_user_id
     * @return MultiUser
     */
    private function findDestinationMultiUser($origin_client_id, $destination_client_id, $origin_user_id)
    {
        return MultiUser::where('destination_client_id', $destination_client_id)
                        ->where('origin_user_id', $origin_user_id)
                        ->where('origin_client_id', $origin_client_id)
                        ->select('id')
                        ->first();
    }


    /**
     *
     * @param  string $email
     * @return User
     */
    private function existDestinationClientUser($email)
    {
        return User::whereFilterWithOutRelations()
                    ->where('email', $email)
                    ->select('id')
                    ->first();
    }


    /**
     *
     * @param  string $composed_id
     * @return array
     */
    private function parseComposedId($composed_id)
    {
        $composed_id = explode('-', $composed_id);

        return [
            'origin_user_id' => $composed_id[0],
            'origin_client_id' => $composed_id[1]
        ];
    }


    /**
     *
     * Validar si el usuario existe en cliente destino
     *
     * @param  int $origin_client_id
     * @param  int $origin_user_id
     * @param  int $destination_client_id
     * @return array
     */
    private function existUserInClient($origin_client_id, $origin_user_id, $destination_client_id)
    {
        $message = 'El usuario ya se encuentra registrado en la empresa seleccionada.';

        if($origin_client_id == $destination_client_id) return $this->generalResponse(false, $message);

        $multi_user = $this->findDestinationMultiUser($origin_client_id, $destination_client_id, $origin_user_id);

        if($multi_user) return $this->generalResponse(false, $message);

        return $this->generalResponse(true, null);
    }


    /**
     * Verificar si existe usuario con mismo email en cliente destino
     *
     * @param  string $email
     * @return array
     */
    private function validateDestinationData($email)
    {
        $user_by_email = $this->existDestinationClientUser($email);

        if($user_by_email) return $this->generalResponse(false, 'Existe un usuario con el mismo correo electrónico en la empresa seleccionada.');

        return $this->generalResponse(true, null);
    }


    /**
     *
     * Guardar registros en cliente destino
     *
     * @param  User $origin_user
     * @param  int $origin_client_id
     * @param  array $params
     * @return void
     */
    private function saveClientData($origin_user, $origin_client_id, $params)
    {
        $destination_user = $this->createUserToClient($origin_user);
        MultiUserPermissionSync::syncFromTenantAdmin($destination_user);
        $multi_user = $this->createMultiUser($origin_client_id, $origin_user->id, $destination_user->id, $params);

        $destination_user->multi_user_id = $multi_user->id;
        $destination_user->api_token = $origin_user->api_token;
        $destination_user->update();
    }


    /**
     *
     * Obtener primer establecimiento de cliente destino
     *
     * @return Establishment
     */
    public function getFirstEstablishment()
    {
        return DB::connection('tenant')
                ->table('establishments')
                ->select([
                    'id'
                ])
                ->first();
    }


    /**
     *
     * Crear usuario en cliente destino
     *
     * @param  User $origin_user
     * @return User
     */
    public function createUserToClient($origin_user)
    {
        $establishment = $this->getFirstEstablishment();

        return User::create([
            'name' => $origin_user->name,
            'email' => $origin_user->email,
            'password' => $origin_user->password,
            'establishment_id' => $establishment->id,
            'type' => $origin_user->type,
            'is_multi_user' => true
        ]);
    }


    /**
     *
     * @param  int $origin_client_id
     * @param  int $origin_user_id
     * @param  int $destination_user_id
     * @param  array $params
     * @return MultiUser
     */
    public function createMultiUser($origin_client_id, $origin_user_id, $destination_user_id, $params)
    {
        return MultiUser::create([
            'origin_client_id' => $origin_client_id,
            'origin_user_id' => $origin_user_id,
            'destination_client_id' => $params['destination_client_id'],
            'destination_user_id' => $destination_user_id,
            'email' => $params['user']['email'],
            'user' => $params['user']
        ]);
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


    /**
     *
     * @param  Exception $exception
     * @param  string $message
     * @return array
     */
    public function parseException($exception, $message)
    {
        $this->generalWriteErrorLog($exception, $message);

        return $this->generalResponse(false, $message.$exception->getMessage());
    }

    /**
     *
     * @return array
     */
    public function getMultiUserRelatedTables()
    {
        return [
            'default_document_types',
            'documents',
            'seller_documents',
            'sale_notes',
            'seller_sale_notes',
            'cashes',
            'contracts',
            'devolutions',
            'dispatches',
            'documentary_files',
            'documents_where_seller',
            'expenses',
            'fixed_asset_purchases',
            'global_payments',
            'incomes',
            'items_ratings',
            'order_forms',
            'order_notes',
            'perceptions',
            'purchase_orders',
            'purchase_quotations',
            'purchase_settlements',
            'purchases',
            'quotations',
            'retentions',
            'sale_opportunities',
            'summaries',
            'technical_services',
            'user_commissions',
            'voideds',
            'authorized_discount_users',
            // 'system_activity_logs',
        ];
    }


    /**
     *
     * @param  User $user
     * @return string|null
     */
    public function findUserRecordsTable($user)
    {
        foreach ($this->getMultiUserRelatedTables() as $table)
        {
            if ($user->$table()->exists()) return $table;
        }

        return null;
    }


    /**
     *
     * @param  int $id
     * @return array
     */
    public function canDeleteMultiUser($id)
    {
        $multi = MultiUser::findOrFail($id);

        $this->changeClientConnection($multi->destination_client_id);

        $user = User::whereFilterWithOutRelations()->find($multi->destination_user_id);

        // el usuario espejo ya no existe en la empresa: solo queda limpiar el vínculo
        if (!$user) return $this->generalResponse(true, null);

        $table = $this->findUserRecordsTable($user);

        if ($table) return $this->generalResponse(false, $this->getUserRecordsMessage($table));

        return $this->generalResponse(true, null);
    }


    /**
     *
     * @param  string $table
     * @return string
     */
    public function getUserRecordsMessage($table)
    {
        $labels = [
            'documents' => 'comprobantes',
            'seller_documents' => 'comprobantes como vendedor',
            'documents_where_seller' => 'comprobantes como vendedor',
            'sale_notes' => 'notas de venta',
            'seller_sale_notes' => 'notas de venta como vendedor',
            'quotations' => 'cotizaciones',
            'order_notes' => 'pedidos',
            'order_forms' => 'órdenes de pedido',
            'purchases' => 'compras',
            'purchase_orders' => 'órdenes de compra',
            'purchase_quotations' => 'cotizaciones de compra',
            'purchase_settlements' => 'liquidaciones de compra',
            'cashes' => 'cajas',
            'expenses' => 'gastos',
            'incomes' => 'ingresos',
            'contracts' => 'contratos',
            'devolutions' => 'devoluciones',
            'dispatches' => 'guías de remisión',
            'summaries' => 'resúmenes',
            'voideds' => 'comunicaciones de baja',
            'technical_services' => 'servicios técnicos',
            'global_payments' => 'pagos',
            'user_commissions' => 'comisiones',
        ];

        $label = $labels[$table] ?? $table;

        return "El usuario ya registró {$label} en esta empresa, por eso no se puede desvincular.";
    }


    /**
     *
     * @param  int $id
     * @return array
     */
    public function actionDelete($id)
    {
        $multi = MultiUser::find($id);
        $this->changeClientConnection($multi->destination_client_id);
        $user = User::whereFilterWithOutRelations()->findOrFail($multi->destination_user_id);

        $current_table = $this->findUserRecordsTable($user);

        if ($current_table) {
            // El usuario tiene al menos una relación en una de las tablas
            return $this->generalResponse(false, $this->getUserRecordsMessage($current_table));
        } else {
            // El usuario no tiene relaciones
            $columns = ColumnsToReport::where('user_id', $user->id)->delete(); // no tiene relacion inversa en modelo
            $user->system_activity_logs()->delete(); // se elimina ya que no hay relación con otras tablas
            $user->delete();
            $multi->delete();
            $message = 'El usuario ha sido eliminado.';
            return $this->generalResponse(true, $message);
        }
    }


}
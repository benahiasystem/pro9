<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

    namespace App\Http\Resources\System;

    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;

    class ClientResource extends JsonResource
    {
        /**
         * Transform the resource into an array.
         *
         * @param Request
         *
         * @return array
         */
        public function toArray($request)
        {

            // $all_modules = Module::orderBy('description')->get();
            // $modules_in_user = $this->modules->pluck('module_id')->toArray();
            // dd($all_modules,$modules_in_user);
            // $modules = [];
            // foreach ($all_modules as $module)
            // {
            //     $modules[] = [
            //         'id' => $module->id,
            //         'description' => $module->description,
            //         'checked' => (bool) in_array($module->id, $modules_in_user)
            //     ];
            // }

            return [
                'id' => $this->id,
                ...($this->fiscal_settings ?? []),
                'hostname' => $this->hostname->fqdn,
                'name' => $this->name,
                'email' => $this->email,
                'token' => $this->token,
                'number' => $this->number,
                'plan_id' => $this->plan_id,
                'price' => isset($this->price) ? (float)$this->price : $this->plan->pricing,
                'plan_period_id' => $this->plan_period_id,
                'locked' => (bool)$this->locked,
                'locked_emission' => (bool)$this->locked_emission,
                'whatsapp_messages_limit_override' => $this->whatsapp_messages_limit_override,
                'modules' => $this->modules,
                'apps' => $this->apps,
                'levels' => $this->levels,
                'business' => $this->business,
                'nrus' => (bool)$this->nrus,
                //'count_doc' => $this->count_doc,
                // 'max_documents' => (int) $this->plan->limit_documents,
                //'count_user' => $this->count_user,
                //'max_users' => (int) $this->plan->limit_users,
                'created_at' => $this->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

                'smtp_host' => $this->smtp_host,
                'smtp_port' => $this->smtp_port,
                'smtp_user' => $this->smtp_user,
                'smtp_password' => null, // dont show smtp password
                'smtp_encryption' => $this->smtp_encryption,
                'enable_list_product' => $this->enable_list_product,
                'contact_email' => $this->contact_email,
                'phone_ws' => $this->phone_ws,
                'client_name' => $this->client_name,
            ];

        }
    }
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

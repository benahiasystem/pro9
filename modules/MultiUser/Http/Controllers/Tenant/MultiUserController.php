<?php

namespace Modules\MultiUser\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\MultiUser\Traits\Tenant\MultiUserTrait;
use Modules\MultiUser\Helpers\Tenant\AutoLoginHelper;
use Modules\MultiUser\Http\Requests\Tenant\ChangeClientRequest;
use Modules\MultiUser\Services\MultiUserAccessService;


class MultiUserController extends Controller
{

    use MultiUserTrait;

    /**
     *
     * @param  Request $request
     * @return mixed
     */
    public function changeClient(ChangeClientRequest $request)
    {
        $is_destination = $request->boolean('is_destination');
        $helper = app(AutoLoginHelper::class);

        $multi_user = app(MultiUserAccessService::class)->resolve(
            $this->getCurrentClient(),
            $request->user(),
            (int) $request->input('multi_user_id'),
            $is_destination
        );

        $client_id = $is_destination ? $multi_user->destination_client_id : $multi_user->origin_client_id;

        $client = $this->getClient($client_id);

        $fqdn = $client->hostname->fqdn;
        $previous_route = Request::create(url()->previous())->path();

        $helper->saveLoginRequest($fqdn, [
            'fqdn' => $fqdn,
            'multi_user_id' => $multi_user->id,
            'is_destination' => $is_destination,
            'association' => $multi_user->only([
                'origin_client_id', 'origin_user_id', 'destination_client_id', 'destination_user_id',
            ]),
        ]);

        return redirect()->to($helper->redirectUrl($fqdn, $previous_route));
    }

   
    /**
     *
     * @return array
     */
    public function records()
    {
        $current_client = $this->getCurrentClient();

        $origin_client_id = $current_client->id;

        return $this->getTableMultiUsers($origin_client_id, $current_client);
    }

}

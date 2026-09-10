<?php

namespace Modules\MultiUser\Http\Controllers\System;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\MultiUser\Http\Resources\System\MultiUserCollection;
use Modules\MultiUser\Traits\System\MultiUserTrait;
use Modules\MultiUser\Http\Requests\System\MultiUserRequest;
use Illuminate\Support\Facades\DB;
use Exception;


class MultiUserController extends Controller
{

    use MultiUserTrait;

    /**
     * @return Response
     */
    public function index()
    {
        return view('multiuser::system.multi-users.index');
    }


    /**
     *
     * @return array
     */
    public function columns()
    {
        return [
            'email' => 'Correo del usuario',
            'origin_client' => 'Empresa principal',
            'destination_client' => 'Empresa vinculada',
        ];
    }


    /**
     *
     * @return array
     */
    public function tables()
    {
        return $this->getDataMultiUser();
    }


    /**
     *
     * @param  Request $request
     * @return MultiUserCollection
     */
    public function records(Request $request)
    {
        return new MultiUserCollection($this->getGroupedMultiUserRecords($request));
    }


    /**
     *
     * @param  MultiUserRequest $request
     * @return array
     */
    public function store(MultiUserRequest $request)
    {
        try
        {
            return DB::transaction(function () use ($request) {
                return $this->storeMultiUser($request);
            });
        }
        catch(Exception $e)
        {
            return $this->parseException($e, 'Ocurrió un error desconocido: ');
        }
    }

    /**
     *
     * Valida si el vínculo se puede desvincular, antes de confirmar
     *
     * @param  int $id
     * @return array
     */
    public function canDelete($id)
    {
        try
        {
            return $this->canDeleteMultiUser($id);
        }
        catch(Exception $e)
        {
            return $this->parseException($e, 'No se pudo validar la desvinculación: ');
        }
    }


    public function delete(Request $request) {

        return $this->actionDelete($request->id);
    }
}

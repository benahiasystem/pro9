<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\WahaServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WahaServerController extends Controller
{
    public function records()
    {
        return WahaServer::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:120',
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'engine' => 'required|in:NOWEB,GOWS,WEBJS,WPP',
            'active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        $id = $data['id'] ?? null;
        $server = $id ? WahaServer::findOrFail($id) : new WahaServer([
            'key' => WahaServer::generateUniqueKey($data['name']),
        ]);

        $server->name = $data['name'];
        $server->url = rtrim($data['url'], '/');
        $server->api_key = $data['api_key'];
        $server->engine = $data['engine'];
        $server->active = array_key_exists('active', $data) ? (bool) $data['active'] : true;
        $server->notes = $data['notes'] ?? null;
        $server->save();

        return [
            'success' => true,
            'message' => $id ? 'Servidor actualizado con éxito' : 'Servidor registrado con éxito',
        ];
    }

    public function destroy($id)
    {
        WahaServer::findOrFail($id)->delete();

        return [
            'success' => true,
            'message' => 'Servidor eliminado con éxito',
        ];
    }

    public function setDefault($id)
    {
        DB::connection('system')->transaction(function () use ($id) {
            WahaServer::query()->update(['is_default' => false]);
            WahaServer::findOrFail($id)->update(['is_default' => true]);
        });

        return [
            'success' => true,
            'message' => 'Servidor marcado como predeterminado',
        ];
    }
}

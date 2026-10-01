<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SearchRequest;
use App\Http\Resources\Tenant\SearchResource;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use Exception;

class SearchController extends Controller
{
    public function index()
    {
        return view('tenant.search.index');
    }

    public function tables()
    {
        $document_types = DocumentType::whereIn('id', ['01', '03', '07', '08'])->get();
        $establishments = \App\Models\Tenant\Establishment::select('id', 'description');
        $user = auth()->user();
        if ($user && $user->type !== 'admin') $establishments->where('id', $user->establishment_id);
        $establishments = $establishments->get();
        return compact('document_types', 'establishments');
    }

    public function store(SearchRequest $request)
    {
        $customer = Person::where('number', $request->input('customer_number'))
                            ->where('type', 'customers')
                            ->first();
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'El número del cliente ingresado no se encontró en la base de datos.'
            ];
        }

        $series = \App\Services\SeriesNumbering::normalizeCode($request->input('series'));
        $branch = $request->input('establishment_id') ?? optional(auth()->user())->establishment_id;
        $user = auth()->user();
        if ($user && $user->type !== 'admin') $branch = $user->establishment_id;
        if ($series === '' && !$branch) {
            return ['success' => false, 'message' => 'Indique la sucursal para buscar una factura sin serie.'];
        }
        $query = Document::where('date_of_issue', $request->input('date_of_issue'))
                            ->where('document_type_id', $request->input('document_type_id'))
                            ->where('series', $series)
                            ->where('number', (int) $request->input('number'))
                            ->where('total', $request->input('total'))
                            ->where('customer_id', $customer->id);
        if ($series === '') $query->where('establishment_id', $branch);
        $document = $query->first();
        if ($document) {
            return [
                'success' => true,
                'data' => new SearchResource($document)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'El documento no fue encontrado.'
            ];
        }
    }
}

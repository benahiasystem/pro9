<?php

namespace Modules\Document\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Models\Tenant\StateType;
use App\Services\SeriesCodeGenerator;
use Modules\Document\Models\SeriesConfiguration;
use Modules\Document\Http\Requests\SeriesConfigurationsRequest;
use App\Models\Tenant\Dispatch;


class SeriesConfigurationController extends Controller
{

    public function index()
    {
        return view('document::series_configurations.index');
    }

    public function records()
    {
        $records = $this->getRecords();
        return $records;
    }

    public function getRecords(){

        $records = SeriesConfiguration::get()->transform(function($row, $key) {

          if($row->document_type_id == '09') {
            $quantity_documents = Dispatch::where('series', $row->series)->where('document_type_id', '09')->count();
          } else{
            $quantity_documents = $this->getQuantityDocuments($row->document_type_id, $row->series);
          }

            return [
                'id' => $row->id,
                'series_id' => $row->series_id,
                'document_type_description' => $row->document_type->description,
                'series' => $row->series,
                'number' => $row->number,
                'initialized_description' => ($quantity_documents > 0) ? 'SI':'NO',
                'btn_delete' => ($quantity_documents > 0) ? false:true
                // 'initialized_description' => ($row->relationSeries->documents->count() > 0) ? 'SI':'NO',
                // 'btn_delete' => ($row->relationSeries->documents->count() > 0) ? false:true
            ];
        });

        return $records;

    }

    public function tables()
    {

        $establishmentId = auth()->user()->establishment_id;
        $document_type_ids = ['01', '07', '08', '09', '80'];

        if ((bool) optional(Configuration::first())->isNrus()) {
            $document_type_ids = array_values(array_intersect($document_type_ids, SeriesCodeGenerator::nrusDocumentTypeIds()));
        }

        $document_types = DocumentType::whereIn('id', $document_type_ids)->get();

        $series = Series::whereIn('document_type_id', $document_type_ids)
                        ->where('establishment_id', $establishmentId)
                        ->doesntHave('series_configurations')
                        // ->doesntHave('documents')
                        ->get();

        return compact('series', 'document_types');

    }

    private function getQuantityDocuments($document_type_id, $series){

        return Document::where([['document_type_id',$document_type_id],['series',$series]])->count();

    }

    public function store(SeriesConfigurationsRequest $request)
    {
        \App\Services\SeriesAdministration::authorize();
        $series = Series::findOrFail($request->series_id);
        if ($series->number !== strtoupper($request->series) || $series->document_type_id !== $request->document_type_id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['series' => 'La configuración no corresponde a la serie seleccionada.']);
        }
        return app(\App\Http\Controllers\Tenant\SeriesController::class)->updateCorrelative(
            Request::create('/', 'POST', ['correlative' => $request->number]), $series->id
        );
    }

    public function destroy($id)
    {
        return \App\Services\SeriesAdministration::transaction(function () use ($id) {
            $record = SeriesConfiguration::findOrFail($id);
            $series = Series::lockForUpdate()->findOrFail($record->series_id);
            if (\App\Services\SeriesNumbering::used($series)) return ['success' => false, 'message' => 'La serie ya tiene documentos registrados.'];
            $record->delete();
            return ['success' => true, 'message' => 'Configuración de serie eliminada con éxito'];
        });
    }
}

<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Webhook\Services\Payloads;

use App\Models\Tenant\Document;
use App\Models\Tenant\StateType;
use Hyn\Tenancy\Environment;

/**
 * Payload de los eventos document.* para documentos registrados localmente.
 */
class DocumentPayload implements PayloadBuilderInterface
{
    /**
     * @param Document $model
     */
    public function build($model): array
    {
        $customer = $model->customer;
        $localResponse = \App\Services\LocalFiscalDocumentPolicy::registeredResponse();
        $baseUrl = $this->tenantBaseUrl();

        return [
            'id' => $model->id,
            'external_id' => $model->external_id,
            'number' => $model->series.'-'.$model->number,
            'filename' => $model->filename,
            'state_type_id' => $model->state_type_id,
            'state_type_description' => optional(StateType::find($model->state_type_id))->description,
            'number_to_letter' => optional(collect($model->legends)->firstWhere('code', '1000'))->value,
            'date_of_issue' => optional($model->date_of_issue)->format('Y-m-d'),
            'document_type_id' => $model->document_type_id,
            'currency_type_id' => $model->currency_type_id,
            'exchange_rate_sale' => $model->exchange_rate_sale,
            'total' => $model->total,
            'customer' => $customer ? [
                'identity_document_type_id' => $customer->identity_document_type_id ?? null,
                'number' => $customer->number ?? null,
                'name' => $customer->name ?? null,
            ] : null,
            'links' => [
                'pdf' => "{$baseUrl}/downloads/document/pdf/{$model->external_id}",
            ],
            'local_response' => $localResponse,
            'fiscal_environment' => $model->fiscal_environment,
            'fiscal_emission_mode' => $model->fiscal_emission_mode,
        ];
    }

    /**
     * URL base del tenant sin usar route(): las rutas de descarga solo se
     * registran cuando hay hostname (request web), y los eventos de estado
     * también se disparan desde comandos de consola.
     */
    private function tenantBaseUrl(): string
    {
        $website = app(Environment::class)->tenant();
        $fqdn = $website ? optional($website->hostnames()->first())->fqdn : null;

        if (!$fqdn) {
            return rtrim(url('/'), '/');
        }

        $scheme = config('tenant.force_https') ? 'https' : 'http';

        return "{$scheme}://{$fqdn}";
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

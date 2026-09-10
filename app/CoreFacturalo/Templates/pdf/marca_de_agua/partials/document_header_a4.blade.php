{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@php
    $logo = null;

    if ($company->logo) {
        $logo = "storage/uploads/logos/{$company->logo}";
    } else if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $type = App\CoreFacturalo\Helpers\Template\TemplateHelper::getFiscalEnvironment();
@endphp

@if($document->state_type->id == '11')
    <div class="company_logo_box" style="position: absolute; text-align: center; top: 30%; left: 0; right: 0;">
        <img
            src="data:{{ mime_content_type(public_path('status_images/anulado.png')) }};base64,{{ base64_encode(file_get_contents(public_path('status_images/anulado.png'))) }}"
            alt="anulado"
            style="opacity: 0.6; max-width: 300px; width: auto; height: auto;"
        >
    </div>
@else
    <div class="item_watermark" style="
        position: absolute;
        top: 35%;
        left: 10%;
        width: 80%;
        height: 300px;
        text-align: center;
    ">
    @if (isset($logo) && $logo && \App\CoreFacturalo\Helpers\Template\TemplateHelper::existsFileInUploads($logo))
        <img
            src="data:{{ mime_content_type(public_path("{$logo}")) }};base64,{{ base64_encode(file_get_contents(public_path("{$logo}"))) }}"
            alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}"
            style="width: 100%; height: auto; object-fit: contain; opacity: 0.1;"
        >
    @endif
    </div>
@endif
@if($document->state_type->id == '09')
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))}}"
            alt="rechazado" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
@if(isset($configuration['is_preview']) && $configuration['is_preview'])
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png")))}}"
            alt="vista previa" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
@if ($type->fiscal_environment === 'demo')
    <table class="full-width">
        <tr>
            <td style="width: 100%;text-align: center">
                <span style="color: red; font-weight: bold; font-size: 1.6rem;">Este comprobante es solo de prueba</span>
            </td>
        </tr>
    </table>
@endif
<table class="full-width">
    <tr>
        @if($company->logo || ($establishment->logo && \App\CoreFacturalo\Helpers\Template\TemplateHelper::existsFileInUploads($establishment->logo)))
            <td width="20%">
                <div class="company_logo_box">
                    <img src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}" alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="company_logo" style="max-width: 250px;">
                </div>
            </td>
            <td width="40%" class="text-center">
                <div class="text-left">
                    @include('pdf.partials.company_document_header_names')
                    <h5>{{ 'RIF '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset

                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="40%" class="border-box py-2 px-2 text-center">
                <h3 class="font-bold">{{ 'R.I.F. '.$company->number }}</h3>
                <h3 class="text-center font-bold">{{ $document->document_type->description }}</h3>
                <br>
                <h3 class="text-center font-bold">{{ $document_number }}</h3>
            </td>
        @else
            <td width="40%" class="pl-1">
                <div class="text-left">
                    @include('pdf.partials.company_document_header_names')
                    <h5>{{ 'RIF '.$company->number }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
                        {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
                        {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset

                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="40%" class="border-box py-2 px-2 text-center">
                <h3 class="font-bold">{{ 'R.I.F. '.$company->number }}</h3>
                <h3 class="text-center font-bold">{{ $document->document_type->description }}</h3>
                <br>
                <h3 class="text-center font-bold">{{ $document_number }}</h3>
            </td>
        @endif
    </tr>
</table>
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}

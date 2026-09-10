{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')
    <div class="row row-mx-0">
        <div class="col-lg-12">
            <tenant-configurations-form
                :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
                :type-user="{{ json_encode(auth()->user()->type) }}"
                :can-delete-test-documents="{{ json_encode(auth()->user()->isAdmin()) }}"></tenant-configurations-form>
        </div>
    </div>
@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}

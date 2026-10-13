@extends('tenant.layouts.app')

@section('content')

    <tenant-documents-note
        :auth-user="{{ json_encode(Auth::user()->only(['id', 'type', 'establishment_id'])) }}"
        :document_affected="{{ json_encode($document_affected) }}"
        :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
    ></tenant-documents-note>

@endsection

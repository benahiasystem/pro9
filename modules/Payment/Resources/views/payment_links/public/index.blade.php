@extends('tenant.layouts.web')

@section('content')

    {{-- el layout centra el contenido a pantalla completa, aca se libera el alto y se amplia la columna --}}
    <style>
        body.body-web {
            height: auto;
            min-height: 100vh;
            background-color: #f6f7fb;
        }
        body.body-web #main-wrapper {
            height: auto;
            min-height: 100vh;
            padding: 40px 16px;
        }
        body.body-web #main-wrapper > .row {
            height: auto;
            align-items: flex-start;
            margin: 0;
        }
        body.body-web #main-wrapper > .row > .col-md-6 {
            flex: 0 0 auto;
            width: 100%;
            max-width: 660px;
            padding: 0;
        }
    </style>

    <tenant-public-payment-links-index
        :payment_link="{{json_encode($payment_link)}}"
        :company="{{json_encode($company)}}"
        :payment_configuration="{{json_encode($payment_configuration)}}"
        :total="{{json_encode($total)}}"
        :apply_conversion="{{json_encode($apply_conversion)}}"
    >
    </tenant-public-payment-links-index>

@endsection

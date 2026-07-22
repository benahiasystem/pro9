@extends('system.layouts.app')

@section('content')
    <marketplace-admin :summary='@json($summary)'></marketplace-admin>
@endsection

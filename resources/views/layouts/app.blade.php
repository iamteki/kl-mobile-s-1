<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KL Mobile Events - Event Equipment Rental & DJ Services')</title>
    <meta name="description" content="@yield('description', 'Professional event equipment rental in Kuala Lumpur. Sound systems, lighting, LED screens, DJ services with instant booking.')">
    
    <!-- Styles -->
    @vite(['resources/css/app.css'])
    @stack('styles')
</head>
<body>
    @include('layouts.partials.header')
    
    @yield('content')
    
    @include('layouts.partials.footer')
    
    <!-- Scripts -->
    @vite(['resources/js/app.js'])
    @stack('scripts')
</body>
</html>
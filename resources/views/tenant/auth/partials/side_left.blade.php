<style>
    .auth__image { display: flex; }
    @media (max-width: 800px) { .auth__image { display: none; } }
</style>
<article class="auth__image" style="padding: {{ ($login->padding_in_form ?? false) ? '0' : '2.5%' }}; justify-content: center; align-items: center; overflow: hidden; background-color: {{ $loginBgColor ?? '#ffffff' }};">
    @if ($login->show_background_image ?? true)
    <img 
        src="{{ $login->image }}" 
        alt="Background Image" 
        style="width: 100%; height: 100%; object-fit: {{ ($login->padding_in_form ?? false) ? 'cover' : 'contain' }}" 
    />
    @endif
    @if (($login->show_logo_in_form ?? false) && $useLoginGlobal)
        @if ($login->logo ?? false)
            @if ($login->position_logo != 'none' && $login->position_logo != 'on-form')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ $login->logo }}" alt="Logo" />
            @endif
        @endif
    @elseif ($login->show_logo_in_form ?? false)
        @if($company->logo)
            @if ($login->position_logo != 'none' && $login->position_logo != 'on-form')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo" />
            @endif
        @else
            @if ($login->position_logo != 'none' && $login->position_logo != 'on-form')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('logo/tulogo.png') }}" alt="Logo" />
            @endif
        @endif
    @endif
</article>

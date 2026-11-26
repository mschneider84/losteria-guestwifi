@if(session('ok'))<div class="flash ok">✓ {{ session('ok') }}</div>@endif
@if(session('error'))
    <div class="flash bad">⚠ {{ session('error') }}
        <button class="link" onclick="document.getElementById('fb').showModal();document.querySelector('#fb [value=bug]').checked=true;document.querySelector('#fb textarea').value=@js(session('error'))">{{ __('Diesen Fehler melden') }}</button>
    </div>
@endif
@if($errors->any())<div class="flash bad">⚠ {{ $errors->first() }}</div>@endif

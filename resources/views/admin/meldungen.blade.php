@extends('layouts.admin')
@section('title', __('Wünsche & Fehler'))
@section('content')
<h1>💡 {{ __('Wünsche & Fehler') }}</h1>
@if($meldungen->isEmpty())
    <div class="empty"><p>{{ __('Noch keine Meldungen. Über die Lasche „Feedback“ rechts kann jeder Wünsche, Fehler oder Lob schicken.') }}</p></div>
@else
<section class="panel flush">
<table class="table">
    <thead><tr><th>#</th><th>{{ __('Art') }}</th><th>{{ __('Meldung') }}</th><th>{{ __('Von') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @foreach($meldungen as $m)
        <tr>
            <td>{{ $m->id }}</td>
            <td>{{ ['wish' => '💡', 'bug' => '🐞', 'praise' => '👍', 'auto' => '🤖'][$m->type] ?? '' }}</td>
            <td><strong>{{ $m->title }}</strong><br><span class="muted small">{{ $m->page_title }} · {{ $m->app_version }}</span></td>
            <td>{{ $m->user?->name }}</td>
            <td>
                <form method="post" action="{{ route('admin.meldungen.status', $m) }}" class="row">@csrf @method('PUT')
                    <select name="status" onchange="this.form.submit()">
                        @foreach(['new' => __('Neu'), 'review' => __('In Prüfung'), 'ai' => '🤖 '.__('An KI übergeben'), 'planned' => __('Geplant'), 'done' => __('Erledigt'), 'rejected' => __('Abgelehnt')] as $k => $v)
                            <option value="{{ $k }}" @selected($m->status === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>
{{ $meldungen->links() }}
@endif
@endsection

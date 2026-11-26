<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\NewsletterContact;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $demo = (bool) session('demo');
        $status = in_array($request->query('status'), ['pending', 'confirmed', 'unsubscribed'], true) ? $request->query('status') : null;

        $contacts = NewsletterContact::with('location:id,name')
            ->where('is_demo', $demo)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->integer('standort'), fn ($q, $id) => $q->where('location_id', $id))
            ->when($request->query('q'), fn ($q, $s) => $q->where('email', 'like', '%'.$s.'%'))
            ->latest()->paginate(25)->withQueryString();

        $counts = NewsletterContact::where('is_demo', $demo)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.contacts', [
            'contacts' => $contacts,
            'counts' => $counts,
            'status' => $status,
            'locations' => Location::mode($demo)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** CSV-Export nur der bestätigten Kontakte (Double-Opt-in), z. B. für den Import ins Newsletter-Tool. */
    public function export(Request $request): StreamedResponse
    {
        $demo = (bool) session('demo');
        Activity::log('contacts.export');

        return response()->streamDownload(function () use ($demo, $request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['E-Mail', 'Sprache', 'Standort', 'Einwilligung', 'Bestätigt', 'Textversion'], ';');
            NewsletterContact::with('location:id,name')->where('is_demo', $demo)->where('status', 'confirmed')
                ->when($request->integer('standort'), fn ($q, $id) => $q->where('location_id', $id))
                ->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $c) {
                        fputcsv($out, [$c->email, $c->language, $c->location?->name, $c->consent_at?->format('d.m.Y H:i'),
                            $c->confirmed_at?->format('d.m.Y H:i'), $c->consent_text_version], ';');
                    }
                });
            fclose($out);
        }, 'newsletter-kontakte-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

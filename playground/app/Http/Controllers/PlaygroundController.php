<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AftermathPathfinder\StoreForward\Facades\StoreForward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The entire playground UI in one controller — deliberately not split into
 * multiple controllers/services, since this app exists to be read start to
 * finish by a human (or an AI assistant) in one sitting, not to model good
 * large-app architecture. See CLAUDE.md for the tour.
 */
class PlaygroundController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'channels' => array_keys(config('store-forward.channels')),
        ]);
    }

    /**
     * JSON feed the dashboard polls. Deliberately returns *all* columns
     * the outbox table tracks, so the UI can show the full lifecycle:
     * status, attempts, last_error, and every timestamp.
     */
    public function messages(): JsonResponse
    {
        $rows = DB::table(config('store-forward.table'))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'messages' => $rows,
            'counts' => DB::table(config('store-forward.table'))
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string',
            'payload' => 'required|string',
        ]);

        $payload = json_decode($validated['payload'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['payload' => 'Payload must be valid JSON.'])->withInput();
        }

        StoreForward::publish($validated['channel'], $payload);

        return back()->with('status', "Published to [{$validated['channel']}].");
    }

    /**
     * Drains one batch of pending messages right now, synchronously, in
     * the request — never do this in a real app (that's what
     * `store-forward:work` is for), but it's what makes the playground
     * clickable instead of needing a worker process running in a second
     * terminal.
     */
    public function process(): RedirectResponse
    {
        Artisan::call('store-forward:work', ['--once' => true, '--limit' => 50]);

        return back()->with('status', trim(Artisan::output()) ?: 'Nothing pending.');
    }

    public function retryDead(): RedirectResponse
    {
        Artisan::call('store-forward:retry');

        return back()->with('status', trim(Artisan::output()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\StoreForwardDemo\DriverCatalog;
use App\Support\EnvFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets you pick which real infrastructure the `demo.custom` channel uses,
 * and fill in that driver's credentials — without hand-editing .env or
 * config/store-forward.php. See DriverCatalog for the "litmus test" this
 * runs before ever letting you switch to a driver: its package has to
 * actually be installed (class_exists() on that driver's service
 * provider), or this shows a warning and refuses to switch, rather than
 * saving a selection that would just throw on the next publish.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        $drivers = DriverCatalog::all();
        $installed = [];
        foreach ($drivers as $key => $driver) {
            $installed[$key] = DriverCatalog::isInstalled($key);
        }

        return view('settings', [
            'drivers' => $drivers,
            'installed' => $installed,
            // Read the env var directly rather than
            // config('store-forward.channels.demo.custom.driver') — that
            // dotted path would be misread as channels->demo->custom
            // instead of the literal key "demo.custom", the exact bug
            // fixed in StoreForwardManager (see the root CHANGELOG).
            'currentDriver' => env('STORE_FORWARD_CUSTOM_DRIVER', 'log'),
            'currentValues' => $this->currentValuesByDriver($drivers),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $driver = $request->string('driver')->toString();
        $drivers = DriverCatalog::all();

        if (! isset($drivers[$driver])) {
            return back()->withErrors(['driver' => "Unknown driver [{$driver}]."]);
        }

        if (! DriverCatalog::isInstalled($driver)) {
            $package = $drivers[$driver]['package'];

            return back()->with('warning',
                "store-forward-{$driver} isn't installed yet, so demo.custom hasn't been changed. ".
                "Run: composer require {$package}"
            );
        }

        $values = ['STORE_FORWARD_CUSTOM_DRIVER' => $driver];
        foreach ($drivers[$driver]['fields'] as $field) {
            $values[$field['env']] = (string) $request->input("fields.{$field['env']}", '');
        }

        (new EnvFileWriter(base_path('.env')))->set($values);

        return redirect()->route('settings')
            ->with('status', "demo.custom now uses [{$driver}]. Publish to it from the dashboard to try it.");
    }

    /**
     * @param  array<string, array{fields: array<int, array{env: string}>}>  $drivers
     * @return array<string, array<string, string>>
     */
    protected function currentValuesByDriver(array $drivers): array
    {
        $values = [];
        foreach ($drivers as $key => $driver) {
            foreach ($driver['fields'] as $field) {
                $values[$key][$field['env']] = (string) env($field['env'], '');
            }
        }

        return $values;
    }
}

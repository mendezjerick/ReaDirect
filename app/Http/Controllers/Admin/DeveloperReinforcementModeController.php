<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\DeveloperReinforcementModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeveloperReinforcementModeController extends Controller
{
    public function update(Request $request, AdminAccessService $access, DeveloperReinforcementModeService $mode): RedirectResponse
    {
        $access->ensureAdmin($request->user());

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $mode->setEnabled((bool) $validated['enabled'], $request->user());

        return back()->with('success', 'Developer reinforcement mode updated.');
    }
}

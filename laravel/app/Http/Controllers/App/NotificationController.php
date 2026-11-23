<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The in-app notification center: the bell-icon list behind every row
 * App\Support\Notifications::record() writes. Company-wide (not per-user,
 * same tenant-isolation shape as ActivityLogController) — strictly scoped
 * to `where('company_id', Auth::user()->company_id)`, never another
 * company's rows.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = Auth::user()->company_id;

        $notifications = Notification::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('app.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    /** Marks one notification read — 404s (rather than leaking existence) if it belongs to another company. */
    public function markRead(int $id): RedirectResponse
    {
        $companyId = Auth::user()->company_id;
        $notification = Notification::where('company_id', $companyId)->where('id', $id)->first();
        abort_if(!$notification, 404);

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        $companyId = Auth::user()->company_id;
        Notification::where('company_id', $companyId)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}

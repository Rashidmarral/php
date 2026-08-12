<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function flash(string $type, string $message): void
    {
        session()->push("flash.{$type}", $message);
    }

    /** Sets a flash message and redirects in one call, mirroring the original app's flash()+redirect() pairing. */
    protected function redirectWithFlash(string $path, string $type, string $message): RedirectResponse
    {
        $this->flash($type, $message);
        return redirect($path);
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Pdf\Pdf;
use Illuminate\Console\Command;

/**
 * Pre-registers the Cairo/Noto Naskh Arabic fonts into dompdf's shared cache
 * once, synchronously, at deploy time — instead of leaving that work to
 * whichever PDF-generating request happens to hit a cold cache first. dompdf
 * writes its font cache files without any locking of its own, so if several
 * requests race to register the same font on a freshly cleared cache (e.g.
 * right after a deploy), one request's write can land on top of another's
 * and leave a font with no embedded glyph data at all — see Pdf::output()'s
 * own comment for the production incident this traces back to. Running this
 * once, before the deploy serves any traffic, means that race essentially
 * never gets a chance to happen. Wired into .cpanel.yml's deployment tasks.
 */
class WarmPdfFonts extends Command
{
    protected $signature = 'pdf:warm-fonts';

    protected $description = 'Pre-registers the PDF fonts into dompdf\'s cache so the first real request never races to build it';

    public function handle(): int
    {
        Pdf::warmFontCache();
        $this->info('PDF font cache warmed.');

        return self::SUCCESS;
    }
}

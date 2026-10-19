<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rooms:dispatch-outbox --limit=100')->everyMinute()->withoutOverlapping();

Artisan::command('maps:prune-import-previews', function () {
    $count=0;
    foreach (\App\Models\MapImportPreview::where('image_path','!=','')->where(fn($q)=>$q->whereNotNull('confirmed_at')->orWhere('expires_at','<',now()))->cursor() as $preview) {
        \Illuminate\Support\Facades\DB::transaction(function() use ($preview,&$count) {
            // Same lock order as confirmation/cancel: room, then preview.
            \App\Models\Room::whereKey($preview->room_id)->lockForUpdate()->first();
            $p=\App\Models\MapImportPreview::whereKey($preview->id)->lockForUpdate()->first();
            if (!$p || $p->image_path==='' || (!$p->confirmed_at && !$p->expires_at->isPast())) return;
            $expected="imports/staging/{$p->id}/image";
            if ($p->image_path!==$expected) throw new \RuntimeException('Unexpected staging path; no cleanup performed.');
            if (!\Illuminate\Support\Facades\Storage::disk('local')->delete($expected)) throw new \RuntimeException('Staging cleanup failed.');
            if ($p->confirmed_at) $p->update(['image_path'=>'']); else $p->delete();
            $count++;
        });
    }
    $this->info("Expired previews removed: $count");
})->purpose('Remove only expired, unconfirmed dd2vtt staging files and previews');
Schedule::command('maps:prune-import-previews')->hourly()->withoutOverlapping();

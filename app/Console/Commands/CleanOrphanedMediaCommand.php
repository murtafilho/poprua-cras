<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CleanOrphanedMediaCommand extends Command
{
    protected $signature = 'media:clean-orphaned {--dry-run : List without deleting}';

    protected $description = 'Remove media records whose parent vistoria was hard-deleted';

    public function handle(): int
    {
        $orphaned = Media::where('model_type', 'App\\Models\\Vistoria')
            ->whereNotIn('model_id', function ($query) {
                $query->select('id')->from('vistorias');
            })
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned media found.');

            return self::SUCCESS;
        }

        $this->info("Found {$orphaned->count()} orphaned media record(s).");

        if ($this->option('dry-run')) {
            $orphaned->each(fn (Media $m) => $this->line("  #{$m->id} — {$m->name} (vistoria_id: {$m->model_id})"));

            return self::SUCCESS;
        }

        $deleted = 0;
        $orphaned->each(function (Media $media) use (&$deleted) {
            $media->delete();
            $deleted++;
        });

        $this->info("Deleted {$deleted} orphaned media record(s) and their files.");

        return self::SUCCESS;
    }
}

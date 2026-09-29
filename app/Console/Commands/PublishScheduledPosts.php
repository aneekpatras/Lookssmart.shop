<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Phase 10 sub-step 2: promotes `scheduled` posts to `published` once their `scheduled_at` time has
 * passed. Without this, a post left in `status = 'scheduled'` would never actually go live — the
 * public `Post::scopePublished()` (Phase 9) only matches `status = 'published'`, deliberately, so a
 * scheduled post can't leak early via a race on `published_at` alone.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Promote scheduled blog posts to published once their scheduled time has passed';

    public function handle(): int
    {
        $due = Post::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->scheduled_at,
            ]);
        }

        $this->info("Published {$due->count()} scheduled post(s).");

        return self::SUCCESS;
    }
}

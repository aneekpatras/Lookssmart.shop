<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Services\HtmlSanitizerService;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    private const SORTABLE_COLUMNS = ['title', 'status', 'published_at', 'created_at'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Post::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'created_at';

        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        $posts = Post::query()
            ->with(['category:id,name', 'author:id,name'])
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%' . $request->string('search')->value() . '%'))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/CMS/Blog/Index', [
            'posts' => $posts->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'category' => $post->category?->name,
                'author' => $post->author?->name,
                'published_at' => $post->published_at?->toIso8601String(),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            ]),
            'categories' => PostCategory::orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Post::class);

        return Inertia::render('Admin/CMS/Blog/Form', [
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePostRequest $request, HtmlSanitizerService $sanitizer, SecureUploadService $uploads): RedirectResponse
    {
        $data = $this->preparePostData($request, $sanitizer, null);

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $uploads->storePublicImage($request->file('cover_image'), 'blog');
        }

        $data['author_id'] = $request->user()->id;

        $post = Post::create($data);
        $this->syncTags($post, $request->string('tags')->value());

        return redirect()->route('admin.blog')->with('success', "\"{$post->title}\" created.");
    }

    public function edit(Post $post): Response
    {
        $this->authorize('update', $post);

        $post->load('tags:id,name');

        return Inertia::render('Admin/CMS/Blog/Form', [
            'post' => [
                ...$post->only([
                    'id', 'title', 'slug', 'excerpt', 'body', 'post_category_id',
                    'seo_title', 'seo_description', 'status',
                ]),
                'scheduled_at' => $post->scheduled_at?->toDateTimeString(),
                'cover_image_url' => $post->cover_image_path ? asset("storage/{$post->cover_image_path}") : null,
                'tags' => $post->tags->pluck('name')->implode(', '),
            ],
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post, HtmlSanitizerService $sanitizer, SecureUploadService $uploads): RedirectResponse
    {
        $data = $this->preparePostData($request, $sanitizer, $post);

        if ($request->hasFile('cover_image')) {
            $oldPath = $post->cover_image_path;
            $data['cover_image_path'] = $uploads->storePublicImage($request->file('cover_image'), 'blog');
            $uploads->deletePublic($oldPath);
        }

        $post->update($data);
        $this->syncTags($post, $request->string('tags')->value());

        return redirect()->route('admin.blog')->with('success', "\"{$post->title}\" updated.");
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return back()->with('success', "\"{$post->title}\" deleted.");
    }

    /** @return array<string, mixed> */
    private function preparePostData(StorePostRequest|UpdatePostRequest $request, HtmlSanitizerService $sanitizer, ?Post $existing): array
    {
        $data = $request->safe()->except(['tags', 'cover_image']);
        $data['body'] = $sanitizer->clean($data['body'], 'blog');

        if (isset($data['seo_description'])) {
            $data['seo_description'] = $sanitizer->clean($data['seo_description']);
        }

        // `scheduled` posts stay unpublished until `posts:publish-scheduled` promotes them; a direct
        // `published` save goes live immediately — keeping the existing `published_at` if the post was
        // already published (an edit shouldn't bump its publish date), defaulting to now otherwise.
        if ($data['status'] === 'published') {
            $data['published_at'] = $existing?->published_at ?? now();
        } else {
            $data['published_at'] = null;
        }

        if ($data['status'] !== 'scheduled') {
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    private function syncTags(Post $post, ?string $tagsInput): void
    {
        $names = Collection::make(explode(',', (string) $tagsInput))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique();

        $tagIds = $names->map(fn (string $name) => Tag::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        )->id);

        $post->tags()->sync($tagIds);
    }
}

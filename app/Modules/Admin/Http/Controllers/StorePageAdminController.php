<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Content\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StorePageAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/StorePageIndex', [
            'pages' => StorePage::query()
                ->with('updatedBy:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (StorePage $page): array => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'url' => url('/'.$page->slug),
                    'is_published' => $page->is_published,
                    'updated_at' => $page->updated_at?->format('d M Y, h:i A'),
                    'updated_by' => $page->updatedBy?->name,
                ])
                ->values(),
        ]);
    }

    public function edit(StorePage $page): Response
    {
        return Inertia::render('Admin/Content/StorePageForm', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'meta_description' => $page->meta_description,
                'body' => $page->body,
                'is_published' => $page->is_published,
                'url' => url('/'.$page->slug),
            ],
        ]);
    }

    public function update(Request $request, StorePage $page, LogAuditTrailAction $audit): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:50000'],
            'is_published' => ['required', 'boolean'],
        ]);

        $old = $page->only(['title', 'meta_description', 'is_published']);

        $page->update($validated + ['updated_by_user_id' => $request->user()?->id]);

        $audit(
            event: 'content.page.updated',
            description: "Store page [{$page->slug}] updated",
            auditable: $page,
            oldValues: $old,
            newValues: $page->only(['title', 'meta_description', 'is_published']),
        );

        return redirect()->route('admin.pages.index')->with('success', "Page \"{$page->title}\" saved.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Menu\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Products → Import (R2, 2026-11-27): a pharmacy's or shop's product list
 * from CSV or Excel (ProductImportService). Pharmacies and shops only (the
 * `retail` route middleware); whoever edits the menu.
 */
class ProductImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.products.import', ['maxRows' => ProductImportService::MAX_ROWS]);
    }

    public function store(Request $request, ProductImportService $import): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ], [
            'file.required' => __('Choose the file with your products first.'),
            'file.mimes' => __('The file must be a CSV or an Excel (.xlsx) file. In Excel or Google Sheets, save or download it as one of those.'),
            'file.max' => __('The file is bigger than 10 MB. Split it into smaller files and import them one after another.'),
        ]);

        $file = $request->file('file');

        try {
            $result = $import->import($file->getRealPath(), $file->getClientOriginalExtension() ?: $file->extension());
        } catch (RuntimeException|ValidationException $e) {
            return back()->withErrors(['file' => $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()]);
        }

        if (! $result['ok']) {
            return back()->with('import_errors', $result['errors'])->withErrors(['file' => trans_choice('{1} 1 row needs fixing before anything is imported:|[2,*] :count rows need fixing before anything is imported:', count($result['errors']))]);
        }

        return redirect()->route('dashboard.menu-items.index')->with('status', __(':created new products, :updated updated, :packs pack sizes.', $result));
    }

    public function template(ProductImportService $import): StreamedResponse
    {
        Gate::authorize('menu.edit_content');

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            foreach ($import->templateRows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv']);
    }
}

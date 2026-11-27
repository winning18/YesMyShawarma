<?php

namespace App\Console\Commands;

use App\Services\Menu\ProductImportService;
use App\Services\Settings\BusinessType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('expresspos:import-products {file : Path to a CSV or Excel (.xlsx) product list on this server}')]
#[Description('Import a pharmacy\'s or shop\'s product list (same rules as Products → Import)')]
class ImportProductsCommand extends Command
{
    public function handle(ProductImportService $import, BusinessType $type): int
    {
        if ($type->isRestaurant()) {
            $this->error('This install is a restaurant; the product import is for pharmacies and shops.');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Can't read {$file}.");

            return self::FAILURE;
        }

        try {
            $result = $import->import($file, pathinfo($file, PATHINFO_EXTENSION));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result['ok']) {
            $this->error('Nothing was imported. Fix these rows, then run it again:');
            foreach ($result['errors'] as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $this->info("{$result['created']} new products, {$result['updated']} updated, {$result['packs']} pack sizes.");

        return self::SUCCESS;
    }
}

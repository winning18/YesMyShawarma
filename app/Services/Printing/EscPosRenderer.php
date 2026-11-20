<?php

namespace App\Services\Printing;

use App\Models\Printer;

/**
 * Receipts, kitchen tickets and the cash drawer as ESC/POS (step 8b) — the
 * command language nearly every receipt printer understands, whatever the
 * brand (Epson, Xprinter, Rongta, Bixolon, Star in ESC/POS mode …). Built
 * from the same ReceiptService data as the browser print pages, so both
 * print the same thing. The print agent sends these bytes as they are.
 */
class EscPosRenderer
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    private int $columns = 48;

    private string $out = '';

    /**
     * @param  array<string, mixed>  $data  ReceiptService::receipt()
     */
    public function receipt(array $data, Printer $printer, ?string $logoFile = null, bool $openDrawer = false): string
    {
        $this->start($printer);
        $money = fn (int $pesewas) => number_format($pesewas / 100, 2);

        $this->align(1);
        if ($logoFile) {
            $this->logo($logoFile, $printer->paper_width);
        }
        $this->bold(true)->size(2, 2)->text($data['business'])->size(1, 1)->bold(false);
        $this->text($data['branch']);
        foreach (array_filter([$data['address'], $data['phone'] ? 'Tel '.$data['phone'] : null]) as $line) {
            $this->text($line);
        }
        $this->align(0)->rule();

        $this->bold(true)->row($data['reference'], $data['placed_at']?->format('d/m/Y H:i') ?? '')->bold(false);
        $servedBy = collect([$data['served_by'] ? __('Served by :name', ['name' => $data['served_by']]) : null, $data['counter']])->filter()->join(' - ');
        if ($servedBy !== '') {
            $this->text($servedBy);
        }
        $this->text(($data['fulfilment'] === 'delivery' ? __('Delivery') : __('Pickup')).($data['customer'] ? ' - '.$data['customer'] : ''));
        $this->rule();

        foreach ($data['lines'] as $line) {
            $this->row($line['quantity'].'x '.$line['name'].($line['free'] ? ' ('.__('free').')' : ''), $money($line['total']));
            foreach ($line['options'] as $option) {
                $this->text('  + '.$option);
            }
        }
        $this->rule();

        if ($data['discount'] || $data['delivery_fee']) {
            $this->row(__('Subtotal'), $money($data['subtotal']));
            if ($data['discount']) {
                $this->row(__('Discount'), '-'.$money($data['discount']));
            }
            if ($data['delivery_fee']) {
                $this->row(__('Delivery fee'), $money($data['delivery_fee']));
            }
        }
        $this->bold(true)->size(1, 2)->row(__('Total'), 'GHS '.$money($data['total']))->size(1, 1)->bold(false);
        $this->text(__('Paid by :method', ['method' => $data['payment']]));

        if ($data['message']) {
            $this->rule()->align(1)->text($data['message'])->align(0);
        }

        $this->cut();

        if ($openDrawer) {
            $this->drawer();
        }

        return $this->out;
    }

    /**
     * @param  array<string, mixed>  $data  ReceiptService::kitchenTicket()
     */
    public function kitchenTicket(array $data, Printer $printer): string
    {
        $this->start($printer);

        $this->align(1)->bold(true)->size(3, 3)->text(substr((string) strrchr('-'.$data['reference'], '-'), 1))->size(1, 1);
        $this->bold(false)->text($data['reference']);
        $this->bold(true)->text(($data['fulfilment'] === 'delivery' ? __('DELIVERY') : __('PICKUP')).($data['counter'] ? ' - '.$data['counter'] : ''))->bold(false);
        $this->text(($data['placed_at']?->format('H:i') ?? '').($data['scheduled_for'] ? ' - '.__('for :time', ['time' => $data['scheduled_for']->format('H:i')]) : ''));
        $this->align(0)->rule();

        foreach ($data['lines'] as $line) {
            $this->bold(true)->size(1, 2)->text($line['quantity'].'x '.$line['name'])->size(1, 1)->bold(false);
            foreach ($line['options'] as $option) {
                $this->bold(true)->text('  + '.$option)->bold(false);
            }
            if ($line['notes']) {
                $this->text('  '.__('Note:').' '.$line['notes']);
            }
        }

        if ($data['instructions']) {
            $this->rule()->bold(true)->text(__('Instructions:').' '.$data['instructions'])->bold(false);
        }

        $this->cut();

        return $this->out;
    }

    /** Only opens the drawer cabled to the printer. */
    public function drawerOnly(): string
    {
        $this->out = self::ESC.'@';
        $this->drawer();

        return $this->out;
    }

    private function start(Printer $printer): void
    {
        $this->columns = $printer->paper_width === 58 ? 32 : 48;
        $this->width = 1;
        $this->out = self::ESC.'@';
    }

    private function align(int $how): self
    {
        $this->out .= self::ESC.'a'.chr($how);

        return $this;
    }

    private function bold(bool $on): self
    {
        $this->out .= self::ESC.'E'.chr($on ? 1 : 0);

        return $this;
    }

    private int $width = 1;

    private function size(int $width, int $height): self
    {
        $this->width = $width;
        $this->out .= self::GS.'!'.chr((($width - 1) << 4) | ($height - 1));

        return $this;
    }

    private function text(string $text): self
    {
        $columns = max(8, intdiv($this->columns, $this->width));

        foreach (explode("\n", wordwrap($this->plain($text), $columns, "\n", true)) as $line) {
            $this->out .= $line."\n";
        }

        return $this;
    }

    /** Left text and a right-aligned amount on one line (the left wraps if it must). */
    private function row(string $left, string $right): self
    {
        $columns = max(8, intdiv($this->columns, $this->width));
        $left = $this->plain($left);
        $right = $this->plain($right);
        $room = $columns - strlen($right) - 1;

        $lines = explode("\n", wordwrap($left, max(4, $room), "\n", true));
        $last = array_pop($lines);
        foreach ($lines as $line) {
            $this->out .= $line."\n";
        }
        $this->out .= str_pad($last, $columns - strlen($right)).$right."\n";

        return $this;
    }

    private function rule(): self
    {
        $this->out .= str_repeat('-', $this->columns)."\n";

        return $this;
    }

    private function cut(): void
    {
        // Feed past the cutter, then a partial cut.
        $this->out .= self::GS.'V'.chr(66).chr(3);
    }

    private function drawer(): void
    {
        // Pulse pin 2 (the usual drawer port), 50 ms on / 500 ms off.
        $this->out .= self::ESC.'p'.chr(0).chr(25).chr(250);
    }

    /**
     * The logo as a black-and-white raster (GS v 0), at most 70% of the
     * paper's width.
     */
    private function logo(string $file, int $paperWidth): void
    {
        $source = @imagecreatefromstring((string) @file_get_contents($file));
        if (! $source) {
            return;
        }

        $maxWidth = $paperWidth === 58 ? 280 : 400;
        $width = min($maxWidth, imagesx($source));
        $height = (int) round(imagesy($source) * $width / imagesx($source));
        if ($height > 160) {
            $width = (int) round($width * 160 / $height);
            $height = 160;
        }
        $width = max(8, $width - ($width % 8));

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $bytesPerRow = intdiv($width, 8);
        $bits = '';
        for ($y = 0; $y < $height; $y++) {
            for ($xByte = 0; $xByte < $bytesPerRow; $xByte++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $rgb = imagecolorat($canvas, $xByte * 8 + $bit, $y);
                    $luminance = 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
                    if ($luminance < 128) {
                        $byte |= 0x80 >> $bit;
                    }
                }
                $bits .= chr($byte);
            }
        }

        $this->out .= self::GS.'v0'.chr(0).chr($bytesPerRow & 0xFF).chr($bytesPerRow >> 8).chr($height & 0xFF).chr($height >> 8).$bits."\n";
    }

    /** Receipt printers' built-in fonts are ASCII: GH₵ becomes GHS, accents drop. */
    private function plain(string $text): string
    {
        $text = strtr($text, ['GH₵' => 'GHS ', '₵' => 'GHS ', '×' => 'x', '−' => '-', '–' => '-', '—' => '-', '·' => '-', '’' => "'", '‘' => "'", '“' => '"', '”' => '"']);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $ascii === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $ascii;
    }
}

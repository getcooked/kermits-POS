<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderReceiptPdf
{
    public function render(Order $order): string
    {
        $order->loadMissing(['items.product', 'reservation', 'customer', 'user']);

        $height = max(842, 520 + ($order->items->count() * 28));
        $commands = [];
        $reservation = $order->reservation;
        $customer = $order->customer ?? $order->user;

        $commands[] = "0.09 0.10 0.09 rg 0 ".($height - 100)." 595 100 re f";
        $commands[] = "0.68 0.73 0.08 rg 0 ".($height - 104)." 595 4 re f";
        $this->text($commands, 44, $height - 48, 22, "KERMIT'S", true, '1 1 1');
        $this->text($commands, 44, $height - 74, 11, 'ORDER RECEIPT', false, '0.86 0.88 0.82');
        $this->rightText($commands, 551, $height - 51, 12, '#'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), true, '1 1 1');

        $y = $height - 142;
        $this->labelValue($commands, 44, $y, 'DATE', $order->created_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'));
        $this->labelValue($commands, 320, $y, 'CUSTOMER', $customer?->name ?? 'Customer');
        $y -= 48;
        $this->labelValue($commands, 44, $y, 'PAYMENT', $this->paymentLabel($order));
        $this->labelValue($commands, 320, $y, 'STATUS', $this->statusLabel($order));

        if ($reservation) {
            $y -= 48;
            $this->labelValue($commands, 44, $y, 'RESERVATION', $reservation->reference);
            $this->labelValue($commands, 320, $y, 'TABLE', $reservation->table_size.' '.($reservation->table_size === 1 ? 'seat' : 'seats'));
            $y -= 48;
            $this->labelValue($commands, 44, $y, 'SCHEDULE', $reservation->reservation_at->format('M d, Y').' - '.$reservation->time_range);
            if ($order->payment_reference) {
                $this->labelValue($commands, 320, $y, 'REFERENCE', $order->payment_reference);
            }
        }

        $itemsY = $y - 66;
        $commands[] = '0.93 0.94 0.90 rg 36 '.($itemsY - 12).' 523 34 re f';
        $this->text($commands, 48, $itemsY, 10, 'ITEM', true, '0.25 0.27 0.23');
        $this->rightText($commands, 547, $itemsY, 10, 'AMOUNT', true, '0.25 0.27 0.23');

        $rowY = $itemsY - 40;
        foreach ($order->items as $item) {
            $name = $item->product?->name ?? 'Product';
            $this->text($commands, 48, $rowY, 11, $item->quantity.' x '.Str::limit($name, 45), true);
            $this->text($commands, 48, $rowY - 14, 8, 'PHP '.number_format((float) $item->unit_price, 2).' each', false, '0.40 0.42 0.38');
            $this->rightText($commands, 547, $rowY, 11, 'PHP '.number_format((float) $item->subtotal, 2), true);
            $commands[] = '0.88 0.89 0.85 RG 0.5 w 44 '.($rowY - 22).' m 551 '.($rowY - 22).' l S';
            $rowY -= 36;
        }

        $totalsY = $rowY - 4;
        $this->text($commands, 340, $totalsY, 10, 'Food order', false, '0.38 0.40 0.36');
        $this->rightText($commands, 547, $totalsY, 10, 'PHP '.number_format((float) $order->total, 2), true);
        if ($reservation) {
            $totalsY -= 24;
            $this->text($commands, 340, $totalsY, 10, 'Table reservation', false, '0.38 0.40 0.36');
            $this->rightText($commands, 547, $totalsY, 10, 'PHP '.number_format((float) $reservation->total_amount, 2), true);
        }
        $totalsY -= 30;
        $commands[] = '0.09 0.10 0.09 RG 1 w 340 '.($totalsY + 17).' m 551 '.($totalsY + 17).' l S';
        $this->text($commands, 340, $totalsY, 13, $order->payment_status === 'rejected' ? 'Order total' : 'Total due', true);
        $this->rightText($commands, 547, $totalsY, 13, 'PHP '.number_format($order->totalDue(), 2), true);

        $this->text($commands, 44, 42, 9, 'Thank you for choosing Kermit\'s.', true, '0.35 0.38 0.32');
        $this->rightText($commands, 551, 42, 8, 'Generated '.now()->format('M d, Y h:i A'), false, '0.45 0.47 0.43');

        return $this->document(implode("\n", $commands), $height);
    }

    private function labelValue(array &$commands, float $x, float $y, string $label, string $value): void
    {
        $this->text($commands, $x, $y, 8, $label, true, '0.43 0.46 0.40');
        $this->text($commands, $x, $y - 17, 11, Str::limit($value, 38), true);
    }

    private function paymentLabel(Order $order): string
    {
        return match ($order->payment_method) {
            'cash' => 'Walk In Pay',
            'paymongo' => 'PayMongo online',
            default => 'GCash',
        };
    }

    private function statusLabel(Order $order): string
    {
        return match ($order->payment_status) {
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            default => 'Pending',
        };
    }

    private function text(array &$commands, float $x, float $y, float $size, string $value, bool $bold = false, string $color = '0.09 0.10 0.09'): void
    {
        $font = $bold ? 'F2' : 'F1';
        $commands[] = sprintf('%s rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET', $color, $font, $size, $x, $y, $this->escape($value));
    }

    private function rightText(array &$commands, float $right, float $y, float $size, string $value, bool $bold = false, string $color = '0.09 0.10 0.09'): void
    {
        $ascii = $this->ascii($value);
        $width = strlen($ascii) * $size * 0.52;
        $this->text($commands, max(44, $right - $width), $y, $size, $ascii, $bold, $color);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($value));
    }

    private function ascii(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', '', $converted === false ? $value : $converted) ?? '';
    }

    private function document(string $content, int $height): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 '.$height.'] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}

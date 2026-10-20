<?php
declare(strict_types=1);

/*
 * Reglas monetarias del piloto (tarjeta #14). Todo importe se calcula en centavos
 * enteros; nunca con float. Las columnas DECIMAL(12,2) solo guardan el resultado.
 * Redondeo: mitad hacia arriba, una vez por línea; los totales son la suma de líneas.
 * Impuesto: porcentaje en puntos básicos (21 % = 2100), incluido o agregado al precio.
 */

const MONEY_MAX_CENTS = 99999999999; // 999.999.999,99

/** "1234.5" o "1234,50" -> 123450. Devuelve null si no es un importe válido (máx. 2 decimales, sin signo). */
function moneyToCents(string $value): ?int
{
    $value = trim($value);
    if (!preg_match('/^(\d{1,9})(?:[.,](\d{1,2}))?$/', $value, $m)) return null;
    return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '0', 2, '0');
}

/** 123450 -> "1234.50" (formato de base de datos). */
function centsToDecimal(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
}

/** DECIMAL de MySQL ("1234.50") -> centavos. */
function decimalToCents(string|int|float|null $value): int
{
    $text = (string)($value ?? '0');
    if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2})\d*)?$/', $text, $m)) throw new InvalidArgumentException('Importe inválido.');
    $cents = (int)$m[2] * 100 + (int)str_pad($m[3] ?? '0', 2, '0');
    return $m[1] === '-' ? -$cents : $cents;
}

/** 123450 -> "$ 1.234,50" para mostrar en pantalla. */
function moneyFormat(int $cents): string
{
    return ($cents < 0 ? '-' : '') . '$ ' . number_format(abs($cents) / 100, 2, ',', '.');
}

/** Cociente entero redondeado a la mitad hacia arriba; solo valores no negativos. */
function roundedDiv(int $numerator, int $denominator): int
{
    if ($numerator < 0 || $denominator < 1) throw new InvalidArgumentException('División fuera de rango.');
    return intdiv(2 * $numerator + $denominator, 2 * $denominator);
}

/** "21", "10.5" -> 2100, 1050. Rango 0 a 100 %. */
function rateToBasisPoints(string $rate): ?int
{
    if (!preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', trim($rate), $m)) return null;
    $bp = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '0', 2, '0');
    return $bp > 10000 ? null : $bp;
}

/**
 * Calcula una línea de venta.
 * $discount: ['type' => 'none'] | ['type' => 'amount', 'value' => centavos] | ['type' => 'percent', 'value' => puntos básicos]
 * Devuelve gross (precio x cantidad), discount, net (sin impuesto), tax y total (lo que paga el cliente).
 */
function calcSaleLine(int $unitCents, int $quantity, array $discount, int $rateBp, bool $taxIncluded): array
{
    if ($unitCents < 0 || $unitCents > MONEY_MAX_CENTS) throw new DomainException('El precio es inválido.', 422);
    if ($quantity < 1 || $quantity > 999) throw new DomainException('La cantidad debe ser de 1 a 999.', 422);
    if ($rateBp < 0 || $rateBp > 10000) throw new DomainException('El impuesto debe estar entre 0 y 100 %.', 422);
    $gross = $unitCents * $quantity;
    if ($gross > MONEY_MAX_CENTS) throw new DomainException('El importe de la línea es demasiado grande.', 422);
    $type = $discount['type'] ?? 'none';
    $value = (int)($discount['value'] ?? 0);
    $off = match ($type) {
        'none' => 0,
        'amount' => $value,
        'percent' => $value >= 0 && $value <= 10000 ? roundedDiv($gross * $value, 10000) : -1,
        default => -1,
    };
    if ($off < 0 || $off > $gross) throw new DomainException('El descuento no puede superar el importe de la línea.', 422);
    $base = $gross - $off;
    if ($taxIncluded) {
        $tax = roundedDiv($base * $rateBp, 10000 + $rateBp);
        return ['gross' => $gross, 'discount' => $off, 'net' => $base - $tax, 'tax' => $tax, 'total' => $base];
    }
    $tax = roundedDiv($base * $rateBp, 10000);
    return ['gross' => $gross, 'discount' => $off, 'net' => $base, 'tax' => $tax, 'total' => $base + $tax];
}

/** Suma las líneas ya calculadas. subtotal = importe bruto antes de descuentos. */
function sumSaleLines(array $lines): array
{
    $sum = ['subtotal' => 0, 'discount' => 0, 'net' => 0, 'tax' => 0, 'total' => 0];
    foreach ($lines as $line) {
        $sum['subtotal'] += $line['gross']; $sum['discount'] += $line['discount'];
        $sum['net'] += $line['net']; $sum['tax'] += $line['tax']; $sum['total'] += $line['total'];
    }
    if ($sum['total'] > MONEY_MAX_CENTS) throw new DomainException('El total de la venta es demasiado grande.', 422);
    return $sum;
}

/** Estado de cobro de una venta según total y cobrado: unpaid, partial o paid. */
function collectionStatus(int $totalCents, int $paidCents): string
{
    if ($paidCents <= 0) return $totalCents === 0 ? 'paid' : 'unpaid';
    return $paidCents >= $totalCents ? 'paid' : 'partial';
}

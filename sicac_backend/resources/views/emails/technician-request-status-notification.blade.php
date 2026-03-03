<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aviso de estado</title>
</head>
<body style="margin:0; padding:0; background-color:#111111; font-family:Arial, sans-serif;">
@php
    $logoPath = public_path('sicac.png');
    $logoSrc = rtrim((string) config('app.url'), '/') . '/sicac.png';
    if (isset($message) && is_file($logoPath)) {
        $logoSrc = $message->embed($logoPath);
    }

    $formatDate = function (?string $value): string {
        if (empty($value)) {
            return 'Sin fecha';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $formatTime = function (?string $value): string {
        if (empty($value)) {
            return 'Sin horario';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return 'Sin horario';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized . ' hs';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
            return substr($normalized, 0, 5) . ' hs';
        }

        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', $normalized)->format('H:i') . ' hs';
        } catch (\Throwable) {
            try {
                return \Carbon\Carbon::createFromFormat('H:i', $normalized)->format('H:i') . ' hs';
            } catch (\Throwable) {
                return (string) $value;
            }
        }
    };

    $isCompleted = $technicianRequest->status === 'completed';
    $isCancelled = $technicianRequest->status === 'cancelled';

    $rawShift = trim((string) ($technicianRequest->time_shift ?? ''));
    $normalizedShift = strtolower($rawShift);
    $shiftLabel = match ($normalizedShift) {
        'morning', 'manana', 'mañana' => 'Mañana',
        'afternoon', 'afternoong', 'tarde' => 'Tarde',
        default => $rawShift !== '' ? $rawShift : 'Sin dato',
    };
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#111111; margin:0; padding:24px 8px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px; max-width:640px; border-collapse:collapse; background-color:#ffffff;">
                <tr>
                    <td style="background-color:#111111; padding:24px 24px 18px; text-align:center;">
                        <img src="{{ $logoSrc }}" alt="SICAC - CEA Insumos" style="display:block; margin:0 auto 14px; width:130px; max-width:130px; height:auto;">
                        <div style="font-size:24px; line-height:30px; font-weight:700; color:#f7c600;">
                            Aviso de {{ ucfirst($typeLabel) }}
                        </div>
                        <div style="margin-top:4px; font-size:13px; line-height:18px; color:#f3f4f6;">
                            CEA Insumos | Sistemas de seguridad
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="height:8px; line-height:8px; font-size:0; background-color:#f7c600;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 12px; font-size:15px; line-height:22px; color:#111111;">
                            Hola {{ $technicianRequest->requestingUser?->name ?? 'cliente' }},
                        </p>
                        <p style="margin:0 0 18px; font-size:15px; line-height:22px; color:#111111;">
                            Te informamos una actualizacion sobre tu {{ $typeLabel }} #{{ $technicianRequest->id }}.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid #e5e7eb; margin-bottom:18px;">
                            <tr>
                                <td style="width:42%; padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Estado actual</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $statusLabel }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Asunto</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $technicianRequest->subject }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Fecha visita</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $formatDate($scheduledVisitDate) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Hora estimada de visita</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $formatTime($scheduledVisitTime) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Turno solicitado</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $shiftLabel }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Rango pedido</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">
                                    {{ $formatDate($technicianRequest->wanted_date_start) }} al {{ $formatDate($technicianRequest->wanted_date_end) }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Actualizado por</td>
                                <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $updatedByLabel }}</td>
                            </tr>
                            @if($isCompleted)
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f7c600; font-size:14px; color:#111111; font-weight:700;">Monto abonado</td>
                                    <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:15px; color:#111111; font-weight:700;">
                                        @if($technicianRequest->charged_amount !== null)
                                            ARS ${{ number_format((float) $technicianRequest->charged_amount, 2, ',', '.') }}
                                        @else
                                            No informado
                                        @endif
                                    </td>
                                </tr>
                                @if(!empty($technicianRequest->resolution_summary))
                                    <tr>
                                        <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Detalle de resolucion</td>
                                        <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $technicianRequest->resolution_summary }}</td>
                                    </tr>
                                @endif
                            @endif
                            @if($isCancelled && !empty($technicianRequest->cancellation_reason))
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #e5e7eb; background-color:#f9fafb; font-size:13px; color:#111111; font-weight:700;">Motivo de cancelacion</td>
                                    <td style="padding:10px 12px; border:1px solid #e5e7eb; font-size:13px; color:#111111;">{{ $technicianRequest->cancellation_reason }}</td>
                                </tr>
                            @endif
                        </table>

                        <div style="margin:0 0 16px; padding:12px 14px; background-color:#111111; color:#f7c600; font-size:12px; line-height:18px;">
                            <strong>CEA Insumos S.R.L.</strong><br>
                            CUIT: 20-25459992-2<br>
                            Domicilio: Buenos Aires 432, Firmat (2630), Santa Fe, Argentina.<br>
                            Horario: L-V 08-18 hs<br>
                            Telefono: +54 (3465) 665656<br>
                            Contacto general: contacto@ceainsumos.com<br>
                            Soporte de reclamos: consultas@ceainsumos.com
                        </div>

                        <p style="margin:0; font-size:13px; line-height:20px; color:#4b5563;">
                            Gracias por confiar en CEA Insumos.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 24px; background-color:#f7c600; text-align:center; font-size:11px; line-height:16px; color:#111111;">
                        Este correo es un aviso automatico de SICAC - CEA Insumos.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

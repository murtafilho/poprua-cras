<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Roteiro de Zeladoria - {{ $dataInicio }} {{ $dataFim ? 'a ' . $dataFim : '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 20px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
        .print-btn { display: block; margin: 0 auto 16px; padding: 8px 24px; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn" x-on:click="window.print()">Imprimir / Salvar PDF</button>

    <h1>Roteiro de Acoes de Zeladoria</h1>
    <p class="subtitle">
        Periodo: {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }}
        {{ $dataFim ? 'a ' . \Carbon\Carbon::parse($dataFim)->format('d/m/Y') : '' }}
        | Total: {{ $vistorias->count() }} registros
    </p>

    <table>
        <thead>
            <tr>
                <th>Data Prevista</th>
                <th>Periodo</th>
                <th>Endereco</th>
                <th>Bairro</th>
                <th>Regional</th>
                <th>Supervisor</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vistorias as $v)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($v->data_prevista_zeladoria)->format('d/m/Y') }}</td>
                    <td>{{ $v->periodo_zeladoria === 'manha' ? 'Manha' : ($v->periodo_zeladoria === 'tarde' ? 'Tarde' : '-') }}</td>
                    <td>{{ $v->tipo }} {{ $v->logradouro }}, {{ $v->numero }}{{ $v->complemento ? ' - ' . $v->complemento : '' }}</td>
                    <td>{{ $v->bairro }}</td>
                    <td>{{ $v->regional }}</td>
                    <td>{{ $v->usuario }}</td>
                    <td>{{ $v->resultado_acao ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 16px; color: #999;">Nenhuma zeladoria encontrada para o periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size: 10px; color: #999; text-align: center;">
        Gerado em {{ now()->format('d/m/Y H:i') }} | {{ config('app.brand', 'SIZEM BH') }}
    </p>
</body>
</html>

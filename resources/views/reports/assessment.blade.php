<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de avaliação física - MF</title>
    <style>
        @page { margin: 27px 32px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #183239; font-size: 10px; line-height: 1.45; }
        .head { border-bottom: 3px solid #40856c; padding: 0 0 14px; margin-bottom: 17px; }
        .brand { font-size: 11px; color: #40856c; font-weight: bold; letter-spacing: 1px; }
        .h1 { margin: 6px 0; font-size: 21px; font-weight: bold; color: #123c47; }
        .muted { font-size: 9px; color: #6b7c7f; }
        h2 { margin: 18px 0 8px; font-size: 12px; color: #123c47; border-bottom: 1px solid #d9e8e1; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        td, th { padding: 7px 9px; border-bottom: 1px solid #e6eceb; text-align: left; vertical-align: top; }
        th { color: #3c6961; background: #edf5f1; font-weight: bold; }
        .two { width: 50%; }
        .summary td { padding: 10px; border: 1px solid #d7e5dd; }
        .sum-label { color: #627477; font-size: 9px; }
        .sum-value { font-size: 16px; font-weight: bold; color: #123c47; }
        .section { page-break-inside: avoid; }
        .small { font-size: 9px; }
        .bar-back { width: 100%; height: 7px; background: #e3ece9; border-radius: 2px; }
        .bar { background: #40856c; height: 7px; }
        .notice { margin-top: 18px; padding: 12px; background: #f1f6f4; border-left: 3px solid #40856c; font-size: 9px; }
        .footer { margin-top: 20px; font-size: 8px; color: #657779; border-top: 1px solid #dfe9e5; padding-top: 10px; }
    </style>
</head>
<body>
<div class="head">
    <div class="brand">MF SAÚDE E MOVIMENTO</div>
    <div class="h1">Relatório de avaliação física</div>
    <div class="muted">Relatório de acompanhamento · Versão 1.0 · Gerado em {{ $generatedAt }}</div>
</div>
<table>
    <tr><th class="two">Aluno(a)</th><th>Data da avaliação</th></tr>
    <tr><td>{{ $student->name }}</td><td>{{ $assessment->evaluation_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Avaliador(a)</th><th>Idade na avaliação</th></tr>
    <tr><td>{{ $assessment->evaluator?->name ?? 'Não informado' }}</td><td>{{ $age ?? 'Não informada' }}</td></tr>
</table>

<div class="section">
<h2>Resumo das medidas registradas</h2>
<table class="summary"><tr>
@foreach($summary as $item)
    <td style="width:25%;">
        <div class="sum-label">{{ $item['label'] }}</div>
        <div class="sum-value">{{ $item['value'] }}</div>
        <div class="muted">{{ $item['unit'] }}</div>
    </td>
@endforeach
</tr></table>
</div>

<div class="section">
<h2>Perímetros corporais</h2>
<table>
    <tr><th style="width:70%;">Medida</th><th>Valor (cm)</th></tr>
    @foreach($circumferences as $entry)
    <tr><td>{{ $entry['label'] }}</td><td>{{ $entry['value'] }}</td></tr>
    @endforeach
</table>
</div>

<div class="section">
<h2>Evolução recente registrada</h2>
<div class="muted">Até seis avaliações concluídas, respeitando a data desta avaliação e o perfil de acesso. Dados ausentes aparecem como traço.</div>
<table style="margin-top:9px;">
    <tr><th style="width:17%">Data</th><th style="width:16%">Peso (kg)</th><th style="width:16%">IMC</th><th style="width:16%">Cintura</th><th>Representação do peso</th></tr>
    @forelse($historyRows as $entry)
        <tr>
            <td>{{ $entry['date'] }}</td><td>{{ $entry['weight'] }}</td><td>{{ $entry['bmi'] }}</td><td>{{ $entry['waist'] }}</td>
            <td>
            @if($entry['bar_percent'] !== null)
                <div class="bar-back"><div class="bar" style="width: {{ $entry['bar_percent'] }}%;"></div></div>
            @else
                —
            @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5">Nenhum registro concluído disponível.</td></tr>
    @endforelse
</table>
<div class="muted" style="margin-top:5px;">Barras proporcionais ao maior peso apresentado, sem classificação clínica.</div>
</div>

<div class="section">
<h2>Seções da avaliação</h2>
<table>
    <tr><th>Seção</th><th style="width:30%">Status</th></tr>
    @foreach($sections as $section)
        <tr><td>{{ $section['label'] }}</td><td>{{ $section['status'] }}</td></tr>
    @endforeach
</table>
</div>

<div class="notice">
    <strong>Aviso:</strong> este documento apresenta valores registrados e o status das seções. Não representa diagnóstico,
    prescrição ou classificação clínica. Equações e tabelas não homologadas não foram utilizadas. Fotografias e
    anotações clínicas detalhadas não integram esta versão do relatório.
</div>
<div class="footer">DADOS CONFIDENCIAIS · Relatório gerado exclusivamente para usuário autenticado e autorizado.
    A redistribuição do arquivo é de responsabilidade de quem o compartilha. Não há assinatura digital nesta versão.</div>
</body>
</html>

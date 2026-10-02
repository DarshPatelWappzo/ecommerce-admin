<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page {
            margin: 20mm 12mm;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #222;
        }

        h1 {
            font-size: 21px;
        }

        h2 {
            font-size: 12px;
            margin-bottom: 4px;
        }

        p,
        td,
        th {
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }

        p {
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            table-layout: fixed;
        }

        th,
        td {
            border-bottom: 1px solid #ddd;
            padding: 6px 4px;
            vertical-align: top;
            text-align: left;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .invoice-items th:first-child {
            width: 25%;
        }

        .invoice-totals {
            width: 65%;
            margin-left: auto;
        }

        .preserve-lines {
            white-space: pre-wrap;
        }

        @media print {
            .print-controls {
                display: none;
            }
        }
    </style>
    <style>
        {!! file_get_contents(public_path('css/invoice-document.css')) !!}
    </style>
</head>

<body>
    @if (!$pdf)
        <div class="print-controls"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    @endif
    @include('tenant.invoices._document')
</body>

</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; padding: 20px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #004d40; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; color: #004d40; }
        .details { margin-bottom: 30px; font-size: 16px; line-height: 1.6; }
        .summary-box { background-color: #f1f8e9; padding: 15px; border-radius: 8px; border: 1px solid #c5e1a5; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #004d40; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">كشف حساب شهري</div>
        <div>تاريخ الإصدار: {{ $date }}</div>
    </div>

    <div class="details">
        <strong>السيد/ة:</strong> {{ $customer->name }} <br>
        <strong>رقم الهاتف:</strong> {{ $customer->primary_phone }} <br>
    </div>

    <div class="summary-box">
        <strong>إجمالي الديون المسجلة:</strong> {{ $totalDebt }} ر.س <br>
        <strong>إجمالي المبالغ المسددة:</strong> {{ $totalPaid }} ر.س <br>
        <hr>
        <strong style="color: #d32f2f; font-size: 18px;">الرصيد المتبقي المستحق: {{ $remaining }} ر.س</strong>
    </div>

    <h3>تفاصيل العمليات الأخيرة</h3>
    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>المبلغ</th>
                <th>المدفوع</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($customer->debts->take(10) as $debt)
            <tr>
                <td>{{ $debt->created_at->format('Y-m-d') }}</td>
                <td>{{ $debt->amount }}</td>
                <td>{{ $debt->paid }}</td>
                <td>{{ $debt->status == 'unpaid' ? 'غير مسدد' : 'مسدد جزئياً' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

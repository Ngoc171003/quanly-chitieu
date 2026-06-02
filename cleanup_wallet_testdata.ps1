$path = 'database\test_data.sql'
$lines = Get-Content $path
$out = New-Object System.Collections.ArrayList
$skippingWallets = $false
$skippingBalanceUpdate = $false
foreach ($line in $lines) {
    $trim = $line.Trim()
    if ($skippingWallets) {
        if ($trim -eq '') { $skippingWallets = $false }
        continue
    }
    if ($skippingBalanceUpdate) {
        if ($trim.EndsWith(';')) { $skippingBalanceUpdate = $false }
        continue
    }
    if ($trim -eq 'TRUNCATE TABLE wallets;') {
        continue
    }
    if ($trim -like 'INSERT INTO wallets*') {
        $skippingWallets = $true
        continue
    }
    if ($trim -eq '-- Cập nhật số dư ví theo giao dịch đã nhập') {
        $skippingBalanceUpdate = $true
        continue
    }
    if ($trim -eq 'INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES') {
        $out.Add('INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES') > $null
        continue
    }
    if ($trim -match '^\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,') {
        $line = $line -replace '^\(\s*(\d+)\s*,\s*(\d+)\s*,\s*\d+\s*,\s*', '($1, $2, '
    }
    $out.Add($line) > $null
}
$out | Set-Content -Path $path -Encoding UTF8
Write-Host 'Updated test_data.sql'

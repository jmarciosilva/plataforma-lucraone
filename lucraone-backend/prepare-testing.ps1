Write-Host "Preparando aplicacao para testes manuais..." -ForegroundColor Yellow
Write-Host ""

# 1. Check containers
Write-Host "1. Verificando containers..." -ForegroundColor Yellow
docker-compose ps | findstr mysql
Write-Host "✓ Verificacao feita" -ForegroundColor Green
Write-Host ""

# 2. Run migrations
Write-Host "2. Executando migracoes..." -ForegroundColor Yellow
docker-compose exec -T app php artisan migrate --force 2>&1 | Select-Object -Last 5
Write-Host "✓ Migracoes executadas" -ForegroundColor Green
Write-Host ""

# 3. Run seeders
Write-Host "3. Populando dados iniciais..." -ForegroundColor Yellow
docker-compose exec -T app php artisan db:seed --force 2>&1
Write-Host "✓ Seeders executados" -ForegroundColor Green
Write-Host ""

# 4. Generate test token
Write-Host "4. Gerando token de teste..." -ForegroundColor Yellow

$phpCode = @'
$tenant = App\Modules\Tenancy\Domain\Models\Tenant::first();
$user = App\Modules\Identity\Domain\Models\User::where('tenant_id', $tenant->id)->first();
$token = $user->createToken('test-token')->plainTextToken;
echo "TOKEN:" . $token . "\n";
echo "TENANT:" . $tenant->id . "\n";
$company = App\Modules\Companies\Domain\Models\Company::first();
echo "COMPANY:" . $company->id . "\n";
'@

$result = docker-compose exec -T app php artisan tinker --execute=$phpCode 2>&1

$TOKEN = ""
$TENANT_ID = ""
$COMPANY_ID = ""

foreach ($line in $result) {
    if ($line -like "TOKEN:*") {
        $TOKEN = $line -replace "TOKEN:", ""
    }
    if ($line -like "TENANT:*") {
        $TENANT_ID = $line -replace "TENANT:", ""
    }
    if ($line -like "COMPANY:*") {
        $COMPANY_ID = $line -replace "COMPANY:", ""
    }
}

Write-Host "✓ Token gerado" -ForegroundColor Green
Write-Host ""

# 5. Display results
Write-Host "============================================" -ForegroundColor Green
Write-Host "APLICACAO PRONTA PARA TESTES" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""

Write-Host "Credenciais para Testes:" -ForegroundColor Yellow
Write-Host ""
Write-Host "URL Base:    http://localhost:8000" -ForegroundColor Green
Write-Host "Token:       $TOKEN" -ForegroundColor Green
Write-Host "Tenant ID:   $TENANT_ID" -ForegroundColor Green
Write-Host "Company ID:  $COMPANY_ID" -ForegroundColor Green
Write-Host ""

Write-Host "Como testar com cURL:" -ForegroundColor Yellow
Write-Host 'curl -X GET http://localhost:8000/api/v1/products -H "Authorization: Bearer TOKEN" -H "X-Tenant-ID: TENANT_ID"' -ForegroundColor Cyan
Write-Host ""

Write-Host "Substitua TOKEN e TENANT_ID pelos valores acima." -ForegroundColor Yellow
Write-Host ""
Write-Host "Veja TESTING_GUIDE.md para mais detalhes" -ForegroundColor Yellow

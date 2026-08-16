#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🚀 Preparando aplicação para testes manuais...${NC}\n"

# 1. Check if containers are running
echo -e "${YELLOW}1️⃣  Verificando containers...${NC}"
if ! docker-compose ps | grep -q "mysql.*Up"; then
    echo -e "${RED}❌ MySQL não está rodando. Execute: docker-compose up -d${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Containers estão rodando${NC}\n"

# 2. Run migrations
echo -e "${YELLOW}2️⃣  Executando migrações...${NC}"
docker-compose exec -T app php artisan migrate --force 2>&1 | tail -20
echo -e "${GREEN}✅ Migrações executadas${NC}\n"

# 3. Run seeders
echo -e "${YELLOW}3️⃣  Populando dados iniciais...${NC}"
docker-compose exec -T app php artisan db:seed --force 2>&1
echo -e "${GREEN}✅ Seeders executados${NC}\n"

# 4. Generate test token
echo -e "${YELLOW}4️⃣  Gerando token de teste...${NC}"
TOKEN=$(docker-compose exec -T app php artisan tinker --execute='
$tenant = \App\Modules\Tenancy\Domain\Models\Tenant::first();
$user = \App\Modules\Identity\Domain\Models\User::where("tenant_id", $tenant->id)->first();
$token = $user->createToken("test-token")->plainTextToken;
echo $token;
' 2>&1 | tail -1)

TENANT_ID=$(docker-compose exec -T app php artisan tinker --execute='
echo \App\Modules\Tenancy\Domain\Models\Tenant::first()->id;
' 2>&1 | tail -1)

COMPANY_ID=$(docker-compose exec -T app php artisan tinker --execute='
echo \App\Modules\Companies\Domain\Models\Company::first()->id;
' 2>&1 | tail -1)

echo -e "${GREEN}✅ Token gerado${NC}\n"

# 5. Test health endpoint
echo -e "${YELLOW}5️⃣  Testando health endpoint...${NC}"
HEALTH=$(curl -s http://localhost:8000/health)
if echo "$HEALTH" | grep -q "ok"; then
    echo -e "${GREEN}✅ Health check: OK${NC}\n"
else
    echo -e "${RED}❌ Health check falhou${NC}\n"
fi

# 6. Display credentials
echo -e "${GREEN}════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ APLICAÇÃO PRONTA PARA TESTES!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════${NC}\n"

echo -e "${YELLOW}📋 Credenciais para Testes:${NC}"
echo ""
echo -e "🔗 ${GREEN}URL Base:${NC}        http://localhost:8000"
echo -e "🔑 ${GREEN}Token:${NC}           $TOKEN"
echo -e "🏢 ${GREEN}Tenant ID:${NC}       $TENANT_ID"
echo -e "🏭 ${GREEN}Company ID:${NC}      $COMPANY_ID"
echo ""
echo -e "${YELLOW}📌 Próximos Passos:${NC}"
echo "1. Copie o token acima"
echo "2. Use em headers: Authorization: Bearer TOKEN"
echo "3. Use X-Tenant-ID: $TENANT_ID"
echo ""
echo -e "${YELLOW}🧪 Testar via cURL:${NC}"
echo ""
echo "curl -X GET http://localhost:8000/api/v1/products \\"
echo "  -H \"Authorization: Bearer $TOKEN\" \\"
echo "  -H \"X-Tenant-ID: $TENANT_ID\""
echo ""
echo -e "${YELLOW}📚 Documentação completa em: TESTING_GUIDE.md${NC}\n"

# Save to .env.test for reference
echo "# Generated Test Credentials" > .env.test.credentials
echo "TEST_TOKEN=$TOKEN" >> .env.test.credentials
echo "TEST_TENANT_ID=$TENANT_ID" >> .env.test.credentials
echo "TEST_COMPANY_ID=$COMPANY_ID" >> .env.test.credentials

echo -e "${GREEN}✅ Credenciais salvas em .env.test.credentials${NC}\n"

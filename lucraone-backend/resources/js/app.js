import Alpine from 'alpinejs';
import precoProduto from './product-price';
import skuProduto from './product-sku';
import telefoneCompany from './company-phone';
import onboardingCliente from './onboarding';

Alpine.data('onboardingCliente', onboardingCliente);
Alpine.data('telefoneCompany', telefoneCompany);

Alpine.data('skuProduto', skuProduto);
Alpine.data('precoProduto', precoProduto);

window.Alpine = Alpine;

Alpine.start();

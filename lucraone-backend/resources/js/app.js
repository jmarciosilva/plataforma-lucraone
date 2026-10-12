import Alpine from 'alpinejs';
import precoProduto from './product-price';
import skuProduto from './product-sku';
import telefoneCompany from './company-phone';
import onboardingCliente from './onboarding';
import recuperacaoTerminal from './terminal-recovery';

Alpine.data('onboardingCliente', onboardingCliente);
Alpine.data('telefoneCompany', telefoneCompany);

Alpine.data('skuProduto', skuProduto);
Alpine.data('precoProduto', precoProduto);
Alpine.data('recuperacaoTerminal', recuperacaoTerminal);

window.Alpine = Alpine;

Alpine.start();

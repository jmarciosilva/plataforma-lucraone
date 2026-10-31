import Alpine from 'alpinejs';
import precoProduto from './product-price';
import skuProduto from './product-sku';
import telefoneCompany from './company-phone';
import onboardingCliente from './onboarding';
import recuperacaoTerminal from './terminal-recovery';
import vendasPdvMonitor from './pdv-sales-monitor';
import vendasPdvSimulador from './pdv-sales-simulator';

Alpine.data('onboardingCliente', onboardingCliente);
Alpine.data('telefoneCompany', telefoneCompany);

Alpine.data('skuProduto', skuProduto);
Alpine.data('precoProduto', precoProduto);
Alpine.data('recuperacaoTerminal', recuperacaoTerminal);
Alpine.data('vendasPdvMonitor', vendasPdvMonitor);
Alpine.data('vendasPdvSimulador', vendasPdvSimulador);

window.Alpine = Alpine;

Alpine.start();

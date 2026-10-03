import Alpine from 'alpinejs';
import onboardingCliente from './onboarding';

Alpine.data('onboardingCliente', onboardingCliente);

window.Alpine = Alpine;

Alpine.start();

/**
 * The customer site's whole bundle: Alpine and nothing else. The
 * dashboard bundle (app.js) also carries Leaflet, Litepicker and the
 * realtime client — about 330KB that every customer page used to download
 * and run before Alpine could start, so on slow mobile data the menu
 * button (and every other Alpine control) did nothing for the first few
 * seconds. Pages that need live updates (order tracking) add echo.js via
 * <x-customer-layout realtime>, loaded ahead of this so window.Echo exists
 * by the time Alpine components initialise.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

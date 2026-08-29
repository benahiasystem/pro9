<?php

namespace App\Providers;

use App\Models\System\PaymentOrder;
use App\Models\Tenant\Document;
use App\Models\Tenant\Purchase;
use App\Observers\DocumentObserver;
use App\Observers\PaymentOrderObserver;
use App\Observers\PurchaseObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\LevelAccess\Helpers\SessionLifetimeHelper;
// ── AGREGADO (RECIENTE) ──────────────────────────────────────
// Importamos las fachadas necesarias para la configuración dinámica de correo
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\System\Configuration;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
	public function boot()
	{
		View::addLocation(app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'));
		$this->configureLocalNgrokUrl();

		// Evitar ejecutar en consola; aplicar sólo en contexto web
		if (!app()->runningInConsole()) {
			SessionLifetimeHelper::setTenantSessionLifetime();

			// ngrok conserva el host público en X-Forwarded-Host aunque reescriba
			// Host para que Hyn resuelva el tenant mediante local.pro9.test.
			$forwardedHost = trim(explode(',', request()->header('X-Forwarded-Host', ''))[0]);
			$forwardedProto = strtolower(trim(explode(',', request()->header('X-Forwarded-Proto', 'https'))[0]));

			if (
				$forwardedHost !== '' &&
				in_array($forwardedProto, ['http', 'https'], true) &&
				preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::\d{1,5})?\z/i', $forwardedHost)
			) {
				URL::forceRootUrl("{$forwardedProto}://{$forwardedHost}");
			}
		}

		if (config('tenant.force_https')) {
			URL::forceScheme('https');
		}
		Document::observe(DocumentObserver::class);
		PaymentOrder::observe(PaymentOrderObserver::class);
		Purchase::observe(PurchaseObserver::class);

		// ── AGREGADO (RECIENTE) ──────────────────────────────────────
        // Se movió este método desde ForgotPasswordController para centralizar
        // la configuración de correo en el arranque de la aplicación.
        // Envuelto en try/catch: en boot() Hyn aun no resuelve el tenant y la
        // conexion MySQL puede fallar con credenciales default. El bootstrap
        // no debe romper la app si la BD no responde aqui.
        try {
            if (Schema::hasTable('configurations')) {
                $this->configurarCorreoDesdeDB();
            }
        } catch (\Throwable $e) {
            // silencioso a proposito
        }
	}

	public function register()
	{
		$this->app->singleton(\App\Services\CentrifugoService::class);
	}

    /**
     * Keep the tenant's internal Host header while generating public ngrok URLs.
     */
    private function configureLocalNgrokUrl(): void
    {
        if (! app()->environment('local') || app()->runningInConsole() || ! request()->headers->has('x-forwarded-host')) {
            return;
        }

        $forwardedHost = trim(explode(',', request()->header('x-forwarded-host'))[0]);
        $forwardedProto = strtolower(trim(explode(',', request()->header('x-forwarded-proto', 'https'))[0]));

        if (! preg_match('/^[a-z0-9-]+\.(ngrok-free\.(app|dev)|ngrok\.app)$/i', $forwardedHost)) {
            return;
        }

        if (! in_array($forwardedProto, ['http', 'https'], true)) {
            $forwardedProto = 'https';
        }

        URL::forceRootUrl("{$forwardedProto}://{$forwardedHost}");
    }

	/**
     * Configura el correo dinámicamente usando los datos de Configuration
     */
    private function configurarCorreoDesdeDB(): void
    {
        $config = Configuration::first();
        if (!$config) {
            return; // Si no hay configuración, salimos sin alterar nada
        }

        $encryption = $config->mail_encryption;
        $host = $config->mail_host ?: env('MAIL_HOST', '127.0.0.1');

        if ($encryption === 'none' || $encryption === '') {
            $encryption = null;
        }

        if (str_starts_with($host, 'ssl://')) {
            $host = str_replace('ssl://', '', $host);
        }

        Config::set('mail.driver', 'smtp');
        Config::set('mail.host', $host);
        Config::set('mail.port', (int) $config->mail_port);
        Config::set('mail.encryption', $encryption);
        Config::set('mail.username', $config->mail_username);
        Config::set('mail.password', $config->mail_password);

        Config::set('mail.from.address', $config->mail_username);
        Config::set('mail.from.name', config('app.name'));

        // Opciones del Stream SSL
        $options = [
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        Config::set('mail.stream', $options);

        // Limpiar instancias para que tome la nueva configuración
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::purge();
    }
}

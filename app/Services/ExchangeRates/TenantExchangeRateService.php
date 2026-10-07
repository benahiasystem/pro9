<?php
// ######## INICIO API BCV ########
namespace App\Services\ExchangeRates;

use App\Models\Tenant\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantExchangeRateService
{
    private BcvClient $client;

    public function __construct(BcvClient $client)
    {
        $this->client = $client;
    }

    public function exchange($date): array
    {
        $today = Carbon::now('America/Caracas')->format('Y-m-d');
        if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) ($parts[2] ?? 0), (int) ($parts[3] ?? 0), (int) ($parts[1] ?? 0))) {
            throw ValidationException::withMessages(['date' => 'Indique una fecha válida en formato YYYY-MM-DD.']);
        }
        if ($date > $today) {
            throw ValidationException::withMessages(['date' => 'No se puede consultar una tasa para una fecha futura.']);
        }
        $record = ExchangeRate::query()->where('date', $date)->first();
        if ($record) return $this->result($record);
        if ($date !== $today) {
            throw ValidationException::withMessages(['date' => 'No existe una tasa guardada para la fecha solicitada. La API BCV no ofrece histórico.']);
        }
        $rate = $this->client->dollarRate();
        $values = ['date' => $date, 'date_original' => $today,
            'sale' => $rate, 'sale_original' => $rate, 'purchase' => $rate, 'purchase_original' => $rate,
            'created_at' => now(), 'updated_at' => now()];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                ExchangeRate::query()->insert($values);
                break;
            } catch (QueryException $exception) {
                $code = $exception->errorInfo[1] ?? null;
                // Una colisión conserva la primera fila; un deadlock permite repetir sólo la inserción.
                if (in_array($code, [1062, 19], true)) break;
                if (in_array($code, [1213, 1205], true) && $attempt < 2) {
                    usleep(20000);
                    continue;
                }
                throw new HttpException(503, 'No se pudo guardar la tasa de cambio en el tenant.');
            }
        }
        $record = ExchangeRate::query()->where('date', $date)->first();
        if (!$record) throw new HttpException(503, 'No se pudo guardar la tasa de cambio en el tenant.');
        return $this->result($record);
    }

    private function result(ExchangeRate $record): array
    {
        foreach (['sale', 'purchase'] as $key) {
            if (!is_string($record->$key) || $record->$key <= 0) {
                throw new HttpException(503, 'La tasa guardada para esta fecha no es válida.');
            }
        }
        return ['date' => $record->date, 'purchase' => $record->purchase, 'sale' => $record->sale];
    }
}
// ######## FIN API BCV ########

<?php
namespace Modules\Payment\Traits;

use Culqi\Culqi;
use Culqi\Error\CulqiException;
use Culqi\Error\UnhandledError;

trait CulqiTrait
{
    public function charge($credentails, $data)
    {
        $privateKey = $credentails['private_key'];
        $culqi = new Culqi([
            'api_key' => $privateKey,
        ]);

        $charge = $culqi->Charges->create($data);
        return $this->unwrapCulqiResponse($charge);
    }

    /**
     * Charges::create() atrapa sus propias excepciones y devuelve el mensaje como string
     * en vez de propagarlo, por lo que un cargo fallido llegaba como respuesta exitosa.
     * Se restaura la excepción para que el llamador pueda distinguir el error.
     *
     * @see vendor/culqi/culqi-php/lib/Culqi/Charges.php
     * @see vendor/culqi/culqi-php/lib/Culqi/Client.php
     *
     * @throws UnhandledError  cuando culqi respondió un objeto de error
     * @throws CulqiException  cuando el sdk falló antes de llamar a la api
     */
    private function unwrapCulqiResponse($response)
    {
        // un cargo correcto se decodifica como objeto, solo los errores vuelven como string
        if (!is_string($response)) return $response;

        json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // validación local del sdk o fallo de conexión, el mensaje no es json
            throw new CulqiException($response);
        }

        throw new UnhandledError($response);
    }

}
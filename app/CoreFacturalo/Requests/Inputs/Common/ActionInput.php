<?php

namespace App\CoreFacturalo\Requests\Inputs\Common;

use App\CoreFacturalo\Requests\Inputs\Functions;

class ActionInput
{
    public static function set($inputs)
    {
        $actions = [];
        if(array_key_exists('actions', $inputs)) {
           if($inputs['actions']) {
               $actions = $inputs['actions'];
           }
        }

        return [
            'send_email' => Functions::valueKeyInArray($actions, 'send_email', false),
            'format_pdf' => Functions::valueKeyInArray($actions, 'format_pdf', 'a4'),
            // Impresión automática server-side (app mozo): evita el 2do roundtrip
            'auto_print' => self::autoPrint($actions),
            'name_printer' => Functions::valueKeyInArray($actions, 'name_printer', null),
            'client_public_ip' => Functions::valueKeyInArray($actions, 'client_public_ip', null),
        ];
    }

    private static function autoPrint($actions)
    {
        return (bool) Functions::valueKeyInArray($actions, 'auto_print', false);
    }

}

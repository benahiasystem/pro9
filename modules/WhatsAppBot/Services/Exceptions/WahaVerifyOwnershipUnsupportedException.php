<?php

namespace Modules\WhatsAppBot\Services\Exceptions;

use RuntimeException;

/**
 * WAHA no tiene un token por sesión (solo API key a nivel de servidor), así
 * que no hay forma segura de verificar la propiedad de una sesión existente
 * como sí se hace con Evolution (ver EvolutionClient::verifyOwnership()).
 * Adoptar instancias/sesiones desde ChatBuho queda exclusivo de Evolution.
 */
class WahaVerifyOwnershipUnsupportedException extends RuntimeException
{
}

<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Ecommerce\Http\Requests\DiscountCouponRequest;
use Modules\Ecommerce\Http\Resources\DiscountCouponCollection;
use Modules\Ecommerce\Http\Resources\DiscountCouponResource;
use Modules\Ecommerce\Models\Tenant\DiscountCoupon;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD de cupones de descuento para el módulo Ecommerce.
 * Expone endpoints para listar, crear/editar, cambiar estado y eliminar.
 */
class DiscountCouponController extends Controller
{
    /**
     * Retorna la vista principal del listado de cupones.
     */
    public function index()
    {
        return view('ecommerce::configuration_digital_coupon.index');
    }

    /**
     * Retorna los datos del formulario (catálogos necesarios en el modal).
     */
    public function tables()
    {
        return [
            'types' => [
                ['value' => 'percentage', 'label' => '% Porcentaje'],
                ['value' => 'fixed',      'label' => 'Bs. Fijo'],
            ],
        ];
    }

    /**
     * Retorna un registro puntual para cargar en el formulario de edición.
     */
    public function record(Request $request)
    {
        $record = DiscountCoupon::findOrFail($request->id);

        return new DiscountCouponResource($record);
    }

    /**
     * Retorna la colección paginada con filtros aplicados.
     * Filtros soportados: q (código), status (active/inactive), type, expired.
     */
    public function records(Request $request)
    {
        $query = DiscountCoupon::query();

        // Filtro por código
        if ($request->filled('q')) {
            $query->where('code', 'like', '%' . $request->q . '%');
        }

        // Filtro por tipo
        if ($request->filled('type') && in_array($request->type, ['percentage', 'fixed'])) {
            $query->where('type', $request->type);
        }

        // Filtro por estado (vencido se trata como estado derivado)
        if ($request->filled('status')) {
            if ($request->status === 'expired') {
                // Cupones con fecha de vencimiento pasada (independiente del flag active)
                $query->whereNotNull('expires_at')
                      ->where('expires_at', '<', Carbon::today()->toDateString());
            } elseif ($request->status === 'active') {
                $query->where('active', true)
                      ->where(function ($q) {
                          $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', Carbon::today()->toDateString());
                      });
            } elseif ($request->status === 'inactive') {
                $query->where('active', false);
            }
        }

        $query->orderByDesc('id');

        return new DiscountCouponCollection($query->paginate(config('tenant.items_per_page')));
    }

    /**
     * Crea o actualiza un cupón de descuento.
     */
    public function store(DiscountCouponRequest $request)
    {
        $id   = $request->input('id');
        $data = $request->validated();
        $code = mb_strtoupper(trim((string) $request->input('code')));

        // Validación manual de código
        if (!$code || strlen($code) > 20) {
            return response(['code' => ['El código del cupón es obligatorio y no puede superar 20 caracteres.']], 422);
        }
        $exists = DiscountCoupon::where('code', $code)
            ->when($id, function($q) use ($id) { $q->where('id', '!=', $id); })
            ->exists();
        if ($exists) {
            return response(['code' => ['Ya existe un cupón con ese código.']], 422);
        }

        $coupon = DiscountCoupon::firstOrNew(['id' => $id]);
        $coupon->fill($data);
        $coupon->code = $code;

        // Limpiar campos de límites cuando están desactivados
        if (!$coupon->has_purchase_limits) {
            $coupon->min_amount = null;
            $coupon->max_amount = null;
        }

        if (!$coupon->has_usage_limits) {
            $coupon->max_total_uses       = null;
            $coupon->max_uses_per_customer = null;
            $coupon->expires_at           = null;
        }

        $coupon->save();

        return new DiscountCouponResource($coupon);
    }

    /**
     * Valida un cupón contra el subtotal actual sin consumirlo.
     */
    public function validateCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:20'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ], [
            'code.required' => 'Ingresa un código de cupón.',
            'subtotal.required' => 'No se recibió el subtotal de la compra.',
            'subtotal.numeric' => 'El subtotal de la compra no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = mb_strtoupper(trim((string) $request->input('code')));
        $subtotal = round((float) $request->input('subtotal'), 2);
        $coupon = DiscountCoupon::where('code', $code)->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'El cupón ingresado no existe.',
            ], 422);
        }

        $personId = optional(auth('ecommerce')->user())->id;
        if ($message = $coupon->validationError($personId, $subtotal)) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $discount = $coupon->calculateDiscountAmount($subtotal);
        $total = max(0, round($subtotal - $discount, 2));

        return response()->json([
            'success' => true,
            'message' => 'Cupón aplicado correctamente.',
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => (float) $coupon->amount,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'new_total' => $total,
                'total' => $total,
                'free_shipping' => (bool) $coupon->free_shipping,
            ],
        ]);
    }

    /**
     * Cambia el estado activo/inactivo de un cupón.
     */
    public function updateStatus(Request $request, int $id)
    {
        $coupon = DiscountCoupon::findOrFail($id);
        $coupon->active = (bool) $request->input('active', !$coupon->active);
        $coupon->save();

        return ['success' => true, 'active' => $coupon->active];
    }

    /**
     * Elimina un cupón de descuento.
     */
    public function destroy(int $id)
    {
        $coupon = DiscountCoupon::findOrFail($id);
        $coupon->delete();

        return ['success' => true, 'message' => 'Cupón eliminado correctamente'];
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
